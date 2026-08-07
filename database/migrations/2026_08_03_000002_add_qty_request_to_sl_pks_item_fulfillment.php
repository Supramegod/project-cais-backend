<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur item fulfillment dipecah dua tahap: request barang lalu penerimaan barang.
 *
 *  qty_diminta   = kebutuhan dari quotation (tidak berubah)
 *  qty_request   = sedang berjalan — sudah dikirim, belum dikonfirmasi diterima
 *  qty_terpenuhi = sudah diterima site (dulu naik saat kirim, sekarang saat terima)
 *
 * qty_request bukan akumulasi seumur hidup: naik saat request, turun saat baris
 * request-nya ditutup oleh penerimaan.
 *
 * Kolom status dilonggarkan dari enum menjadi string supaya muat nilai baru
 * 'requested' (barang sudah dikirim, belum diterima) tanpa perlu ALTER enum tiap
 * kali ada status baru.
 *
 * Tidak ada backfill: baris lama mengisi qty_terpenuhi lewat alur satu tahap dan
 * itu memang sudah berarti "diterima"; qty_request-nya mulai dari 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sl_pks_item_fulfillment')) {
            return;
        }

        if (! Schema::hasColumn('sl_pks_item_fulfillment', 'qty_request')) {
            Schema::table('sl_pks_item_fulfillment', function (Blueprint $table) {
                $table->unsignedInteger('qty_request')->default(0)->after('qty_diminta');
            });
        }

        Schema::table('sl_pks_item_fulfillment', function (Blueprint $table) {
            $table->string('status', 32)->default('not_yet_fulfilled')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sl_pks_item_fulfillment')) {
            return;
        }

        // Nilai baru harus dinormalkan dulu, kalau tidak enum menolaknya.
        DB::table('sl_pks_item_fulfillment')
            ->where('status', 'requested')
            ->update(['status' => 'not_yet_fulfilled']);

        Schema::table('sl_pks_item_fulfillment', function (Blueprint $table) {
            $table->enum('status', ['not_yet_fulfilled', 'partially_fulfilled', 'fully_fulfilled'])
                ->default('not_yet_fulfilled')
                ->change();
        });

        if (Schema::hasColumn('sl_pks_item_fulfillment', 'qty_request')) {
            Schema::table('sl_pks_item_fulfillment', function (Blueprint $table) {
                $table->dropColumn('qty_request');
            });
        }
    }
};
