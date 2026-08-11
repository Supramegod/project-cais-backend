<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan balik dari baris permintaan barang ke batch penerimaannya.
 *
 * batch_id pada sl_pks_item_request adalah batch PENGIRIMAN — diisi saat request
 * dibuat. Sementara status/qty_diterima/received_at pada baris yang sama berasal
 * dari tahap PENERIMAAN, yang punya batch sendiri. Akibatnya baris berstatus
 * 'received' tetap menunjuk ke batch request, dan tidak ada jalan dari daftar
 * permintaan menuju batch penerimaan yang menutupnya.
 *
 * Selama ini tautannya hanya satu arah dan terkubur di
 * sl_pks_fulfillment_log.meta->request_ids milik log receive. Dua kolom di sini
 * membuatnya bisa dibaca dua arah.
 *
 * Backfill mengambil tautan lama dari meta tersebut. Baris yang batch
 * penerimaannya tidak bisa ditemukan (log lama tanpa request_ids) sengaja
 * dibiarkan NULL — artinya "tautan tidak tersedia", bukan kesalahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sl_pks_item_request')) {
            return;
        }

        if (! Schema::hasColumn('sl_pks_item_request', 'received_batch_id')) {
            Schema::table('sl_pks_item_request', function (Blueprint $table) {
                $table->uuid('received_batch_id')->nullable()->after('batch_ke');
                $table->unsignedInteger('received_batch_ke')->nullable()->after('received_batch_id');

                $table->index('received_batch_id', 'idx_item_request_received_batch');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (! Schema::hasTable('sl_pks_item_request')) {
            return;
        }

        if (! Schema::hasColumn('sl_pks_item_request', 'received_batch_id')) {
            return;
        }

        Schema::table('sl_pks_item_request', function (Blueprint $table) {
            $table->dropIndex('idx_item_request_received_batch');
            $table->dropColumn(['received_batch_id', 'received_batch_ke']);
        });
    }

    /**
     * Isi tautan dari log penerimaan yang sudah ada.
     *
     * Idempoten: hanya menyentuh baris yang tautannya masih NULL, jadi aman
     * dijalankan ulang dan tidak menimpa data yang sudah benar.
     */
    private function backfill(): void
    {
        if (! Schema::hasTable('sl_pks_fulfillment_log')) {
            return;
        }

        DB::table('sl_pks_fulfillment_log')
            ->where('jenis', 'item')
            ->where('aksi', 'receive')
            ->whereNotNull('batch_id')
            ->select('id', 'batch_id', 'batch_ke', 'meta')
            ->orderBy('id')
            ->chunk(200, function ($logs) {
                foreach ($logs as $log) {
                    $meta = is_string($log->meta) ? json_decode($log->meta, true) : $log->meta;
                    $requestIds = is_array($meta['request_ids'] ?? null) ? $meta['request_ids'] : [];

                    if (empty($requestIds)) {
                        continue;
                    }

                    DB::table('sl_pks_item_request')
                        ->whereIn('id', $requestIds)
                        ->whereNull('received_batch_id')
                        ->update([
                            'received_batch_id' => $log->batch_id,
                            'received_batch_ke' => $log->batch_ke,
                        ]);
                }
            });
    }
};
