<?php
// database/migrations/2025_01_01_000001_create_m_umsk_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_umsk', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->string('city_name', 255);
            $table->decimal('umsk', 15, 2)->unsigned();
            $table->date('tgl_berlaku');
            $table->string('sumber', 500)->comment('URL sumber data resmi');
            $table->boolean('is_aktif')->default(true)->index();
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['city_id', 'is_aktif'], 'umsk_city_aktif_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('m_umsk');
    }
};