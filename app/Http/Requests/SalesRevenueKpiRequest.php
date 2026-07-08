<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class SalesRevenueKpiRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'        => FluentRule::integer('Year')->required()->min(2000)->max(2100),
            'month'       => FluentRule::integer('Month')->nullable()->between(1, 12),
            'user_id'     => FluentRule::string('User')->nullable(),
            'branch_id'   => FluentRule::integer('Branch')->nullable(),
            'period_type' => FluentRule::field('Period Type')->nullable()->in(['monthly', 'yearly']),
        ];
    }
}
