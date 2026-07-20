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
        if (Schema::hasTable('sl_pks') && ! Schema::hasColumn('sl_pks', 'item_fulfillment_reminded_at')) {
            Schema::table('sl_pks', function (Blueprint $table) {
                $table->timestamp('item_fulfillment_reminded_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sl_pks') && Schema::hasColumn('sl_pks', 'item_fulfillment_reminded_at')) {
            Schema::table('sl_pks', function (Blueprint $table) {
                $table->dropColumn('item_fulfillment_reminded_at');
            });
        }
    }
};
