<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_add_unique_to_hpp_coss.php
    public function up()
    {
        Schema::table('sl_quotation_detail_hpp', function (Blueprint $table) {
            $table->unique('quotation_detail_id', 'uq_hpp_detail_id');
        });

        Schema::table('sl_quotation_detail_coss', function (Blueprint $table) {
            $table->unique('quotation_detail_id', 'uq_coss_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hpp_coss', function (Blueprint $table) {
            //
        });
    }
};
