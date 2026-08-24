<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sysmenu_role')) {
            return;
        }

        if (! Schema::hasColumn('sysmenu_role', 'user_id')) {
            Schema::table('sysmenu_role', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('role_id');
                $table->index(['role_id', 'user_id', 'sysmenu_id'], 'sysmenurole_role_user_menu');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sysmenu_role')) {
            return;
        }

        if (Schema::hasColumn('sysmenu_role', 'user_id')) {
            Schema::table('sysmenu_role', function (Blueprint $table) {
                $table->dropIndex('sysmenurole_role_user_menu');
                $table->dropColumn('user_id');
            });
        }
    }
};
