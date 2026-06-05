<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sl_quotation_management_fee', function (Blueprint $table) {
            $table->id();
            
            // Menggunakan bigint(30) signed agar sama persis dengan master sl_quotation
            $table->bigInteger('quotation_id')->length(30)->unique();

            // ── Gaji Pokok selalu masuk (tidak ada flag) ────────────────────────
            // Flag true = komponen ini IKUT dihitung dalam basis management fee
            $table->boolean('is_thr')->default(true)->comment('Tunjangan Hari Raya');
            $table->boolean('is_kompensasi')->default(true)->comment('Kompensasi PKWT');
            $table->boolean('is_thl')->default(true)->comment('Tunjangan Hari Libur Nasional');
            $table->boolean('is_lembur')->default(true)->comment('Lembur (flat)');
            $table->boolean('is_bpjs_kes')->default(true)->comment('BPJS Kesehatan');
            $table->boolean('is_bpjs_tk')->default(true)->comment('BPJS Ketenagakerjaan (JKK+JKM+JHT+JP)');
            $table->boolean('is_chemical')->default(true)->comment('Biaya Chemical');
            $table->boolean('is_kaporlap')->default(true)->comment('Kaporlap / Seragam');
            $table->boolean('is_device')->default(true)->comment('Device / Peralatan');
            $table->boolean('is_ohc')->default(true)->comment('OHC (Occupational Health Center)');

            $table->timestamps();
            $table->softDeletes();

            // Deklarasi foreign key manual
            $table->foreign('quotation_id')
                ->references('id')
                ->on('sl_quotation')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_quotation_management_fee');
    }
};