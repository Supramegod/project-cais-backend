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
        Schema::table('sl_pks', function (Blueprint $table) {
            $table->unsignedBigInteger('pks_induk_id')->nullable()->after('id');
            $table->string('tipe_pks', 20)->nullable()->after('pks_induk_id');
        });
    }

    public function down(): void
    {
        Schema::table('sl_pks', function (Blueprint $table) {
            $table->dropColumn(['pks_induk_id', 'tipe_pks']);
        });
    }
};
