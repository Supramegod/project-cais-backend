<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('mysqlhris')->table('m_user', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down()
    {
        Schema::connection('mysqlhris')->table('m_user', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};