<?php

namespace App\Http\Requests\Sales;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class SalesRevenueByMonthRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => FluentRule::integer('Year')->required()->min(2000)->max(2100),
        ];
    }
}
