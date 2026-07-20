<?php

namespace App\Services\Pks;

use App\Models\Pks;
use App\Models\PksItemFulfillment;
use App\Models\PksItemFulfillmentLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemFulfillmentService
{
    /**
     * Get requested items + fulfillment status untuk site dalam PKS.
     * Item sourced dari quotation pivot tables (kaporlap, device, chemical — EXCLUDE OHC).
     * Preload semua fulfillment dalam 1 query untuk menghindari N+1.
     */
    public function getRequestedItems(Pks $pks, int $siteId): array
    {
        $quotation = $pks->quotations;
        if (! $quotation) {
            return [];
        }

        // Scope item per site. Beda kolom acuan per jenis barang (lihat
        // QuotationBarangService::getModelConfig):
        //  - kaporlap  → quotation_detail_id (detail milik site)
        //  - device    → quotation_site_id langsung (detail_id NULL di DB)
        //  - chemical  → quotation_site_id langsung (detail_id NULL di DB)
        // Site legacy tanpa quotation_site_id → null → tanpa filter (tampilkan semua).
        $scope = self::resolveSiteScope($quotation->id, $siteId);
        $detailIds = $scope['detail_ids'];        // null = tanpa filter kaporlap
        $quotationSiteId = $scope['quotation_site_id']; // null = tanpa filter device/chemical

        // Preload ALL fulfillments untuk PKS + site ini dalam 1 query (select kolom yg dibutuhin aja)
        $fulfillments = PksItemFulfillment::where('pks_id', $pks->id)
            ->where('site_id', $siteId)
            ->select('id', 'pks_id', 'site_id', 'item_type', 'item_id', 'qty_diminta', 'qty_terpenuhi', 'status')
            ->get()
            ->keyBy(function ($f) {
                return $f->item_type.'_'.$f->item_id;
            });

        $results = [];

        // 1. Kaporlap — scope via quotation_detail_id
        $kaporlaps = $quotation->quotationKaporlaps()->select('id', 'jumlah', 'nama')
            ->when($detailIds !== null, fn ($q) => $q->whereIn('quotation_detail_id', $detailIds))
            ->get();
        foreach ($kaporlaps as $item) {
            $key = 'kaporlap_'.$item->id;
            $f = $fulfillments->get($key);
            $results[] = $this->formatItem(1, 'kaporlap', $item->id, $item->nama ?? 'Kaporlap #'.$item->id, (int) $item->jumlah, $f);
        }

        // 2. Device — scope via quotation_site_id
        $devices = $quotation->quotationDevices()->select('id', 'jumlah', 'nama')
            ->when($quotationSiteId !== null, fn ($q) => $q->where('quotation_site_id', $quotationSiteId))
            ->get();
        foreach ($devices as $item) {
            $key = 'device_'.$item->id;
            $f = $fulfillments->get($key);
            $results[] = $this->formatItem(2, 'device', $item->id, $item->nama ?? 'Device #'.$item->id, (int) $item->jumlah, $f);
        }

        // 3. Chemical — scope via quotation_site_id
        $chemicals = $quotation->quotationChemicals()->select('id', 'jumlah', 'nama')
            ->when($quotationSiteId !== null, fn ($q) => $q->where('quotation_site_id', $quotationSiteId))
            ->get();
        foreach ($chemicals as $item) {
            $key = 'chemical_'.$item->id;
            $f = $fulfillments->get($key);
            $results[] = $this->formatItem(3, 'chemical', $item->id, $item->nama ?? 'Chemical #'.$item->id, (int) $item->jumlah, $f);
        }

        return $results;
    }

    /**
     * Scope site untuk filter item quotation.
     *  - quotation_site_id: id sl_quotation_site milik sl_site (untuk device/chemical/ohc).
     *  - detail_ids: id sl_quotation_detail milik site tsb (untuk kaporlap).
     * Keduanya null bila site tidak punya quotation_site_id (site legacy) → tanpa filter.
     *
     * @return array{quotation_site_id: int|null, detail_ids: array<int>|null}
     */
    public static function resolveSiteScope(int $quotationId, int $siteId): array
    {
        $quotationSiteId = DB::table('sl_site')->where('id', $siteId)->value('quotation_site_id');
        if (! $quotationSiteId) {
            return ['quotation_site_id' => null, 'detail_ids' => null];
        }

        $detailIds = DB::table('sl_quotation_detail')
            ->where('quotation_id', $quotationId)
            ->where('quotation_site_id', $quotationSiteId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        return ['quotation_site_id' => (int) $quotationSiteId, 'detail_ids' => $detailIds];
    }

    private function formatItem(int $typeId, string $type, int $itemId, string $nama, int $qtyDiminta, $fulfillment): array
    {
        $terpenuhi = $fulfillment?->qty_terpenuhi ?? 0;

        return [
            'item_type_id' => $typeId,
            'item_type' => $type,
            'item_id' => $itemId,
            'nama' => $nama,
            'qty_diminta' => $qtyDiminta,
            'qty_terpenuhi' => $terpenuhi,
            'remaining' => $qtyDiminta - $terpenuhi,
            'status' => $fulfillment?->status ?? 'not_yet_fulfilled',
        ];
    }

    /**
     * Create fulfillment session — atomic increment.
     */
    public function createFulfillment(array $data, User $user): PksItemFulfillment
    {
        return DB::transaction(function () use ($data, $user) {
            // Find or restore existing (including soft-deleted) fulfillment record
            $fulfillment = PksItemFulfillment::withTrashed()
                ->where('pks_id', $data['pks_id'])
                ->where('site_id', $data['site_id'])
                ->where('item_type', $data['item_type'])
                ->where('item_id', $data['item_id'])
                ->first();

            if ($fulfillment && $fulfillment->trashed()) {
                $fulfillment->restore();
            } elseif (! $fulfillment) {
                $fulfillment = PksItemFulfillment::create([
                    'pks_id' => $data['pks_id'],
                    'site_id' => $data['site_id'],
                    'item_type' => $data['item_type'],
                    'item_id' => $data['item_id'],
                    'leads_id' => $data['leads_id'] ?? null,
                    'qty_diminta' => $data['qty_diminta'] ?? 0,
                    'qty_terpenuhi' => 0,
                    'status' => 'not_yet_fulfilled',
                    'created_by' => $user->full_name,
                    'created_by_user_id' => $user->id,
                ]);
            }

            $qty = (int) $data['qty'];
            $remainingBefore = $fulfillment->qty_diminta - $fulfillment->qty_terpenuhi;

            // Atomic increment dengan guard
            $affected = PksItemFulfillment::where('id', $fulfillment->id)
                ->whereRaw('(qty_diminta - qty_terpenuhi) >= ?', [$qty])
                ->update([
                    'qty_terpenuhi' => DB::raw("qty_terpenuhi + {$qty}"),
                    'updated_by' => $user->full_name,
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                throw new \RuntimeException('Qty melebihi remaining atau race condition.');
            }

            // Refresh model
            $fulfillment->refresh();

            // Update status
            $remaining = $fulfillment->qty_diminta - $fulfillment->qty_terpenuhi;
            $fulfillment->status = $remaining === 0 ? 'fully_fulfilled' : 'partially_fulfilled';
            $fulfillment->save();

            // Insert log
            PksItemFulfillmentLog::create([
                'fulfillment_id' => $fulfillment->id,
                'aksi' => 'create',
                'qty_sesi_ini' => $qty,
                'remaining_sebelum' => $remainingBefore,
                'remaining_sesudah' => $remaining,
                'catatan' => $data['catatan'] ?? null,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
            ]);

            return $fulfillment;
        });
    }

    /**
     * Edit fulfillment — hanya untuk cais_role_id 8/10/98 (di-check di controller level).
     */
    public function editFulfillment(PksItemFulfillment $fulfillment, int $newQty, string $catatan, User $user): PksItemFulfillment
    {
        return DB::transaction(function () use ($fulfillment, $newQty, $catatan, $user) {
            $oldQty = $fulfillment->qty_terpenuhi;
            $remainingBefore = $fulfillment->qty_diminta - $fulfillment->qty_terpenuhi;

            if ($newQty < 0 || $newQty > $fulfillment->qty_diminta) {
                throw new \RuntimeException('Qty tidak valid.');
            }

            $fulfillment->qty_terpenuhi = $newQty;
            $remainingAfter = $fulfillment->qty_diminta - $newQty;

            $fulfillment->status = $remainingAfter === 0
                ? 'fully_fulfilled'
                : ($newQty > 0 ? 'partially_fulfilled' : 'not_yet_fulfilled');

            $fulfillment->updated_by = $user->full_name;
            $fulfillment->save();

            // Insert audit log
            PksItemFulfillmentLog::create([
                'fulfillment_id' => $fulfillment->id,
                'aksi' => 'edit',
                'qty_sesi_ini' => $newQty - $oldQty, // delta
                'remaining_sebelum' => $remainingBefore,
                'remaining_sesudah' => $remainingAfter,
                'catatan' => $catatan,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
            ]);

            return $fulfillment;
        });
    }

    public function getFulfillmentLog(int $fulfillmentId): Collection
    {
        return PksItemFulfillmentLog::where('fulfillment_id', $fulfillmentId)
            ->select('id', 'fulfillment_id', 'aksi', 'qty_sesi_ini', 'remaining_sebelum', 'remaining_sesudah', 'catatan', 'created_by', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
