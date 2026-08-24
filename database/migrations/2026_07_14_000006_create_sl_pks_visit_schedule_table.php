<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sl_pks_visit_schedule')) {
            return;
        }

        Schema::create('sl_pks_visit_schedule', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pks_id');
            $table->bigInteger('site_id');
            $table->bigInteger('leads_id');
            $table->enum('role', ['operasional', 'crm']);
            $table->unsignedBigInteger('pic_user_id');
            $table->date('tgl_jadwal');
            $table->date('tgl_jadwal_asli')->nullable();
            $table->text('alasan_reschedule')->nullable();
            $table->unsignedBigInteger('direschedule_oleh')->nullable();
            $table->enum('status', ['scheduled', 'rescheduled', 'done', 'missed'])->default('scheduled');

            // Audit
            $table->string('created_by', 255)->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by', 255)->nullable();

            $table->timestamps();

            // Indexes
            $table->index('pks_id');
            $table->index('site_id');
            $table->index('leads_id');
            $table->index('pic_user_id');
            $table->index('tgl_jadwal');
            $table->index('status');
            $table->index(['pks_id', 'tgl_jadwal']);

            // Foreign keys
            $table->foreign('pks_id')->references('id')->on('sl_pks')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sl_site')->cascadeOnDelete();
            $table->foreign('leads_id')->references('id')->on('sl_leads')->cascadeOnDelete();
            // pic_user_id is a soft FK to mysqlhris.m_user — no DB-level FK
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_pks_visit_schedule');
    }
};
