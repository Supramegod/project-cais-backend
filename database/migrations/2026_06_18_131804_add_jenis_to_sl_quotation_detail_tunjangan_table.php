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
        Schema::table('sl_quotation_detail_tunjangan', function (Blueprint $table) {
            if (! Schema::hasColumn('sl_quotation_detail_tunjangan', 'jenis')) {
                $table->string('jenis', 50)->default('Nominal')->after('nominal_coss');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sl_quotation_detail_tunjangan', function (Blueprint $table) {
            $table->dropColumn('jenis');
        });
    }
};
