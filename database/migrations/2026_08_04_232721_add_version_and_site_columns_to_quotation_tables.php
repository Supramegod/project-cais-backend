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
        Schema::table('sl_quotation', function (Blueprint $table) {
            if (!Schema::hasColumn('sl_quotation', 'version')) {
                $table->integer('version')->default(1)->after('id');
            }
        });

        Schema::table('sl_quotation_site', function (Blueprint $table) {
            $table->string('nama_perusahaan')->nullable();
            $table->string('cabang')->nullable();
            $table->string('jenis_perusahaan')->nullable();
            $table->integer('jenis_perusahaan_id')->nullable();
            $table->string('status_gedung')->nullable();
            $table->text('alamat_lengkap')->nullable();
            $table->string('link_maps')->nullable();
            $table->integer('jumlah_lantai')->nullable();
            $table->string('luas_estimasi_area')->nullable();
            $table->string('hari_operasional')->nullable();
            $table->string('pengaturan_shift_kerja')->nullable();
            $table->integer('jumlah_hc')->nullable();
            $table->string('area_khusus')->nullable();
            $table->text('catatan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sl_quotation', function (Blueprint $table) {
            if (Schema::hasColumn('sl_quotation', 'version')) {
                $table->dropColumn('version');
            }
        });

        Schema::table('sl_quotation_site', function (Blueprint $table) {
            $table->dropColumn([
                'nama_perusahaan', 'cabang', 'jenis_perusahaan', 'jenis_perusahaan_id', 'status_gedung',
                'alamat_lengkap', 'link_maps', 'jumlah_lantai', 'luas_estimasi_area',
                'hari_operasional', 'pengaturan_shift_kerja', 'jumlah_hc', 'area_khusus', 'catatan'
            ]);
        });
    }
};
