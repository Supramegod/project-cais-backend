<?php

namespace App\Http\Requests\TimSales;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class TimSalesSetLeaderRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => FluentRule::integer('Member')->required(),
        ];
    }
}
