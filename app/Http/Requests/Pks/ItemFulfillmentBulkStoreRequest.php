<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;
use App\Models\Pks;
use App\Models\PksItemFulfillment;
use App\Services\Pks\Fulfillment\ItemFulfillmentService;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

/**
 * Versi bulk dari ItemFulfillmentStoreRequest.
 *
 * Client boleh kirim dua bentuk:
 *   {"items": [ {..}, {..} ]}
 *   [ {..}, {..} ]                 (bare array, dinormalisasi ke "items")
 *
 * Per item wajib: pks_id, site_id, item_type_id, item_id, qty, catatan.
 * leads_id / item_type / qty_diminta di-derive otomatis seperti versi single.
 */
class ItemFulfillmentBulkStoreRequest extends BaseRequest
{
    public const MAX_ITEMS = 100;

    private const TYPE_MAP = [1 => 'kaporlap', 2 => 'device', 3 => 'chemical'];

    /**
     * Cache query resolveQtyDiminta, dishare antar item supaya scope site &
     * hc map tidak di-query ulang untuk setiap baris batch.
     *
     * @var array<string, mixed>
     */
    private array $qtyCache = [];

    /** @var array<int, Pks|null> */
    private array $pksCache = [];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        // Bare array di root → pakai payload itu sebagai daftar item.
        if (! is_array($items)) {
            $all = $this->all();
            $items = array_is_list($all) ? $all : [];
        }

        $typeRev = array_flip(self::TYPE_MAP);

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $pks = $this->findPks($item['pks_id'] ?? null);

            if (! $pks) {
                continue;
            }

            $item['leads_id'] = $pks->leads_id;

            // Backward-compat: accept item_type_id (int) atau item_type (string)
            $itemType = null;
            if (! empty($item['item_type_id'])) {
                $item['item_type_id'] = (int) $item['item_type_id'];
                $itemType = self::TYPE_MAP[$item['item_type_id']] ?? null;
                $item['item_type'] = $itemType;
            } elseif (! empty($item['item_type'])) {
                $itemType = $item['item_type'];
                $item['item_type_id'] = $typeRev[$itemType] ?? null;
            }

            if ($itemType && $pks->quotation_id && ! empty($item['item_id'])) {
                $item['qty_diminta'] = $this->resolveQtyDiminta($pks, $itemType, $item);
            }

            $items[$i] = $item;
        }

        $this->merge(['items' => array_values($items)]);
    }

    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required()->list()->min(1)->max(self::MAX_ITEMS),
            'items.*.pks_id' => FluentRule::integer()->required()->exists('sl_pks', 'id'),
            'items.*.site_id' => FluentRule::integer()->required()->exists('sl_site', 'id'),
            'items.*.item_type_id' => FluentRule::integer()->required()->in([1, 2, 3]),
            'items.*.item_id' => FluentRule::integer()->required(),
            'items.*.qty' => FluentRule::integer()->required()->min(1),
            // Opsional — kalau diisi tetap harus bermakna, bukan satu-dua huruf.
            'items.*.catatan' => FluentRule::string()->nullable()->min(10),
            // Auto-derived oleh prepareForValidation() — tidak wajib dari client
            'items.*.item_type' => FluentRule::string()->nullable(),
            'items.*.leads_id' => FluentRule::integer()->nullable(),
            'items.*.qty_diminta' => FluentRule::integer()->nullable(),
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Daftar item wajib diisi.',
            'items.array' => 'Daftar item harus berupa array.',
            'items.list' => 'Daftar item harus berupa array (bukan object).',
            'items.min' => 'Minimal 1 item.',
            'items.max' => 'Maksimal '.self::MAX_ITEMS.' item per request.',
            'items.*.pks_id.required' => 'PKS wajib dipilih.',
            'items.*.pks_id.exists' => 'PKS tidak ditemukan.',
            'items.*.site_id.required' => 'Site wajib dipilih.',
            'items.*.site_id.exists' => 'Site tidak ditemukan.',
            'items.*.item_type_id.required' => 'Tipe item wajib dipilih.',
            'items.*.item_type_id.in' => 'Tipe item harus 1 (kaporlap), 2 (device), atau 3 (chemical).',
            'items.*.item_id.required' => 'Item wajib dipilih.',
            'items.*.qty.required' => 'Qty wajib diisi.',
            'items.*.qty.min' => 'Qty minimal 1.',
            'items.*.catatan.min' => 'Catatan minimal 10 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Qty item yang sama boleh dikirim beberapa baris dalam satu batch,
            // jadi sisa dicek terhadap akumulasi qty batch, bukan per baris.
            $accumulated = [];

            foreach ((array) $this->input('items', []) as $i => $item) {
                $pks = $this->findPks($item['pks_id'] ?? null);

                if (! $pks || ! $pks->quotation_id) {
                    $validator->errors()->add("items.{$i}.pks_id", 'PKS tidak valid atau belum memiliki quotation.');

                    continue;
                }

                $itemType = $item['item_type'] ?? null;
                if (! $itemType) {
                    $validator->errors()->add("items.{$i}.item_type_id", 'Tipe item tidak valid.');

                    continue;
                }

                $qtyDiminta = $this->resolveQtyDiminta($pks, $itemType, $item);
                if ($qtyDiminta === 0) {
                    $validator->errors()->add("items.{$i}.item_id", 'Item tidak ditemukan di quotation PKS ini.');

                    continue;
                }

                $key = $item['pks_id'].'_'.$item['site_id'].'_'.$itemType.'_'.$item['item_id'];

                if (! array_key_exists($key, $accumulated)) {
                    // Barang yang sudah dikirim tapi belum diterima (qty_request)
                    // ikut memotong jatah — kebutuhan yang sama tidak boleh
                    // dikirim dua kali sambil menunggu penerimaan.
                    $row = PksItemFulfillment::where('pks_id', $item['pks_id'])
                        ->where('site_id', $item['site_id'])
                        ->where('item_type', $itemType)
                        ->where('item_id', $item['item_id'])
                        ->first(['qty_request', 'qty_terpenuhi']);

                    $accumulated[$key] = $qtyDiminta
                        - (int) ($row->qty_terpenuhi ?? 0)
                        - (int) ($row->qty_request ?? 0);
                }

                $accumulated[$key] -= (int) $item['qty'];

                if ($accumulated[$key] < 0) {
                    $sisa = $accumulated[$key] + (int) $item['qty'];
                    $validator->errors()->add("items.{$i}.qty", "Qty melebihi sisa yang boleh di-request ({$sisa}).");
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveQtyDiminta(Pks $pks, string $itemType, array $item): int
    {
        return ItemFulfillmentService::resolveQtyDiminta(
            (int) $pks->quotation_id,
            $itemType,
            (int) $item['item_id'],
            isset($item['site_id']) ? (int) $item['site_id'] : null,
            $this->qtyCache,
        );
    }

    private function findPks(mixed $pksId): ?Pks
    {
        $pksId = (int) $pksId;

        if ($pksId <= 0) {
            return null;
        }

        return $this->pksCache[$pksId] ??= Pks::find($pksId);
    }
}
