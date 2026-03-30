<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daftar tabel yang butuh index di quotation_id
        $tables = [
            'sl_quotation_aplikasi', 'sl_quotation_chemical', 'sl_quotation_detail',
            'sl_quotation_detail_coss', 'sl_quotation_detail_requirement', 
            'sl_quotation_detail_tunjangan', 'sl_quotation_devices', 
            'sl_quotation_kaporlap', 'sl_quotation_kerjasama', 'sl_quotation_ohc', 
            'sl_quotation_pic', 'sl_quotation_site', 'sl_quotation_training'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index('quotation_id');
            });
        }

        // Index tambahan untuk relasi antar anak tabel (sangat penting!)
        Schema::table('sl_quotation_detail', function (Blueprint $table) {
            $table->index('quotation_site_id');
        });
        
        Schema::table('sl_quotation_chemical', function (Blueprint $table) {
            $table->index('quotation_site_id');
        });
        
        Schema::table('sl_quotation_devices', function (Blueprint $table) {
            $table->index('quotation_site_id');
        });
        Schema::table('sl_quotation_ohc', function (Blueprint $table) {
            $table->index('quotation_site_id');
        });
        Schema::table('sl_quotation_kaporlap', function (Blueprint $table) {
            $table->index('quotation_detail_id');
        });
    }

    public function down(): void
    {
        // Opsional: Untuk menghapus index jika di-rollback
        $tables = [
            'sl_quotation_aplikasi', 'sl_quotation_chemical', 'sl_quotation_detail',
            'sl_quotation_detail_coss', 'sl_quotation_detail_requirement', 
            'sl_quotation_detail_tunjangan', 'sl_quotation_devices', 
            'sl_quotation_kaporlap', 'sl_quotation_kerjasama', 'sl_quotation_ohc', 
            'sl_quotation_pic', 'sl_quotation_site', 'sl_quotation_training'
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['quotation_id']);
            });
        }
    }
};
