<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection($this->connection)->create('m_umsp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id')->comment('FK referensi ke m_province (mysqlhris)');
            $table->string('province_name', 150)->comment('Snapshot nama provinsi saat data disimpan');
            $table->string('sektor', 100)->comment('Nama sektor industri, e.g. "Tekstil", "Pertambangan"');
            $table->decimal('umsp', 15, 2)->comment('Nilai Upah Minimum Sektoral Provinsi');
            $table->date('tgl_berlaku')->comment('Tanggal mulai berlaku');
            $table->text('sumber')->nullable()->comment('URL / referensi peraturan daerah');
            $table->boolean('is_aktif')->default(true);
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite index: deactivation + read queries
            $table->index(['province_id', 'sektor', 'is_aktif'], 'idx_umsp_province_sektor_aktif');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('m_umsp');
    }
};