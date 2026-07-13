<?php

namespace App\Http\Requests\Option;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class GetUsersOptionRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => FluentRule::integer('branch_id')->required(),
        ];
    }
}
