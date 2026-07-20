<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sl_pks_visit_record_foto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visit_record_id');
            $table->string('url_file', 500);
            $table->string('nama_file', 255);
            $table->string('created_by', 255)->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            // Index
            $table->index('visit_record_id');

            // Foreign key
            $table->foreign('visit_record_id')->references('id')->on('sl_pks_visit_record')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_pks_visit_record_foto');
    }
};
