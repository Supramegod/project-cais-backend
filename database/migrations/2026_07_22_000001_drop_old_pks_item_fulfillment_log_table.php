<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buang tabel log lama. Log fulfillment sekarang gabung di
     * sl_pks_fulfillment_log (item + visit, dibedakan lewat kolom `jenis`).
     */
    public function up(): void
    {
        Schema::dropIfExists('sl_pks_item_fulfillment_log');

        // Kolom catatan sempat direncanakan di row lalu direvert (catatan
        // pindah ke log). Buang bila terlanjur ada di DB dev.
        if (Schema::hasColumn('sl_pks_item_fulfillment', 'catatan')) {
            Schema::table('sl_pks_item_fulfillment', function (Blueprint $table) {
                $table->dropColumn('catatan');
            });
        }
    }

    /**
     * Tidak dibuat ulang — data lama tidak dipulihkan. Skema log ada di
     * migration create sl_pks_fulfillment_log.
     */
    public function down(): void
    {
        // no-op
    }
};
