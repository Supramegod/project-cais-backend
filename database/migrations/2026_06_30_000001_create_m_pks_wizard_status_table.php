<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('m_pks_wizard_status')) {
            return;
        }

        Schema::create('m_pks_wizard_status', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->unsignedInteger('urutan');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('m_pks_wizard_status')->insert([
            [
                'id' => 1,
                'kode' => 'initialized',
                'nama' => 'Initialized',
                'keterangan' => 'PKS wizard baru dibuat',
                'urutan' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'kode' => 'in_progress',
                'nama' => 'In Progress',
                'keterangan' => 'PKS wizard sedang diisi',
                'urutan' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'kode' => 'ready_to_finalize',
                'nama' => 'Ready To Finalize',
                'keterangan' => 'Semua step wajib selesai',
                'urutan' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'kode' => 'finalized',
                'nama' => 'Finalized',
                'keterangan' => 'PKS sudah final',
                'urutan' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'kode' => 'cancelled',
                'nama' => 'Cancelled',
                'keterangan' => 'PKS wizard dibatalkan',
                'urutan' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('m_pks_wizard_status');
    }
};
