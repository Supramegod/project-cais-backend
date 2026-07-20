<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defensif: tabel mungkin sudah dibuat manual di DB dev.
        if (Schema::hasTable('sl_pks_item_fulfillment')) {
            return;
        }

        Schema::create('sl_pks_item_fulfillment', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('pks_id');
            $table->bigInteger('site_id');
            $table->bigInteger('leads_id')->nullable();
            $table->enum('item_type', ['kaporlap', 'device', 'chemical']);
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('qty_diminta')->default(0);
            $table->unsignedInteger('qty_terpenuhi')->default(0);
            $table->enum('status', ['not_yet_fulfilled', 'partially_fulfilled', 'fully_fulfilled'])
                ->default('not_yet_fulfilled');

            // Audit
            $table->string('created_by', 255)->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->string('deleted_by', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Constraints (nama unique eksplisit agar tidak melebihi limit MySQL)
            $table->unique(['pks_id', 'site_id', 'item_type', 'item_id'], 'uq_pks_item_fulfillment');

            // Indexes
            $table->index('pks_id');
            $table->index('site_id');
            $table->index('leads_id');
            $table->index('status');

            // Foreign keys
            $table->foreign('pks_id')->references('id')->on('sl_pks')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sl_site')->cascadeOnDelete();
            $table->foreign('leads_id')->references('id')->on('sl_leads')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_pks_item_fulfillment');
    }
};
