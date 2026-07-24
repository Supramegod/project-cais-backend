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
        Schema::table('sl_quotation_site', function (Blueprint $table) {
            if (! Schema::hasColumn('sl_quotation_site', 'umsk')) {
                $table->decimal('umsk', 15, 2)->nullable()->after('umk');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sl_quotation_site', function (Blueprint $table) {
            //
        });
    }
};
