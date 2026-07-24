<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysqlhris')->hasColumn('m_user', 'remember_token')) {
            return;
        }

        Schema::connection('mysqlhris')->table('m_user', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        if (! Schema::connection('mysqlhris')->hasColumn('m_user', 'remember_token')) {
            return;
        }

        Schema::connection('mysqlhris')->table('m_user', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};