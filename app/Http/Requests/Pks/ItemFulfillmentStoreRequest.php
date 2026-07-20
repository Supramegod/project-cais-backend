<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;
use App\Models\Pks;
use App\Models\PksItemFulfillment;
use App\Models\QuotationChemical;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

class ItemFulfillmentStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Auto-derive leads_id & qty_diminta dari PKS + quotation item.
     * User hanya mengirim: pks_id, site_id, item_type_id, item_id, qty, catatan.
     */
    protected function prepareForValidation(): void
    {
        $pks = Pks::find($this->pks_id);

        if ($pks) {
            $this->merge(['leads_id' => $pks->leads_id]);

            // Backward-compat: accept item_type_id (int) or item_type (string)
            $typeMap = [1 => 'kaporlap', 2 => 'device', 3 => 'chemical'];
            $typeRev = ['kaporlap' => 1, 'device' => 2, 'chemical' => 3];
            $itemType = null;

            if ($this->has('item_type_id') && $this->item_type_id) {
                $itemType = $typeMap[(int) $this->item_type_id] ?? null;
                $this->merge(['item_type' => $itemType, 'item_type_id' => (int) $this->item_type_id]);
            } elseif ($this->has('item_type') && $this->item_type) {
                $itemType = $this->item_type;
                $this->merge(['item_type_id' => $typeRev[$itemType] ?? null]);
            }

            // Derive qty_diminta dari quotation item
            if ($itemType && $pks->quotation_id && $this->item_id) {
                $qtyDiminta = $this->resolveQtyDiminta($pks->quotation_id, $itemType, (int) $this->item_id);
                $this->merge(['qty_diminta' => $qtyDiminta]);
            }
        }
    }

    private function resolveQtyDiminta(int $quotationId, string $itemType, int $itemId): int
    {
        // Scope per site, beda kolom acuan per jenis (lihat ItemFulfillmentService):
        //  - kaporlap → quotation_detail_id (detail milik site)
        //  - device/chemical → quotation_site_id langsung
        // Site legacy tanpa quotation_site_id → tanpa filter.
        $scope = $this->site_id
            ? \App\Services\Pks\ItemFulfillmentService::resolveSiteScope($quotationId, (int) $this->site_id)
            : ['quotation_site_id' => null, 'detail_ids' => null];

        $detailIds = $scope['detail_ids'];
        $quotationSiteId = $scope['quotation_site_id'];

        $base = fn ($query) => $query->where('quotation_id', $quotationId)->where('id', $itemId);

        return match ($itemType) {
            'kaporlap' => (int) ($base(QuotationKaporlap::query())
                ->when($detailIds !== null, fn ($q) => $q->whereIn('quotation_detail_id', $detailIds))
                ->value('jumlah') ?? 0),
            'device' => (int) ($base(QuotationDevices::query())
                ->when($quotationSiteId !== null, fn ($q) => $q->where('quotation_site_id', $quotationSiteId))
                ->value('jumlah') ?? 0),
            'chemical' => (int) ($base(QuotationChemical::query())
                ->when($quotationSiteId !== null, fn ($q) => $q->where('quotation_site_id', $quotationSiteId))
                ->value('jumlah') ?? 0),
            default => 0,
        };
    }

    public function rules(): array
    {
        return [
            'pks_id'        => FluentRule::integer()->required()->exists('sl_pks', 'id'),
            'site_id'       => FluentRule::integer()->required()->exists('sl_site', 'id'),
            'item_type_id'  => FluentRule::integer()->required()->in([1, 2, 3]),
            'item_id'       => FluentRule::integer()->required(),
            'qty'           => FluentRule::integer()->required()->min(1),
            'catatan'       => FluentRule::string()->required()->min(10),
            // Auto-derived oleh prepareForValidation() — tidak wajib dari client
            'item_type'     => FluentRule::string()->nullable(),
            'leads_id'      => FluentRule::integer()->nullable(),
            'qty_diminta'   => FluentRule::integer()->nullable(),
        ];
    }

    public function messages(): array
    {
        return [
            'pks_id.required'    => 'PKS wajib dipilih.',
            'pks_id.exists'      => 'PKS tidak ditemukan.',
            'site_id.required'   => 'Site wajib dipilih.',
            'site_id.exists'     => 'Site tidak ditemukan.',
            'item_type_id.required' => 'Tipe item wajib dipilih.',
            'item_type_id.in'       => 'Tipe item harus 1 (kaporlap), 2 (device), atau 3 (chemical).',
            'item_id.required'   => 'Item wajib dipilih.',
            'qty.required'       => 'Qty wajib diisi.',
            'qty.min'            => 'Qty minimal 1.',
            'catatan.required'   => 'Catatan wajib diisi.',
            'catatan.min'        => 'Catatan minimal 10 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $pks = Pks::find($this->pks_id);

            if (!$pks || !$pks->quotation_id) {
                $validator->errors()->add('pks_id', 'PKS tidak valid atau belum memiliki quotation.');
                return;
            }

            $qty = (int) $this->qty;

            // Cek item valid — ada di quotation source
            if (!$this->item_type) {
                $validator->errors()->add('item_type_id', 'Tipe item tidak valid.');
                return;
            }

            $qtyDiminta = $this->resolveQtyDiminta($pks->quotation_id, $this->item_type, (int) $this->item_id);
            if ($qtyDiminta === 0) {
                $validator->errors()->add('item_id', 'Item tidak ditemukan di quotation PKS ini.');
                return;
            }

            // Hitung remaining dari fulfillment yang sudah ada
            $fulfillment = PksItemFulfillment::where('pks_id', $this->pks_id)
                ->where('site_id', $this->site_id)
                ->where('item_type', $this->item_type)
                ->where('item_id', $this->item_id)
                ->first();

            $terpenuhi = $fulfillment?->qty_terpenuhi ?? 0;
            $remaining = $qtyDiminta - $terpenuhi;

            if ($qty > $remaining) {
                $validator->errors()->add('qty', "Qty melebihi remaining ({$remaining}).");
            }
        });
    }
}
