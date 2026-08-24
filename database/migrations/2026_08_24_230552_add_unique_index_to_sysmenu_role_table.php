<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris kembar untuk kombinasi yang sama menggandakan menu di response
 * (leftJoin ke `sysmenu_role`) dan membuat "baris mana yang menang" jadi
 * tidak deterministik. Unique index biasa tidak cukup karena MySQL
 * mengizinkan banyak NULL, jadi user_id dinormalkan ke kolom generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sysmenu_role', function (Blueprint $table) {
            $table->unsignedBigInteger('user_key')->storedAs('COALESCE(user_id, 0)')->after('user_id');
            $table->unique(['role_id', 'user_key', 'sysmenu_id'], 'sysmenu_role_role_user_menu_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sysmenu_role', function (Blueprint $table) {
            $table->dropUnique('sysmenu_role_role_user_menu_unique');
            $table->dropColumn('user_key');
        });
    }
};
