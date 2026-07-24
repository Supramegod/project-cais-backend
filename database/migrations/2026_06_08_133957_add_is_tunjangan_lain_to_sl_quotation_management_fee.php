<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sl_quotation_management_fee', function (Blueprint $table) {
            if (! Schema::hasColumn('sl_quotation_management_fee', 'is_tunjangan_lain')) {
                $table->boolean('is_tunjangan_lain')->default(false)->after('is_ohc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sl_quotation_management_fee', function (Blueprint $table) {
            $table->dropColumn('is_tunjangan_lain');
        });
    }
};
