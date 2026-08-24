<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi sl_pks_item_request.received_batch_id untuk baris lama yang masih NULL.
 *
 * Baris permintaan barang menyimpan batch PENGIRIMAN pada batch_id, sementara
 * status penerimaannya datang dari batch lain. Sejak migration
 * 2026_08_11_000001 tautan ke batch penerimaan disimpan saat penerimaan dicatat,
 * tapi baris yang sudah ditutup sebelum itu tidak punya tautannya.
 *
 * Sumbernya satu-satunya: sl_pks_fulfillment_log.meta->request_ids milik log
 * beraksi 'receive' — di situlah tercatat baris mana saja yang ditutup oleh
 * batch penerimaan tersebut.
 *
 * Dipisah dari migration karena tidak selalu perlu dijalankan: instalasi baru
 * tidak punya baris lama sama sekali, dan deploy produksi sebaiknya tidak ikut
 * menanggung mutasi data.
 *
 * Aman diulang: hanya menyentuh baris yang received_batch_id-nya masih NULL,
 * jadi tautan yang sudah benar tidak pernah ditimpa. Memakai query builder,
 * bukan Eloquent, supaya updated_at tidak ikut berubah.
 *
 *   php artisan db:seed --class=BackfillItemRequestReceivedBatchSeeder
 */
class BackfillItemRequestReceivedBatchSeeder extends Seeder
{
    /** Log dibaca per potongan supaya PKS dengan riwayat panjang tidak menyedot memori. */
    private const CHUNK = 200;

    public function run(): void
    {
        $terisi = 0;
        $logTanpaTautan = 0;

        DB::table('sl_pks_fulfillment_log')
            ->where('jenis', 'item')
            ->where('aksi', 'receive')
            ->whereNotNull('batch_id')
            ->select('id', 'batch_id', 'batch_ke', 'meta')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($logs) use (&$terisi, &$logTanpaTautan): void {
                foreach ($logs as $log) {
                    $requestIds = $this->requestIds($log->meta);

                    // Log lama dari sebelum request_ids dicatat. Barisnya
                    // dibiarkan NULL — "tautan tidak tersedia", bukan kesalahan.
                    if ($requestIds === []) {
                        $logTanpaTautan++;

                        continue;
                    }

                    $terisi += DB::table('sl_pks_item_request')
                        ->whereIn('id', $requestIds)
                        ->whereNull('received_batch_id')
                        ->update([
                            'received_batch_id' => $log->batch_id,
                            'received_batch_ke' => $log->batch_ke,
                        ]);
                }
            });

        $this->report($terisi, $logTanpaTautan);
    }

    /**
     * Daftar id baris permintaan yang ditutup satu log penerimaan.
     *
     * meta bisa datang sebagai string JSON (query builder) atau array (driver
     * yang sudah men-decode), dan isinya tidak dijamin — log bersifat
     * append-only dan bentuk metanya berubah antar versi.
     *
     * @return array<int, int>
     */
    private function requestIds(mixed $meta): array
    {
        $decoded = is_string($meta) ? json_decode($meta, true) : $meta;

        if (! is_array($decoded) || ! is_array($decoded['request_ids'] ?? null)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn ($id) => is_numeric($id) ? (int) $id : null,
                $decoded['request_ids']
            )
        ));
    }

    private function report(int $terisi, int $logTanpaTautan): void
    {
        $this->command?->info("Backfill selesai: {$terisi} baris permintaan tertaut ke batch penerimaannya.");

        if ($logTanpaTautan > 0) {
            $this->command?->warn(
                "{$logTanpaTautan} log penerimaan dilewati karena tidak menyimpan request_ids ".
                '(log lama). Baris terkait tetap NULL dan tidak bisa ditautkan otomatis.'
            );
        }
    }
}
