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
            $table->string('hari_off')->nullable()->after('hari_kerja');
            $table->string('jam_lembur')->nullable()->after('jam_kerja');
            $table->string('cuti_izin')->nullable()->after('cuti');
            $table->string('status_rekrutmen')->nullable()->after('cuti_izin');
            $table->string('pendaftaran_pkwt')->nullable()->after('status_rekrutmen');
            $table->string('jaminan')->nullable()->after('pendaftaran_pkwt');
            $table->string('penanggung_jawab_aset')->nullable()->after('jaminan');
            $table->text('detail_penanggung_jawab_aset')->nullable()->after('penanggung_jawab_aset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sl_quotation', function (Blueprint $table) {
            $table->dropColumn([
                'hari_off',
                'jam_lembur',
                'cuti_izin',
                'status_rekrutmen',
                'pendaftaran_pkwt',
                'jaminan',
                'penanggung_jawab_aset',
                'detail_penanggung_jawab_aset'
            ]);
        });
    }
};
