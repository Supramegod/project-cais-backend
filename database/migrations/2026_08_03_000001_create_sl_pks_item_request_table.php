<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris permintaan barang per item per batch — tahap pertama dari alur dua tahap
 * (request barang, lalu penerimaan barang di site).
 *
 * sl_pks_item_fulfillment hanya punya satu baris per item seumur PKS (unique
 * pks+site+item_type+item_id), jadi tidak bisa menampung banyak pengiriman untuk
 * item yang sama. Tabel ini yang menyimpan "batch A kirim 5, batch B kirim 3",
 * beserta status penerimaannya:
 *
 *  - open     : sudah dikirim, belum dikonfirmasi diterima
 *  - received : diterima persis sebanyak yang dikirim
 *  - short    : diterima kurang (barang kurang/rusak). Selisihnya tidak
 *               menggantung — langsung kembali jadi kekurangan yang boleh
 *               di-request lagi di batch berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sl_pks_item_request')) {
            return;
        }

        Schema::create('sl_pks_item_request', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pks_id');
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('fulfillment_id');

            // Penanda batch request — sama dengan batch_id/batch_ke pada log.
            $table->uuid('batch_id');
            $table->unsignedInteger('batch_ke')->nullable();

            // Disalin dari baris fulfillment supaya daftar request bisa dibaca
            // tanpa join.
            $table->string('item_type', 32);
            $table->unsignedBigInteger('item_id');

            $table->unsignedInteger('qty_request');
            $table->unsignedInteger('qty_diterima')->default(0);
            $table->string('status', 32)->default('open');
            $table->timestamp('received_at')->nullable();

            $table->string('created_by', 255)->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->timestamps();

            // Penerimaan mencari baris open milik satu item, urut FIFO.
            $table->index(['fulfillment_id', 'status'], 'idx_item_request_fulfillment_status');
            $table->index('batch_id', 'idx_item_request_batch');
            $table->index(['pks_id', 'site_id'], 'idx_item_request_pks_site');

            $table->foreign('fulfillment_id')
                ->references('id')->on('sl_pks_item_fulfillment')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_pks_item_request');
    }
};
