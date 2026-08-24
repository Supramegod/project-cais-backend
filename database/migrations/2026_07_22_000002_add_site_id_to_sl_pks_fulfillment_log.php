<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah site_id ke log fulfillment. Terpisah dari migration create karena
     * create di-guard hasTable — kalau tabel sudah ada, create dilewati, jadi
     * kolom baru tidak ikut. Migration ini yang mengisinya di DB existing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sl_pks_fulfillment_log')) {
            return;
        }
        if (Schema::hasColumn('sl_pks_fulfillment_log', 'site_id')) {
            return;
        }

        Schema::table('sl_pks_fulfillment_log', function (Blueprint $table) {
            $table->unsignedBigInteger('site_id')->nullable()->after('pks_id');
            $table->index(['pks_id', 'site_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sl_pks_fulfillment_log', 'site_id')) {
            return;
        }

        Schema::table('sl_pks_fulfillment_log', function (Blueprint $table) {
            $table->dropIndex(['pks_id', 'site_id']);
            $table->dropColumn('site_id');
        });
    }
};
