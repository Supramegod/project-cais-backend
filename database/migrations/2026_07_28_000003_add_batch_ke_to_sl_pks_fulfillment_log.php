<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor urut batch per PKS — "pengiriman batch ke-3", bukan UUID.
 *
 * batch_id sudah cukup untuk menyatukan satu kelompok pengiriman, tapi tidak
 * bisa dibaca orang. batch_ke dihitung per pks_id + jenis: batch item pertama
 * PKS itu = 1, batch visit pertama juga = 1, masing-masing punya urutan sendiri.
 * Index mengikuti cara hitungnya supaya pencarian nomor terakhir tidak memindai
 * seluruh log satu PKS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sl_pks_fulfillment_log')) {
            return;
        }

        if (! Schema::hasColumn('sl_pks_fulfillment_log', 'batch_ke')) {
            Schema::table('sl_pks_fulfillment_log', function (Blueprint $table) {
                $table->unsignedInteger('batch_ke')->nullable()->after('batch_id');
                $table->index(['pks_id', 'jenis', 'batch_ke']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sl_pks_fulfillment_log')) {
            return;
        }

        if (Schema::hasColumn('sl_pks_fulfillment_log', 'batch_ke')) {
            Schema::table('sl_pks_fulfillment_log', function (Blueprint $table) {
                $table->dropIndex(['pks_id', 'jenis', 'batch_ke']);
                $table->dropColumn('batch_ke');
            });
        }
    }
};
