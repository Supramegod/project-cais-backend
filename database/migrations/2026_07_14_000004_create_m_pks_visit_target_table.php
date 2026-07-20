<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengecekan agar tidak error jika tabel sudah ada di DB
        if (!Schema::hasTable('m_pks_visit_target')) {
            Schema::create('m_pks_visit_target', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('kebutuhan_id');
                $table->unsignedInteger('hc_min');
                $table->unsignedInteger('hc_max');
                $table->bigInteger('kategori_sesuai_hc_id');
                $table->unsignedInteger('target_visit_per_tahun');

                // Audit
                $table->string('created_by', 255)->nullable();
                $table->unsignedInteger('created_by_user_id')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->string('deleted_by', 255)->nullable();

                $table->softDeletes();
                $table->timestamps();

                // Indexes
                $table->unique(['kebutuhan_id', 'hc_min', 'hc_max'], 'uq_m_pks_visit_target');
                $table->index('kebutuhan_id');
                $table->index('kategori_sesuai_hc_id');

                // Foreign keys
                $table->foreign('kebutuhan_id')->references('id')->on('m_kebutuhan')->cascadeOnDelete();
                $table->foreign('kategori_sesuai_hc_id')->references('id')->on('m_kategori_sesuai_hc')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('m_pks_visit_target');
    }
};