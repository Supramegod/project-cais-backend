<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->addUniqueIfMissing('sl_quotation_detail_hpp', 'quotation_detail_id', 'uq_hpp_detail_id');
        $this->addUniqueIfMissing('sl_quotation_detail_coss', 'quotation_detail_id', 'uq_coss_detail_id');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('sl_quotation_detail_hpp', 'uq_hpp_detail_id');
        $this->dropIndexIfExists('sl_quotation_detail_coss', 'uq_coss_detail_id');
    }

    private function addUniqueIfMissing(string $table, string $column, string $index): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column, $index) {
            $t->unique($column, $index);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($index) {
            $t->dropUnique($index);
        });
    }
};
