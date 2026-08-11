<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\Pks;
use App\Models\PksFulfillmentLog;
use App\Models\PksItemFulfillment;
use App\Models\PksItemRequest;
use App\Models\QuotationChemical;
use App\Models\QuotationDetail;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            ->select('id', 'pks_id', 'site_id', 'item_type', 'item_id', 'qty_diminta', 'qty_request', 'qty_terpenuhi', 'status')
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
        $request = (int) ($fulfillment?->qty_request ?? 0);

        return [
            'item_type_id' => $typeId,
            'item_type' => $type,
            'item_id' => $itemId,
            'nama' => $nama,
            'qty_diminta' => $qtyDiminta,
            // Sudah dikirim, menunggu konfirmasi penerimaan.
            'qty_request' => $request,
            // Sudah diterima site.
            'qty_terpenuhi' => $terpenuhi,
            'remaining' => $qtyDiminta - $terpenuhi,
            // Batas input request berikutnya — barang di jalan tidak boleh
            // dikirim ulang.
            'boleh_direquest' => max(0, $qtyDiminta - $terpenuhi - $request),
            'status' => $fulfillment?->status ?? PksItemFulfillment::STATUS_NOT_YET,
            // Catatan tidak di sini — hanya tampil di list log (getFulfillmentLog).
        ];
    }

    /**
     * Tahap 1 — request barang: catat pengiriman, bukan pemenuhan.
     *
     * qty_request naik di sini; qty_terpenuhi baru naik saat site mengonfirmasi
     * penerimaan lewat ItemReceivingService. Satu baris sl_pks_item_request
     * dibuat sebagai "yang ditunggu" dari batch ini.
     *
     * $data['batch_id'] + $data['batch_ke'] diisi saat pemanggilan datang dari
     * endpoint bulk, supaya seluruh item satu pengiriman punya penanda kelompok
     * yang sama. Kirim satuan tidak mengisi keduanya, jadi di sini dibuatkan
     * batch sendiri berisi satu item — setiap log selalu punya batch.
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
                    'qty_request' => 0,
                    'qty_terpenuhi' => 0,
                    'status' => PksItemFulfillment::STATUS_NOT_YET,
                    'created_by' => $user->full_name,
                    'created_by_user_id' => $user->id,
                ]);
            }

            $batch = isset($data['batch_id'])
                ? ['batch_id' => $data['batch_id'], 'batch_ke' => $data['batch_ke'] ?? null]
                : PksFulfillmentLog::newBatch(
                    (int) $fulfillment->pks_id,
                    PksFulfillmentLog::JENIS_ITEM,
                    PksFulfillmentLog::AKSI_REQUEST
                );

            $qty = (int) $data['qty'];
            $bolehSebelum = $fulfillment->qty_diminta - $fulfillment->qty_terpenuhi - (int) $fulfillment->qty_request;

            // Atomic increment dengan guard. Barang yang masih di jalan
            // (qty_request) ikut mengurangi jatah, supaya satu kebutuhan tidak
            // dikirim dua kali sambil menunggu penerimaan.
            $affected = PksItemFulfillment::where('id', $fulfillment->id)
                ->whereRaw('(qty_diminta - qty_terpenuhi - qty_request) >= ?', [$qty])
                ->update([
                    'qty_request' => DB::raw("qty_request + {$qty}"),
                    'updated_by' => $user->full_name,
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                throw new \RuntimeException('Qty melebihi sisa yang boleh di-request atau race condition.');
            }

            // Refresh model
            $fulfillment->refresh();

            $fulfillment->status = $fulfillment->resolveStatus();
            $fulfillment->save();

            // Baris yang ditunggu penerimaannya — inilah yang ditutup saat
            // barang dikonfirmasi diterima.
            $request = PksItemRequest::create([
                'pks_id' => $fulfillment->pks_id,
                'site_id' => $fulfillment->site_id,
                'fulfillment_id' => $fulfillment->id,
                'batch_id' => $batch['batch_id'],
                'batch_ke' => $batch['batch_ke'],
                'item_type' => $fulfillment->item_type,
                'item_id' => $fulfillment->item_id,
                'qty_request' => $qty,
                'qty_diterima' => 0,
                'status' => PksItemRequest::STATUS_OPEN,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
            ]);

            // Insert log fulfillment. Catatan hidup di sini per sesi, bukan di row.
            PksFulfillmentLog::create([
                'pks_id' => $fulfillment->pks_id,
                'site_id' => $fulfillment->site_id,
                'jenis' => PksFulfillmentLog::JENIS_ITEM,
                'reference_id' => $fulfillment->id,
                'batch_id' => $batch['batch_id'],
                'batch_ke' => $batch['batch_ke'],
                'aksi' => PksFulfillmentLog::AKSI_REQUEST,
                'catatan' => $data['catatan'] ?? null,
                'meta' => [
                    'request_id' => $request->id,
                    'qty_sesi_ini' => $qty,
                    'qty_request_berjalan' => (int) $fulfillment->qty_request,
                    'boleh_direquest_sebelum' => $bolehSebelum,
                    'boleh_direquest_sesudah' => $bolehSebelum - $qty,
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
     * Seluruh log yang lahir dari satu panggilan berbagi satu batch_id, jadi
     * satu kelompok pengiriman bisa ditarik utuh belakangan. Penelusuran lewat
     * pks_id + created_at tetap jalan karena semua baris ditulis dalam satu
     * transaksi, jadi created_at-nya berdempetan.
     *
     * @param  array<int, array<string, mixed>>  $items
     *                                                   batch_ke adalah nomor urut batch dalam satu PKS — versi terbaca manusia
     *                                                   dari batch_id. Dihitung di dalam transaksi supaya dua pengiriman bersamaan
     *                                                   pada PKS yang sama tidak mendapat nomor kembar.
     * @return array{batch_id: string, batch_ke: array<int, int>, items: array<int, PksItemFulfillment>}
     */
    public function createBulkFulfillment(array $items, User $user, ?string $batchId = null): array
    {
        $batchId ??= (string) Str::uuid();

        return DB::transaction(function () use ($items, $user, $batchId) {
            $results = [];
            $batchKe = [];

            foreach ($items as $index => $item) {
                $pksId = (int) $item['pks_id'];
                $batchKe[$pksId] ??= PksFulfillmentLog::nextBatchKe(
                    $pksId,
                    PksFulfillmentLog::JENIS_ITEM,
                    PksFulfillmentLog::AKSI_REQUEST
                );

                $item['batch_id'] = $batchId;
                $item['batch_ke'] = $batchKe[$pksId];

                try {
                    $results[] = $this->createFulfillment($item, $user);
                } catch (\RuntimeException $e) {
                    throw new \RuntimeException('Item ke-'.($index + 1).': '.$e->getMessage(), 0, $e);
                }
            }

            return ['batch_id' => $batchId, 'batch_ke' => $batchKe, 'items' => $results];
        });
    }

    /**
     * Koreksi jumlah yang DITERIMA (qty_terpenuhi) — hanya untuk role tertentu
     * (di-check di controller level).
     *
     * Tidak menyentuh qty_request: barang yang masih di jalan urusan penerimaan,
     * bukan koreksi angka terima. Justru karena itu koreksinya harus tunduk pada
     * invarian yang sama dengan jalur request —
     * qty_terpenuhi + qty_request <= qty_diminta. Tanpa itu, menaikkan
     * qty_terpenuhi selagi ada barang di jalan membuat penerimaannya nanti
     * menembus qty_diminta (10 diminta, dikirim 10, dikoreksi jadi 10, lalu
     * diterima 10 = 20 terpenuhi).
     *
     * Barang yang masih menunggu harus dicatat lewat penerimaan, bukan
     * diselundupkan lewat koreksi — supaya baris permintaannya ikut ditutup dan
     * riwayat batch-nya tetap utuh.
     */
    public function editFulfillment(PksItemFulfillment $fulfillment, int $newQty, string $catatan, User $user): PksItemFulfillment
    {
        return DB::transaction(function () use ($fulfillment, $newQty, $catatan, $user) {
            // Dikunci karena koreksi dan penerimaan sama-sama menulis
            // qty_terpenuhi; tanpa ini keduanya bisa saling menimpa.
            $fulfillment = PksItemFulfillment::whereKey($fulfillment->getKey())
                ->lockForUpdate()
                ->first();

            if (! $fulfillment) {
                throw new \RuntimeException('Data fulfillment tidak ditemukan.');
            }

            $oldQty = $fulfillment->qty_terpenuhi;
            $remainingBefore = $fulfillment->qty_diminta - $fulfillment->qty_terpenuhi;

            if ($newQty < 0 || $newQty > $fulfillment->qty_diminta) {
                throw new \RuntimeException('Qty tidak valid.');
            }

            $menunggu = (int) $fulfillment->qty_request;

            if ($newQty + $menunggu > (int) $fulfillment->qty_diminta) {
                $maksimal = (int) $fulfillment->qty_diminta - $menunggu;

                throw new \RuntimeException(
                    "Qty terlalu besar: {$menunggu} unit masih menunggu penerimaan, ".
                    "jadi koreksi maksimal {$maksimal}. Catat penerimaannya dulu bila barang sudah sampai."
                );
            }

            $fulfillment->qty_terpenuhi = $newQty;
            $remainingAfter = $fulfillment->qty_diminta - $newQty;

            $fulfillment->status = $fulfillment->resolveStatus();

            $fulfillment->updated_by = $user->full_name;
            $fulfillment->save();

            // Edit juga satu batch — isinya satu log — supaya riwayat PKS bisa
            // dibaca seragam per batch tanpa kasus khusus.
            $batch = PksFulfillmentLog::newBatch(
                (int) $fulfillment->pks_id,
                PksFulfillmentLog::JENIS_ITEM,
                PksFulfillmentLog::AKSI_EDIT
            );

            // Insert audit log fulfillment
            PksFulfillmentLog::create([
                'pks_id' => $fulfillment->pks_id,
                'site_id' => $fulfillment->site_id,
                'jenis' => PksFulfillmentLog::JENIS_ITEM,
                'reference_id' => $fulfillment->id,
                'batch_id' => $batch['batch_id'],
                'batch_ke' => $batch['batch_ke'],
                'aksi' => PksFulfillmentLog::AKSI_EDIT,
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
        // Dipakai menghitung remaining pada log penerimaan, yang metanya
        // menyimpan qty_terpenuhi (bukan remaining). qty_diminta tetap sepanjang
        // hidup baris, jadi aman dipakai untuk log lama sekalipun.
        $qtyDiminta = (int) PksItemFulfillment::withTrashed()
            ->whereKey($fulfillmentId)
            ->value('qty_diminta');

        // Flatten meta JSON balik ke top-level supaya bentuk response endpoint
        // tetap sama seperti sebelum log dipindah ke tabel fulfillment.
        return PksFulfillmentLog::forRef(PksFulfillmentLog::JENIS_ITEM, $fulfillmentId)
            ->select('id', 'site_id', 'reference_id', 'batch_id', 'batch_ke', 'aksi', 'meta', 'catatan', 'created_by', 'created_at')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (PksFulfillmentLog $log) use ($qtyDiminta) {
                $meta = $log->meta ?? [];

                $baris = [
                    'id' => $log->id,
                    'site_id' => $log->site_id,
                    'fulfillment_id' => $log->reference_id,
                    'batch_id' => $log->batch_id,
                    'batch_ke' => $log->batch_ke,
                    'aksi' => $log->aksi,
                    'catatan' => $log->catatan,
                    'created_by' => $log->created_by,
                    'created_at' => $log->created_at,
                ];

                // Log penerimaan memakai kunci meta sendiri (qty_diterima dkk),
                // bukan qty_sesi_ini/remaining_*. Tanpa cabang ini seluruh baris
                // receive terbaca 0 — seolah tidak ada barang yang diterima.
                if ($log->aksi === PksFulfillmentLog::AKSI_RECEIVE) {
                    $terpenuhiSebelum = (int) ($meta['qty_terpenuhi_sebelum'] ?? 0);
                    $terpenuhiSesudah = (int) ($meta['qty_terpenuhi_sesudah'] ?? 0);

                    return $baris + [
                        // Diisi qty yang diterima supaya klien lama yang membaca
                        // qty_sesi_ini tetap mendapat angka sesi ini.
                        'qty_sesi_ini' => (int) ($meta['qty_diterima'] ?? 0),
                        'remaining_sebelum' => $qtyDiminta - $terpenuhiSebelum,
                        'remaining_sesudah' => $qtyDiminta - $terpenuhiSesudah,
                        'qty_diterima' => (int) ($meta['qty_diterima'] ?? 0),
                        'qty_request_ditutup' => $meta['qty_request_ditutup'] ?? null,
                        'kurang' => $meta['kurang'] ?? null,
                        'request_ids' => $meta['request_ids'] ?? [],
                    ];
                }

                return $baris + [
                    'qty_sesi_ini' => $meta['qty_sesi_ini'] ?? 0,
                    'remaining_sebelum' => $meta['remaining_sebelum'] ?? 0,
                    'remaining_sesudah' => $meta['remaining_sesudah'] ?? 0,
                ];
            });
    }
}
