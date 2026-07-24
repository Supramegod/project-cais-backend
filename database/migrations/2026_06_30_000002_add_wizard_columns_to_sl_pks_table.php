<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sl_pks')) {
            return;
        }

        // Kolom wizard_status_id jadi penanda: kalau sudah ada, seluruh set kolom
        // + index + FK dianggap sudah ter-apply (migration ini all-or-nothing).
        $alreadyApplied = Schema::hasColumn('sl_pks', 'wizard_status_id');

        $columns = [
            'wizard_status_id' => fn (Blueprint $t) => $t->unsignedBigInteger('wizard_status_id')->nullable()->after('status_pks_id'),
            'wizard_current_step' => fn (Blueprint $t) => $t->unsignedInteger('wizard_current_step')->nullable()->after('wizard_status_id'),
            'wizard_completed_steps' => fn (Blueprint $t) => $t->json('wizard_completed_steps')->nullable()->after('wizard_current_step'),
            'wizard_payload' => fn (Blueprint $t) => $t->json('wizard_payload')->nullable()->after('wizard_completed_steps'),
            'template_payload' => fn (Blueprint $t) => $t->json('template_payload')->nullable()->after('wizard_payload'),
            'pasal_preview_payload' => fn (Blueprint $t) => $t->json('pasal_preview_payload')->nullable()->after('template_payload'),
            'initialized_at' => fn (Blueprint $t) => $t->timestamp('initialized_at')->nullable()->after('pasal_preview_payload'),
            'finalized_at' => fn (Blueprint $t) => $t->timestamp('finalized_at')->nullable()->after('initialized_at'),
        ];

        Schema::table('sl_pks', function (Blueprint $table) use ($columns) {
            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('sl_pks', $name)) {
                    $add($table);
                }
            }
        });

        if (! $alreadyApplied) {
            Schema::table('sl_pks', function (Blueprint $table) {
                $table->index('wizard_status_id');
                $table->foreign('wizard_status_id')->references('id')->on('m_pks_wizard_status');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sl_pks') || ! Schema::hasColumn('sl_pks', 'wizard_status_id')) {
            return;
        }

        Schema::table('sl_pks', function (Blueprint $table) {
            $table->dropForeign(['wizard_status_id']);
            $table->dropIndex(['wizard_status_id']);
            $table->dropColumn([
                'wizard_status_id',
                'wizard_current_step',
                'wizard_completed_steps',
                'wizard_payload',
                'template_payload',
                'pasal_preview_payload',
                'initialized_at',
                'finalized_at',
            ]);
        });
    }
};
