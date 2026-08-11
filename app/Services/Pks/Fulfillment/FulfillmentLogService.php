<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\PksFulfillmentLog;
use App\Models\PksItemFulfillment;
use App\Models\PksItemRequest;
use App\Models\PksVisitRecord;
use App\Models\QuotationChemical;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use Illuminate\Support\Collection;

/**
 * Pembacaan log modul fulfillment (item + visit).
 *
 * Dipisah dari ItemFulfillmentService karena isinya lintas jenis: satu PKS
 * punya log item dan log visit dalam satu tabel, dan keduanya dibaca lewat
 * pengelompokan yang sama.
 */
class FulfillmentLogService
{
    /** item_type di tabel fulfillment => item_type_id yang dipakai API. */
    private const TYPE_IDS = ['kaporlap' => 1, 'device' => 2, 'chemical' => 3];

    /**
     * Bentuk rekap saat tidak ada yang bisa dijawab — batch bukan pengiriman,
     * atau seluruh lognya lama tanpa tautan ke baris permintaan. Dijadikan
     * konstanta supaya kunci-kuncinya selalu hadir, jadi klien tidak perlu
     * membedakan "tidak relevan" dari "field-nya hilang".
     */
    private const REKAP_KOSONG = [
        'status_penerimaan' => null,
        'jumlah_diterima' => 0,
        'jumlah_menunggu' => 0,
        'jumlah_kurang' => 0,
        'jumlah_tanpa_tautan' => 0,
        'qty_kurang' => 0,
    ];

    /**
     * Log satu PKS, dikelompokkan per batch, terbaru dulu.
     * Filter opsional per jenis (PksFulfillmentLog::JENIS_ITEM / JENIS_VISIT).
     *
     * Log lama (sebelum kolom batch ada) tidak punya batch_id — tiap barisnya
     * jadi grup sendiri supaya tidak ada riwayat yang hilang dari tampilan.
     *
     * Batch pengiriman ikut membawa rekap penerimaannya, supaya daftar ini bisa
     * menjawab "sudah diterima belum" tanpa membuka satu per satu. Tanpa itu
     * seluruh batch kirim terbaca sama saja — `aksi`-nya memang selamanya
     * 'request'.
     */
    public function getPksLog(int $pksId, ?string $jenis = null): Collection
    {
        $logs = PksFulfillmentLog::forPks($pksId)
            ->when($jenis !== null, fn ($q) => $q->where('jenis', $jenis))
            ->select('id', 'pks_id', 'site_id', 'jenis', 'reference_id', 'batch_id', 'batch_ke', 'aksi', 'catatan', 'meta', 'created_by', 'created_at')
            ->orderBy('id', 'desc')
            ->get();

        // Satu query untuk seluruh log PKS, bukan per grup.
        $requests = $this->requestRows($logs);

        return $logs
            ->groupBy(fn (PksFulfillmentLog $log) => $log->batch_id ?: 'log:'.$log->id)
            ->map(function (Collection $group) use ($requests) {
                $first = $group->first();
                $aksi = $group->pluck('aksi')->unique();

                $rekap = $this->batchPengiriman($first->jenis, $aksi)
                    ? $this->rekapPenerimaan($group, $requests)
                    : self::REKAP_KOSONG;

                return [
                    'batch_id' => $first->batch_id,
                    'batch_ke' => $first->batch_ke,
                    'jenis' => $first->jenis,
                    // Satu batch normalnya satu aksi; 'mixed' hanya muncul kalau
                    // suatu saat satu batch menggabungkan create dan edit.
                    'aksi' => $aksi->count() === 1 ? $aksi->first() : 'mixed',
                    'jumlah_log' => $group->count(),
                    'created_by' => $first->created_by,
                    'created_at' => $first->created_at,
                ] + $rekap + [
                    'logs' => $group->map(fn (PksFulfillmentLog $log) => [
                        'id' => $log->id,
                        'site_id' => $log->site_id,
                        'jenis' => $log->jenis,
                        'reference_id' => $log->reference_id,
                        'batch_id' => $log->batch_id,
                        'batch_ke' => $log->batch_ke,
                        'aksi' => $log->aksi,
                        'catatan' => $log->catatan,
                        'meta' => $log->meta,
                        'created_by' => $log->created_by,
                        'created_at' => $log->created_at,
                    ])->values()->all(),
                ];
            })
            ->values();
    }

    /**
     * Isi satu batch: barang apa saja yang dikirim, berapa, dan sisanya.
     *
     * Barang diambil lewat reference_id di log — untuk jenis item itu id
     * sl_pks_item_fulfillment, untuk visit id sl_pks_visit_record. Qty yang
     * ditampilkan diambil dari meta log (qty_sesi_ini), bukan dari tabel
     * fulfillment: tabel itu menyimpan akumulasi seluruh pengiriman, sedangkan
     * yang ditanyakan "batch ini mengirim berapa".
     *
     * @return array<string, mixed>|null null bila batch tidak ada
     */
    public function getBatchDetail(string $batchId): ?array
    {
        $logs = PksFulfillmentLog::forBatch($batchId)
            ->select('id', 'pks_id', 'site_id', 'jenis', 'reference_id', 'batch_id', 'batch_ke', 'aksi', 'catatan', 'meta', 'created_by', 'created_at')
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $first = $logs->first();
        $requests = $this->requestRows($logs);

        $items = $first->jenis === PksFulfillmentLog::JENIS_VISIT
            ? $this->visitItems($logs)
            : $this->fulfillmentItems($logs, $requests);

        // Batch pengiriman punya pertanyaan lanjutan yang tidak terjawab oleh
        // `aksi`: barangnya sudah diterima atau belum. Batch penerimaan dan
        // visit tidak, jadi rekapnya nol/null di sana.
        $rekap = $this->batchPengiriman($first->jenis, collect([$first->aksi]))
            ? $this->rekapPenerimaan($logs, $requests)
            : self::REKAP_KOSONG;

        return [
            'batch_id' => $first->batch_id,
            'batch_ke' => $first->batch_ke,
            'jenis' => $first->jenis,
            // Menentukan arti angka di dalam items: request/receive/edit.
            // Nilainya melekat pada batch dan tidak pernah berubah — status
            // penerimaannya dibaca dari status_penerimaan di bawah, bukan dari
            // sini.
            'aksi' => $first->aksi,
            'pks_id' => $first->pks_id,
            'created_by' => $first->created_by,
            'created_at' => $first->created_at,
            'jumlah_item' => count($items),
        ] + $rekap + [
            'items' => $items,
        ];
    }

    /**
     * Baris batch jenis item: log => record fulfillment => nama barang.
     *
     * @param  Collection<int, PksFulfillmentLog>  $logs
     * @param  Collection<int, PksItemRequest>  $requests
     * @return array<int, array<string, mixed>>
     */
    private function fulfillmentItems(Collection $logs, Collection $requests): array
    {
        $fulfillments = PksItemFulfillment::withTrashed()
            ->whereIn('id', $logs->pluck('reference_id')->unique()->all())
            ->select('id', 'site_id', 'item_type', 'item_id', 'qty_diminta', 'qty_request', 'qty_terpenuhi', 'status')
            ->get()
            ->keyBy('id');

        $names = self::itemNames($fulfillments);

        return $logs->map(function (PksFulfillmentLog $log) use ($fulfillments, $names, $requests) {
            $meta = $log->meta ?? [];
            // Record acuan bisa saja sudah hilang; lognya tetap ditampilkan
            // karena log bersifat append-only.
            $item = $fulfillments->get($log->reference_id);
            $type = $item?->item_type;

            $baris = [
                'log_id' => $log->id,
                'fulfillment_id' => $log->reference_id,
                'site_id' => $item?->site_id ?? $log->site_id,
                'item_type_id' => $type ? (self::TYPE_IDS[$type] ?? null) : null,
                'item_type' => $type,
                'item_id' => $item?->item_id,
                'nama' => $type ? ($names[$type][$item->item_id] ?? null) : null,
                'qty_diminta' => $item?->qty_diminta,
                'qty_request' => $item?->qty_request,
                'qty_terpenuhi' => $item?->qty_terpenuhi,
                'status' => $item?->status,
                'aksi' => $log->aksi,
                'catatan' => $log->catatan,
                'created_at' => $log->created_at,
            ];

            // Angka yang ditanyakan beda per aksi: batch pengiriman menjawab
            // "dikirim berapa", batch penerimaan menjawab "diterima berapa,
            // kurang berapa".
            if ($log->aksi === PksFulfillmentLog::AKSI_RECEIVE) {
                return $baris + [
                    'qty_diterima' => $meta['qty_diterima'] ?? 0,
                    'qty_request_ditutup' => $meta['qty_request_ditutup'] ?? null,
                    'kurang' => $meta['kurang'] ?? null,
                    'request_ids' => $meta['request_ids'] ?? [],
                ];
            }

            // Batch pengiriman harus bisa menjawab "sudah diterima belum",
            // kalau tidak pembacanya menyimpulkan barang masih menggantung
            // hanya karena aksi log-nya selamanya 'request'.
            //
            // Disarangkan, bukan didatarkan: qty_diterima dan kurang sudah
            // dipakai baris batch penerimaan di atas dengan cakupan berbeda
            // (di sana "diterima di sesi ini", di sini "diterima atas kiriman
            // ini"). Satu kunci dua arti adalah kekeliruan yang menunggu
            // terjadi.
            $request = $this->requestOf($log, $requests);

            return $baris + [
                'qty_dikirim' => $meta['qty_sesi_ini'] ?? 0,
                // remaining_* hanya ada di log lama (alur satu tahap);
                // boleh_direquest_* dipakai log request sejak alur dua tahap.
                'remaining_sebelum' => $meta['remaining_sebelum'] ?? null,
                'remaining_sesudah' => $meta['remaining_sesudah'] ?? null,
                'boleh_direquest_sebelum' => $meta['boleh_direquest_sebelum'] ?? null,
                'boleh_direquest_sesudah' => $meta['boleh_direquest_sesudah'] ?? null,
                // null untuk log lama yang tidak punya meta.request_id —
                // "tautan tidak tersedia", bukan "belum diterima".
                'penerimaan' => $request === null ? null : [
                    'request_id' => $request->id,
                    'status' => $request->status,
                    'qty_diterima' => (int) $request->qty_diterima,
                    'kurang' => (int) $request->kurang,
                    'received_at' => $request->received_at,
                    'batch_id' => $request->received_batch_id,
                    'batch_ke' => $request->received_batch_ke,
                ],
            ];
        })->all();
    }

    /**
     * Baris permintaan barang yang dirujuk log pengiriman, satu query untuk
     * seluruh batch (bukan per baris).
     *
     * @param  Collection<int, PksFulfillmentLog>  $logs
     * @return \Illuminate\Support\Collection<int, PksItemRequest>
     */
    private function requestRows(Collection $logs): Collection
    {
        $ids = $logs
            ->map(fn (PksFulfillmentLog $log) => ($log->meta ?? [])['request_id'] ?? null)
            ->filter()
            ->unique()
            ->all();

        if (empty($ids)) {
            return collect();
        }

        return PksItemRequest::whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Rekap penerimaan satu batch pengiriman, supaya pembacanya tidak perlu
     * menghitung sendiri dari daftar item.
     *
     * status_penerimaan null berarti pertanyaannya tidak relevan (batch bukan
     * pengiriman) atau tidak terjawab sama sekali (seluruh lognya lama, tanpa
     * tautan ke baris permintaan).
     *
     * 'selesai' dan 'selesai_kurang' dipisah karena keduanya sama-sama berarti
     * "tidak ada lagi yang ditunggu", tapi yang kedua barangnya kurang.
     * Menyatukannya membuat batch yang cuma diterima separuh terbaca beres.
     *
     * jumlah_tanpa_tautan menutup selisih: diterima + menunggu + tanpa_tautan
     * selalu sama dengan jumlah_item, jadi pembaca tidak menyimpulkan ada item
     * yang hilang saat batchnya bercampur dengan log lama.
     *
     * @param  Collection<int, PksFulfillmentLog>  $logs
     * @param  Collection<int, PksItemRequest>  $requests
     * @return array{status_penerimaan: string|null, jumlah_diterima: int, jumlah_menunggu: int, jumlah_kurang: int, jumlah_tanpa_tautan: int, qty_kurang: int}
     */
    private function rekapPenerimaan(Collection $logs, Collection $requests): array
    {
        $tanpaTautan = 0;
        $menunggu = 0;
        $kurang = 0;
        $qtyKurang = 0;
        $tertaut = 0;

        foreach ($logs as $log) {
            $request = $this->requestOf($log, $requests);

            if ($request === null) {
                $tanpaTautan++;

                continue;
            }

            $tertaut++;

            if ($request->status === PksItemRequest::STATUS_OPEN) {
                $menunggu++;

                continue;
            }

            if ($request->status === PksItemRequest::STATUS_SHORT) {
                $kurang++;
                $qtyKurang += (int) $request->kurang;
            }
        }

        if ($tertaut === 0) {
            return ['jumlah_tanpa_tautan' => $tanpaTautan] + self::REKAP_KOSONG;
        }

        $diterima = $tertaut - $menunggu;

        return [
            'status_penerimaan' => match (true) {
                $diterima === 0 => 'belum',
                $menunggu > 0 => 'sebagian',
                $kurang > 0 => 'selesai_kurang',
                default => 'selesai',
            },
            'jumlah_diterima' => $diterima,
            'jumlah_menunggu' => $menunggu,
            'jumlah_kurang' => $kurang,
            'jumlah_tanpa_tautan' => $tanpaTautan,
            'qty_kurang' => $qtyKurang,
        ];
    }

    /**
     * Apakah kelompok log ini batch pengiriman barang — satu-satunya batch yang
     * punya status penerimaan. Batch campuran ('mixed') sengaja tidak dihitung:
     * artinya tidak jelas, jadi lebih baik tidak menjawab.
     *
     * @param  Collection<int, string>  $aksi  nilai aksi unik dalam kelompok
     */
    private function batchPengiriman(?string $jenis, Collection $aksi): bool
    {
        return $jenis === PksFulfillmentLog::JENIS_ITEM
            && $aksi->count() === 1
            && $aksi->first() === PksFulfillmentLog::AKSI_REQUEST;
    }

    /**
     * Baris permintaan yang dirujuk satu log pengiriman, bila tautannya ada.
     *
     * @param  Collection<int, PksItemRequest>  $requests
     */
    private function requestOf(PksFulfillmentLog $log, Collection $requests): ?PksItemRequest
    {
        $id = ($log->meta ?? [])['request_id'] ?? null;

        return $id === null ? null : ($requests[$id] ?? null);
    }

    /**
     * Nama barang per tipe — satu query per tipe, bukan per item.
     *
     * Menerima koleksi apa pun yang punya atribut item_type + item_id, jadi
     * bisa dipakai baris fulfillment maupun baris permintaan barang.
     *
     * @param  Collection<int, object>  $items
     * @return array<string, array<int, string>>
     */
    public static function itemNames(Collection $items): array
    {
        $names = [];

        foreach ($items->groupBy('item_type') as $type => $rows) {
            $model = match ($type) {
                'kaporlap' => QuotationKaporlap::query(),
                'device' => QuotationDevices::query(),
                'chemical' => QuotationChemical::query(),
                default => null,
            };

            if ($model === null) {
                continue;
            }

            $names[$type] = $model
                ->whereIn('id', $rows->pluck('item_id')->unique()->all())
                ->pluck('nama', 'id')
                ->all();
        }

        return $names;
    }

    /**
     * Baris batch jenis visit: log => record visit.
     *
     * @param  Collection<int, PksFulfillmentLog>  $logs
     * @return array<int, array<string, mixed>>
     */
    private function visitItems(Collection $logs): array
    {
        $records = PksVisitRecord::whereIn('id', $logs->pluck('reference_id')->unique()->all())
            ->select('id', 'site_id', 'schedule_id', 'role', 'tgl_visit_aktual', 'hasil_visit')
            ->get()
            ->keyBy('id');

        return $logs->map(function (PksFulfillmentLog $log) use ($records) {
            $meta = $log->meta ?? [];
            $record = $records->get($log->reference_id);

            return [
                'log_id' => $log->id,
                'visit_record_id' => $log->reference_id,
                'site_id' => $record?->site_id ?? $log->site_id,
                'schedule_id' => $record?->schedule_id ?? ($meta['schedule_id'] ?? null),
                'role' => $record?->role ?? ($meta['role'] ?? null),
                'tgl_visit_aktual' => $record?->tgl_visit_aktual ?? ($meta['tgl_visit_aktual'] ?? null),
                'hasil_visit' => $record?->hasil_visit ?? ($meta['hasil_visit'] ?? null),
                'jumlah_foto' => $meta['jumlah_foto'] ?? 0,
                'sisa_target' => $meta['sisa_target'] ?? null,
                'aksi' => $log->aksi,
                'catatan' => $log->catatan,
                'created_at' => $log->created_at,
            ];
        })->all();
    }
}
