<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel yang butuh index di quotation_id
        $tables = [
            'sl_quotation_aplikasi', 'sl_quotation_chemical', 'sl_quotation_detail',
            'sl_quotation_detail_coss', 'sl_quotation_detail_requirement',
            'sl_quotation_detail_tunjangan', 'sl_quotation_devices',
            'sl_quotation_kaporlap', 'sl_quotation_kerjasama', 'sl_quotation_ohc',
            'sl_quotation_pic', 'sl_quotation_site', 'sl_quotation_training',
        ];

        foreach ($tables as $tableName) {
            $this->addIndexIfMissing($tableName, 'quotation_id');
        }

        // Index tambahan untuk relasi antar anak tabel
        $this->addIndexIfMissing('sl_quotation_detail', 'quotation_site_id');
        $this->addIndexIfMissing('sl_quotation_chemical', 'quotation_site_id');
        $this->addIndexIfMissing('sl_quotation_devices', 'quotation_site_id');
        $this->addIndexIfMissing('sl_quotation_ohc', 'quotation_site_id');
        $this->addIndexIfMissing('sl_quotation_kaporlap', 'quotation_detail_id');
    }

    public function down(): void
    {
        $tables = [
            'sl_quotation_aplikasi', 'sl_quotation_chemical', 'sl_quotation_detail',
            'sl_quotation_detail_coss', 'sl_quotation_detail_requirement',
            'sl_quotation_detail_tunjangan', 'sl_quotation_devices',
            'sl_quotation_kaporlap', 'sl_quotation_kerjasama', 'sl_quotation_ohc',
            'sl_quotation_pic', 'sl_quotation_site', 'sl_quotation_training',
        ];

        foreach ($tables as $tableName) {
            $this->dropIndexIfExists($tableName, 'quotation_id');
        }
    }

    /**
     * Tambah index hanya bila belum ada — bikin migration idempotent, aman
     * dijalankan di DB yang index-nya sudah terpasang (hindari error 1061).
     */
    private function addIndexIfMissing(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $index = "{$table}_{$column}_index";
        if (Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->index($column);
        });
    }

    private function dropIndexIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $index = "{$table}_{$column}_index";
        if (! Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($index) {
            $t->dropIndex($index);
        });
    }
};
