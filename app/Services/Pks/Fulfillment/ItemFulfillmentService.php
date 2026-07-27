<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\Pks;
use App\Models\PksFulfillmentLog;
use App\Models\PksItemFulfillment;
use App\Models\QuotationChemical;
use App\Models\QuotationDetail;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\Site;
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

        // 1. Kaporlap — scope via quotation_detail_id.
        // jumlah pada sl_quotation_kaporlap = kebutuhan PER PERSONIL, jadi qty riil
        // yang harus dipenuhi = jumlah x jumlah_hc pada detail-nya.
        $kaporlaps = $quotation->quotationKaporlaps()->select('id', 'jumlah', 'nama', 'quotation_detail_id')
            ->when($detailIds !== null, fn ($q) => $q->whereIn('quotation_detail_id', $detailIds))
            ->get();
        $hcMap = $kaporlaps->isEmpty() ? [] : self::detailHcMap($quotation->id);
        foreach ($kaporlaps as $item) {
            $key = 'kaporlap_'.$item->id;
            $f = $fulfillments->get($key);
            $qty = self::kaporlapQty((int) $item->jumlah, $item->quotation_detail_id, $hcMap);
            $results[] = $this->formatItem(1, 'kaporlap', $item->id, $item->nama ?? 'Kaporlap #'.$item->id, $qty, $f);
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
        $quotationSiteId = Site::whereKey($siteId)->value('quotation_site_id');
        if (! $quotationSiteId) {
            return ['quotation_site_id' => null, 'detail_ids' => null];
        }

        $detailIds = QuotationDetail::where('quotation_id', $quotationId)
            ->where('quotation_site_id', $quotationSiteId)
            ->pluck('id')
            ->all();

        return ['quotation_site_id' => (int) $quotationSiteId, 'detail_ids' => $detailIds];
    }

    /**
     * Map quotation_detail_id => jumlah_hc untuk 1 quotation.
     *
     * @return array<int, int|null>
     */
    public static function detailHcMap(int $quotationId): array
    {
        return self::detailHcMapMany([$quotationId]);
    }

    /**
     * Versi batch detailHcMap() — 1 query untuk banyak quotation sekaligus.
     * Aman digabung karena id detail unik lintas quotation.
     *
     * @param  array<int>  $quotationIds
     * @return array<int, int|null>
     */
    public static function detailHcMapMany(array $quotationIds): array
    {
        if (empty($quotationIds)) {
            return [];
        }

        return QuotationDetail::whereIn('quotation_id', $quotationIds)
            ->pluck('jumlah_hc', 'id')
            ->all();
    }

    /**
     * Qty diminta untuk satu item quotation, sudah di-scope per site.
     * Beda kolom acuan per jenis (lihat resolveSiteScope):
     *  - kaporlap        → quotation_detail_id (detail milik site), dikali jumlah_hc
     *  - device/chemical → quotation_site_id langsung
     * Site legacy tanpa quotation_site_id → tanpa filter.
     *
     * $cache dipakai untuk pemanggilan batch (bulk) supaya scope & hc map
     * tidak di-query ulang per item. Cukup share satu array antar pemanggilan.
     *
     * @param  array<string, mixed>  $cache
     */
    public static function resolveQtyDiminta(
        int $quotationId,
        string $itemType,
        int $itemId,
        ?int $siteId,
        array &$cache = []
    ): int {
        $scopeKey = "scope:{$quotationId}:".($siteId ?? 0);
        if (! array_key_exists($scopeKey, $cache)) {
            $cache[$scopeKey] = $siteId
                ? self::resolveSiteScope($quotationId, $siteId)
                : ['quotation_site_id' => null, 'detail_ids' => null];
        }

        $detailIds = $cache[$scopeKey]['detail_ids'];
        $quotationSiteId = $cache[$scopeKey]['quotation_site_id'];

        if ($itemType === 'kaporlap') {
            $item = QuotationKaporlap::query()
                ->where('quotation_id', $quotationId)
                ->where('id', $itemId)
                ->when($detailIds !== null, fn ($q) => $q->whereIn('quotation_detail_id', $detailIds))
                ->first(['jumlah', 'quotation_detail_id']);

            if (! $item) {
                return 0;
            }

            $hcKey = "hc:{$quotationId}";
            if (! array_key_exists($hcKey, $cache)) {
                $cache[$hcKey] = self::detailHcMap($quotationId);
            }

            return self::kaporlapQty((int) $item->jumlah, $item->quotation_detail_id, $cache[$hcKey]);
        }

        $model = match ($itemType) {
            'device' => QuotationDevices::query(),
            'chemical' => QuotationChemical::query(),
            default => null,
        };

        if ($model === null) {
            return 0;
        }

        return (int) ($model
            ->where('quotation_id', $quotationId)
            ->where('id', $itemId)
            ->when($quotationSiteId !== null, fn ($q) => $q->where('quotation_site_id', $quotationSiteId))
            ->value('jumlah') ?? 0);
    }

    /**
     * Qty kaporlap riil = jumlah (per personil) x jumlah_hc detail.
     * Detail tidak diketahui / jumlah_hc null (data legacy) → pengali 1.
     *
     * @param  array<int, int|null>  $hcMap
     */
    public static function kaporlapQty(int $jumlah, ?int $detailId, array $hcMap): int
    {
        $hc = $detailId !== null ? ($hcMap[$detailId] ?? null) : null;

        return $jumlah * (int) ($hc ?? 1);
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
            // Catatan tidak di sini — hanya tampil di list log (getFulfillmentLog).
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

            // Insert log fulfillment. Catatan hidup di sini per sesi, bukan di row.
            PksFulfillmentLog::create([
                'pks_id' => $fulfillment->pks_id,
                'site_id' => $fulfillment->site_id,
                'jenis' => PksFulfillmentLog::JENIS_ITEM,
                'reference_id' => $fulfillment->id,
                'aksi' => 'create',
                'catatan' => $data['catatan'] ?? null,
                'meta' => [
                    'qty_sesi_ini' => $qty,
                    'remaining_sebelum' => $remainingBefore,
                    'remaining_sesudah' => $remaining,
                ],
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
            ]);

            return $fulfillment;
        });
    }

    /**
     * Bulk create fulfillment session — semua item dalam satu transaksi.
     * Gagal satu item = rollback seluruh batch (all-or-nothing) supaya client
     * tidak perlu tahu item mana yang sudah masuk sebagian.
     *
     * Item duplikat (pks+site+item sama) dalam satu batch tetap aman: guard
     * atomic di createFulfillment membaca qty_terpenuhi hasil item sebelumnya.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, PksItemFulfillment>
     */
    public function createBulkFulfillment(array $items, User $user): array
    {
        return DB::transaction(function () use ($items, $user) {
            $results = [];

            foreach ($items as $index => $item) {
                try {
                    $results[] = $this->createFulfillment($item, $user);
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('Item ke-'.($index + 1).': '.$e->getMessage(), 0, $e);
                }
            }

            return $results;
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

            // Insert audit log fulfillment
            PksFulfillmentLog::create([
                'pks_id' => $fulfillment->pks_id,
                'site_id' => $fulfillment->site_id,
                'jenis' => PksFulfillmentLog::JENIS_ITEM,
                'reference_id' => $fulfillment->id,
                'aksi' => 'edit',
                'catatan' => $catatan,
                'meta' => [
                    'qty_sesi_ini' => $newQty - $oldQty, // delta
                    'remaining_sebelum' => $remainingBefore,
                    'remaining_sesudah' => $remainingAfter,
                ],
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
            ]);

            return $fulfillment;
        });
    }

    public function getFulfillmentLog(int $fulfillmentId): Collection
    {
        // Flatten meta JSON balik ke top-level supaya bentuk response endpoint
        // tetap sama seperti sebelum log dipindah ke tabel fulfillment.
        return PksFulfillmentLog::forRef(PksFulfillmentLog::JENIS_ITEM, $fulfillmentId)
            ->select('id', 'site_id', 'reference_id', 'aksi', 'meta', 'catatan', 'created_by', 'created_at')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (PksFulfillmentLog $log) {
                $meta = $log->meta ?? [];

                return [
                    'id' => $log->id,
                    'site_id' => $log->site_id,
                    'fulfillment_id' => $log->reference_id,
                    'aksi' => $log->aksi,
                    'qty_sesi_ini' => $meta['qty_sesi_ini'] ?? 0,
                    'remaining_sebelum' => $meta['remaining_sebelum'] ?? 0,
                    'remaining_sesudah' => $meta['remaining_sesudah'] ?? 0,
                    'catatan' => $log->catatan,
                    'created_by' => $log->created_by,
                    'created_at' => $log->created_at,
                ];
            });
    }

    /**
     * Log seluruh modul fulfillment untuk satu PKS (item + visit), terbaru dulu.
     * Filter opsional per jenis (PksFulfillmentLog::JENIS_ITEM / JENIS_VISIT).
     * meta dikembalikan apa adanya karena isinya beda per jenis.
     */
    public function getPksLog(int $pksId, ?string $jenis = null): Collection
    {
        return PksFulfillmentLog::forPks($pksId)
            ->when($jenis !== null, fn ($q) => $q->where('jenis', $jenis))
            ->select('id', 'pks_id', 'site_id', 'jenis', 'reference_id', 'aksi', 'catatan', 'meta', 'created_by', 'created_at')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (PksFulfillmentLog $log) {
                return [
                    'id' => $log->id,
                    'site_id' => $log->site_id,
                    'jenis' => $log->jenis,
                    'reference_id' => $log->reference_id,
                    'aksi' => $log->aksi,
                    'catatan' => $log->catatan,
                    'meta' => $log->meta,
                    'created_by' => $log->created_by,
                    'created_at' => $log->created_at,
                ];
            });
    }
}
