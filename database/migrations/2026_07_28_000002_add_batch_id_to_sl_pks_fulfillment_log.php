<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * batch_id menandai satu kelompok pengiriman fulfillment.
 *
 * Endpoint bulk mengirim banyak item sekaligus dalam satu transaksi, dan
 * sebelumnya tiap item jadi baris log yang berdiri sendiri — tidak ada cara
 * tahu item mana saja yang datang bersamaan. Satu UUID per panggilan bulk
 * menyatukan mereka. Pengiriman single tetap NULL karena memang bukan batch.
 *
 * Index pks_id + created_at ditambah supaya log satu PKS bisa ditarik urut
 * waktu tanpa filesort, termasuk saat batch ditelusuri lewat rentang waktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sl_pks_fulfillment_log')) {
            return;
        }

        if (! Schema::hasColumn('sl_pks_fulfillment_log', 'batch_id')) {
            Schema::table('sl_pks_fulfillment_log', function (Blueprint $table) {
                $table->uuid('batch_id')->nullable()->after('reference_id');
                $table->index('batch_id');
                $table->index(['pks_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sl_pks_fulfillment_log')) {
            return;
        }

        if (Schema::hasColumn('sl_pks_fulfillment_log', 'batch_id')) {
            Schema::table('sl_pks_fulfillment_log', function (Blueprint $table) {
                $table->dropIndex(['batch_id']);
                $table->dropIndex(['pks_id', 'created_at']);
                $table->dropColumn('batch_id');
            });
        }
    }
};
