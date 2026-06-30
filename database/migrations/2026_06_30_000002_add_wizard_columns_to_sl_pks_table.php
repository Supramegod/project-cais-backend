<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sl_pks', function (Blueprint $table) {
            $table->unsignedBigInteger('wizard_status_id')->nullable()->after('status_pks_id');
            $table->unsignedInteger('wizard_current_step')->nullable()->after('wizard_status_id');
            $table->json('wizard_completed_steps')->nullable()->after('wizard_current_step');
            $table->json('wizard_payload')->nullable()->after('wizard_completed_steps');
            $table->json('template_payload')->nullable()->after('wizard_payload');
            $table->json('pasal_preview_payload')->nullable()->after('template_payload');
            $table->timestamp('initialized_at')->nullable()->after('pasal_preview_payload');
            $table->timestamp('finalized_at')->nullable()->after('initialized_at');

            $table->index('wizard_status_id');
            $table->foreign('wizard_status_id')->references('id')->on('m_pks_wizard_status');
        });
    }

    public function down(): void
    {
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
