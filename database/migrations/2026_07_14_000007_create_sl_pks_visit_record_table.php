<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sl_pks_visit_record', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->bigInteger('pks_id');
            $table->bigInteger('site_id');
            $table->bigInteger('leads_id');
            $table->enum('role', ['operasional', 'crm']);
            $table->unsignedBigInteger('user_id');
            $table->date('tgl_visit_aktual');
            $table->enum('hasil_visit', ['selesai', 'ada_kendala', 'ditunda']);
            $table->text('catatan');

            // Audit
            $table->string('created_by', 255)->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->string('deleted_by', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('pks_id');
            $table->index('schedule_id');
            $table->index('leads_id');
            $table->index('user_id');
            $table->index('role');
            $table->index(['pks_id', 'role']);

            // Foreign keys
            $table->foreign('schedule_id')->references('id')->on('sl_pks_visit_schedule')->nullOnDelete();
            $table->foreign('pks_id')->references('id')->on('sl_pks')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sl_site')->cascadeOnDelete();
            $table->foreign('leads_id')->references('id')->on('sl_leads')->cascadeOnDelete();
            // user_id is a soft FK to mysqlhris.m_user — no DB-level FK
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_pks_visit_record');
    }
};
