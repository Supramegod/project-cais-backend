<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cek apakah tabel sudah ada di database
        if (!Schema::hasTable('sl_pks_visit_target')) {
            Schema::create('sl_pks_visit_target', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('pks_id');
                $table->enum('role', ['operasional', 'crm']);
                $table->bigInteger('kategori_sesuai_hc_id');
                $table->unsignedInteger('target_total')->default(0);
                $table->unsignedInteger('target_terpakai')->default(0);

                // Audit
                $table->string('created_by', 255)->nullable();
                $table->unsignedInteger('created_by_user_id')->nullable();
                $table->string('updated_by', 255)->nullable();

                $table->timestamps();

                // Constraints
                $table->unique(['pks_id', 'role'], 'uq_pks_visit_target_role');

                // Foreign keys
                $table->foreign('pks_id')->references('id')->on('sl_pks')->cascadeOnDelete();
                $table->foreign('kategori_sesuai_hc_id')->references('id')->on('m_kategori_sesuai_hc')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_pks_visit_target');
    }
};