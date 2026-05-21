<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePksPerjanjianHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('sl_pks_perjanjian_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pks_perjanjian_id');
            $table->unsignedBigInteger('pks_id');
            $table->string('pasal');
            $table->string('judul');
            $table->longText('raw_text');
            $table->longText('snapshot'); // menyimpan JSON seluruh data lama
            $table->string('changed_by');
            $table->timestamps();

            // $table->foreign('pks_perjanjian_id')->references('id')->on('sl_pks_perjanjian')->onDelete('cascade');
            // $table->foreign('pks_id')->references('id')->on('sl_pks')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sl_pks_perjanjian_history');
    }
}