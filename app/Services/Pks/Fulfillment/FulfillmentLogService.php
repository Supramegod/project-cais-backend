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
     * Log satu PKS, dikelompokkan per batch, terbaru dulu.
     * Filter opsional per jenis (PksFulfillmentLog::JENIS_ITEM / JENIS_VISIT).
     *
     * Log lama (sebelum kolom batch ada) tidak punya batch_id — tiap barisnya
     * jadi grup sendiri supaya tidak ada riwayat yang hilang dari tampilan.
     */
    public function getPksLog(int $pksId, ?string $jenis = null): Collection
    {
        $logs = PksFulfillmentLog::forPks($pksId)
            ->when($jenis !== null, fn ($q) => $q->where('jenis', $jenis))
            ->select('id', 'pks_id', 'site_id', 'jenis', 'reference_id', 'batch_id', 'batch_ke', 'aksi', 'catatan', 'meta', 'created_by', 'created_at')
            ->orderBy('id', 'desc')
            ->get();

        return $logs
            ->groupBy(fn (PksFulfillmentLog $log) => $log->batch_id ?: 'log:'.$log->id)
            ->map(function (Collection $group) {
                $first = $group->first();
                $aksi = $group->pluck('aksi')->unique();

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

        $items = $first->jenis === PksFulfillmentLog::JENIS_VISIT
            ? $this->visitItems($logs)
            : $this->fulfillmentItems($logs);

        // Batch pengiriman punya pertanyaan lanjutan yang tidak terjawab oleh
        // `aksi`: barangnya sudah diterima atau belum. Batch penerimaan dan
        // visit tidak, jadi rekapnya nol/null di sana.
        $rekap = $first->jenis === PksFulfillmentLog::JENIS_ITEM && $first->aksi === PksFulfillmentLog::AKSI_REQUEST
            ? $this->rekapPenerimaan($items)
            : ['status_penerimaan' => null, 'jumlah_diterima' => 0, 'jumlah_menunggu' => 0];

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
     * @return array<int, array<string, mixed>>
     */
    private function fulfillmentItems(Collection $logs): array
    {
        $fulfillments = PksItemFulfillment::withTrashed()
            ->whereIn('id', $logs->pluck('reference_id')->unique()->all())
            ->select('id', 'site_id', 'item_type', 'item_id', 'qty_diminta', 'qty_request', 'qty_terpenuhi', 'status')
            ->get()
            ->keyBy('id');

        $names = self::itemNames($fulfillments);
        $requests = $this->requestRows($logs);

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
            $request = isset($meta['request_id']) ? ($requests[$meta['request_id']] ?? null) : null;

            return $baris + [
                'qty_dikirim' => $meta['qty_sesi_ini'] ?? 0,
                // remaining_* hanya ada di log lama (alur satu tahap);
                // boleh_direquest_* dipakai log request sejak alur dua tahap.
                'remaining_sebelum' => $meta['remaining_sebelum'] ?? null,
                'remaining_sesudah' => $meta['remaining_sesudah'] ?? null,
                'boleh_direquest_sebelum' => $meta['boleh_direquest_sebelum'] ?? null,
                'boleh_direquest_sesudah' => $meta['boleh_direquest_sesudah'] ?? null,
                // Seluruhnya null untuk log lama yang tidak punya meta.request_id
                // — "tautan tidak tersedia", bukan "belum diterima".
                'request_id' => $meta['request_id'] ?? null,
                'status_penerimaan' => $request?->status,
                'qty_diterima' => $request ? (int) $request->qty_diterima : null,
                'kurang' => $request?->kurang,
                'received_at' => $request?->received_at,
                'received_batch_id' => $request?->received_batch_id,
                'received_batch_ke' => $request?->received_batch_ke,
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
     * null berarti pertanyaannya tidak relevan (batch bukan pengiriman) atau
     * tidak terjawab (log lama tanpa tautan ke baris permintaan).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{status_penerimaan: string|null, jumlah_diterima: int, jumlah_menunggu: int}
     */
    private function rekapPenerimaan(array $items): array
    {
        $status = array_values(array_filter(
            array_map(fn (array $item) => $item['status_penerimaan'] ?? null, $items)
        ));

        if (empty($status)) {
            return ['status_penerimaan' => null, 'jumlah_diterima' => 0, 'jumlah_menunggu' => 0];
        }

        $menunggu = count(array_filter($status, fn (string $s) => $s === PksItemRequest::STATUS_OPEN));
        $diterima = count($status) - $menunggu;

        return [
            'status_penerimaan' => match (true) {
                $menunggu === 0 => 'selesai',
                $diterima === 0 => 'belum',
                default => 'sebagian',
            },
            'jumlah_diterima' => $diterima,
            'jumlah_menunggu' => $menunggu,
        ];
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
