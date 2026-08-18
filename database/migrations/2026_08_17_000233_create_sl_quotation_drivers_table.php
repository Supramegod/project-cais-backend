<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sl_quotation_drivers', function (Blueprint $table) {
            $table->id();
            
            // Menggunakan bigint(30) signed agar sama persis dengan master sl_quotation
            $table->bigInteger('quotation_id')->length(30);
            
            // A. Informasi Utama Kendaraan
            $table->string('status_kendaraan')->nullable();
            $table->string('jenis_kendaraan')->nullable();
            $table->string('nama_kendaraan')->nullable();
            $table->string('kepemilikan_sim')->nullable();
            $table->string('asuransi_mobil')->nullable(); // ada/tidak (simpan sebagai string agar sesuai opsi)
            $table->string('gps_map')->nullable(); // ada/tidak
            
            // B. Tipe Layanan & Area Operasional
            $table->string('tipe_layanan_angkut')->nullable();
            $table->string('area_dihandle')->nullable();
            $table->string('kapasitas_bobot_maksimal')->nullable();
            $table->string('asuransi_barang')->nullable(); // ada/tidak
            
            // C. Biaya & Ketentuan Khusus
            $table->decimal('biaya_khusus_kecelakaan', 15, 2)->nullable()->default(0);

            // Audit trails
            $table->string('created_by')->nullable();
            $table->integer('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->integer('updated_by_user_id')->nullable();
            $table->string('deleted_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('quotation_id')
                ->references('id')
                ->on('sl_quotation')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sl_quotation_drivers');
    }
};
