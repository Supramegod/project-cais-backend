<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('m_umsk')) {
            return;
        }

        Schema::connection($this->connection)->table('m_umsk', function (Blueprint $table) {
            if (! Schema::connection($this->connection)->hasColumn('m_umsk', 'sektor')) {
                $table->string('sektor', 100)->after('city_name');
            }

            // Composite index: deactivation queries filter by (city_id + sektor + is_aktif)
            if (! Schema::connection($this->connection)->hasIndex('m_umsk', 'idx_umsk_city_sektor_aktif')) {
                $table->index(['city_id', 'sektor', 'is_aktif'], 'idx_umsk_city_sektor_aktif');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasTable('m_umsk')) {
            return;
        }

        Schema::connection($this->connection)->table('m_umsk', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasIndex('m_umsk', 'idx_umsk_city_sektor_aktif')) {
                $table->dropIndex('idx_umsk_city_sektor_aktif');
            }
            if (Schema::connection($this->connection)->hasColumn('m_umsk', 'sektor')) {
                $table->dropColumn('sektor');
            }
        });
    }
};
