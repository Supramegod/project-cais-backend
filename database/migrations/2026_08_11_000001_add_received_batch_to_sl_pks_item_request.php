<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
 * Murni skema. Pengisian baris lama ditangani
 * BackfillItemRequestReceivedBatchSeeder, dijalankan terpisah dan hanya saat
 * dibutuhkan — supaya deploy produksi tidak ikut menanggung mutasi data.
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('sl_pks_item_request')) {
            return;
        }

        if (! Schema::hasColumn('sl_pks_item_request', 'received_batch_id')) {
            return;
        }

        // Kolomnya bisa saja pernah ditambahkan manual di DB dev tanpa index —
        // rollback tidak boleh gagal hanya karena itu.
        $adaIndex = Schema::hasIndex('sl_pks_item_request', 'idx_item_request_received_batch');

        Schema::table('sl_pks_item_request', function (Blueprint $table) use ($adaIndex) {
            if ($adaIndex) {
                $table->dropIndex('idx_item_request_received_batch');
            }

            $table->dropColumn(['received_batch_id', 'received_batch_ke']);
        });
    }
};
