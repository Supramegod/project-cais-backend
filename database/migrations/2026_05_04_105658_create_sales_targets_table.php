<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sl_sales_targets')) {
            return;
        }

        Schema::create('sl_sales_targets', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('user_id', false, true)->nullable();

            $table->bigInteger('branch_id')->nullable();

            $table->string('type'); // lebih fleksibel
            $table->string('period_type');

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month')->nullable();

            $table->decimal('target_amount', 18, 2);

            $table->timestamps();

            $table->index(['year', 'month']);
            $table->index('user_id');
            $table->index('branch_id');
            $table->index('type');

            $table->unique([
                'type',
                'user_id',
                'branch_id',
                'year',
                'month',
                'period_type'
            ], 'uq_sales_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sl_sales_targets');
    }
};