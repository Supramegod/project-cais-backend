<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;
use App\Models\PksItemRequest;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

/**
 * Tahap 2 — konfirmasi penerimaan barang di site.
 *
 * Body:
 *   {
 *     "batch_id": "9b1f…",          // opsional, batasi ke satu batch pengiriman
 *     "catatan": "1 seragam rusak", // opsional, berlaku untuk semua item
 *     "items": [ {"fulfillment_id": 12, "qty": 4}, … ]
 *   }
 *
 * Qty per item boleh lebih kecil dari yang dikirim (barang kurang/rusak), tapi
 * tidak boleh melebihi yang masih menunggu penerimaan.
 */
class ItemFulfillmentReceiveRequest extends BaseRequest
{
    public const MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => FluentRule::string()->nullable()->uuid(),
            // Opsional — kalau diisi tetap harus bermakna, bukan satu-dua huruf.
            'catatan' => FluentRule::string()->nullable()->min(10),
            'items' => FluentRule::array()->required()->list()->min(1)->max(self::MAX_ITEMS),
            'items.*.fulfillment_id' => FluentRule::integer()->required()->exists('sl_pks_item_fulfillment', 'id'),
            'items.*.qty' => FluentRule::integer()->required()->min(1),
            'items.*.catatan' => FluentRule::string()->nullable()->min(10),
        ];
    }

    public function messages(): array
    {
        return [
            'batch_id.uuid' => 'Batch id tidak valid.',
            'catatan.min' => 'Catatan minimal 10 karakter.',
            'items.required' => 'Daftar item wajib diisi.',
            'items.array' => 'Daftar item harus berupa array.',
            'items.list' => 'Daftar item harus berupa array (bukan object).',
            'items.min' => 'Minimal 1 item.',
            'items.max' => 'Maksimal '.self::MAX_ITEMS.' item per penerimaan.',
            'items.*.fulfillment_id.required' => 'Item wajib dipilih.',
            'items.*.fulfillment_id.exists' => 'Data fulfillment tidak ditemukan.',
            'items.*.qty.required' => 'Qty diterima wajib diisi.',
            'items.*.qty.min' => 'Qty diterima minimal 1.',
            'items.*.catatan.min' => 'Catatan minimal 10 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $batchId = $this->input('batch_id');

            // Satu fulfillment boleh muncul beberapa baris; jatahnya dipotong
            // berurutan supaya totalnya tetap tidak melebihi yang dikirim.
            $sisa = [];

            foreach ((array) $this->input('items', []) as $i => $item) {
                $fulfillmentId = (int) ($item['fulfillment_id'] ?? 0);

                if (! array_key_exists($fulfillmentId, $sisa)) {
                    $sisa[$fulfillmentId] = (int) PksItemRequest::query()
                        ->forFulfillment($fulfillmentId)
                        ->open()
                        ->when($batchId !== null, fn ($q) => $q->forBatch($batchId))
                        ->sum('qty_request');
                }

                if ($sisa[$fulfillmentId] === 0) {
                    $validator->errors()->add(
                        "items.{$i}.fulfillment_id",
                        'Tidak ada permintaan barang yang menunggu penerimaan.'
                    );

                    continue;
                }

                $sisa[$fulfillmentId] -= (int) $item['qty'];

                if ($sisa[$fulfillmentId] < 0) {
                    $menunggu = $sisa[$fulfillmentId] + (int) $item['qty'];
                    $validator->errors()->add(
                        "items.{$i}.qty",
                        "Qty diterima melebihi yang dikirim dan belum diterima ({$menunggu})."
                    );
                }
            }
        });
    }
}
