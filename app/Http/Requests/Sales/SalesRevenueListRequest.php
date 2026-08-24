<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class SalesRevenueListRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'       => FluentRule::integer('Year')->nullable()->min(2000)->max(2100),
            'month'      => FluentRule::integer('Month')->nullable()->between(1, 12),
            'user_id'    => FluentRule::integer('User')->nullable(),
            'start_date' => FluentRule::date('Start Date')->nullable()->format('Y-m-d'),
            'end_date'   => FluentRule::date('End Date')->nullable()->format('Y-m-d')->afterOrEqual('start_date'),
        ];
    }
}
