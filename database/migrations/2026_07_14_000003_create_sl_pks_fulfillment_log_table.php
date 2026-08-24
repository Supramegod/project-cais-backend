<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defensif: tabel mungkin sudah dibuat manual di DB dev.
        if (Schema::hasTable('sl_pks_fulfillment_log')) {
            return;
        }

        // Log modul fulfillment, dikelompokkan per PKS. Satu tabel untuk seluruh
        // sub-aktivitas fulfillment (item, visit, dst) — dibedakan lewat `jenis`.
        Schema::create('sl_pks_fulfillment_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pks_id');        // PKS pemilik log
            $table->string('jenis', 32);                 // 'item' | 'visit' | dst
            $table->unsignedBigInteger('reference_id');  // id record di sub-modul
            $table->string('aksi', 32);                  // 'create' | 'edit' | dst
            $table->text('catatan')->nullable();
            $table->json('meta')->nullable();            // payload spesifik sub-modul

            // Audit — append-only: hanya created_at, tanpa updated_at
            $table->string('created_by', 255)->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            // Log per PKS + lookup per record sub-modul. site_id ditambah
            // di migration terpisah (2026_07_22_000002) — lihat catatan di sana.
            $table->index('pks_id');
            $table->index(['jenis', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_pks_fulfillment_log');
    }
};
