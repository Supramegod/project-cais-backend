<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE sl_leads ADD FULLTEXT INDEX fulltext_nama_perusahaan (nama_perusahaan)');
        
        Schema::table('sl_leads', function (Blueprint $table) {
            $table->index(['status_leads_id', 'tgl_leads', 'created_at'], 'idx_status_tgl_created');
            $table->index(['branch_id', 'status_leads_id'], 'idx_branch_status');
            $table->index(['platform_id', 'status_leads_id'], 'idx_platform_status');
        });
    }

    public function down()
    {
        DB::statement('ALTER TABLE sl_leads DROP INDEX fulltext_nama_perusahaan');
        
        Schema::table('sl_leads', function (Blueprint $table) {
            $table->dropIndex('idx_status_tgl_created');
            $table->dropIndex('idx_branch_status');
            $table->dropIndex('idx_platform_status');
        });
    }
};
