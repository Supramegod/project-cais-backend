<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection($this->connection)->table('m_umsk', function (Blueprint $table) {
            // Add sektor column after city_name
            $table->string('sektor', 100)->after('city_name');

            // Composite index: deactivation queries filter by (city_id + sektor + is_aktif)
            $table->index(['city_id', 'sektor', 'is_aktif'], 'idx_umsk_city_sektor_aktif');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('m_umsk', function (Blueprint $table) {
            $table->dropIndex('idx_umsk_city_sektor_aktif');
            $table->dropColumn('sektor');
        });
    }
};