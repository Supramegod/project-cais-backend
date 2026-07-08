<?php

namespace App\Http\Requests;

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
