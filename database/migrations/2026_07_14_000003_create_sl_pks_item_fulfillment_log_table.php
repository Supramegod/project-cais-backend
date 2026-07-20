<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defensif: tabel mungkin sudah dibuat manual di DB dev.
        if (Schema::hasTable('sl_pks_item_fulfillment_log')) {
            return;
        }

        Schema::create('sl_pks_item_fulfillment_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fulfillment_id');
            $table->enum('aksi', ['create', 'edit']);
            $table->integer('qty_sesi_ini'); // boleh negatif (koreksi edit)
            $table->integer('remaining_sebelum');
            $table->integer('remaining_sesudah');
            $table->text('catatan')->nullable();

            // Audit — append-only: hanya created_at, tanpa updated_at
            $table->string('created_by', 255)->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            // Index + foreign key
            $table->index('fulfillment_id');
            $table->foreign('fulfillment_id')
                ->references('id')->on('sl_pks_item_fulfillment')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_pks_item_fulfillment_log');
    }
};
