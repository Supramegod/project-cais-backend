<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defensif: kolom mungkin sudah dibuat manual di DB dev.
        if (Schema::hasTable('sl_site') && ! Schema::hasColumn('sl_site', 'is_visit_anchor')) {
            Schema::table('sl_site', function (Blueprint $table) {
                $table->boolean('is_visit_anchor')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sl_site') && Schema::hasColumn('sl_site', 'is_visit_anchor')) {
            Schema::table('sl_site', function (Blueprint $table) {
                $table->dropColumn('is_visit_anchor');
            });
        }
    }
};
