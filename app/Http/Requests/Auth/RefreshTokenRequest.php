<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class RefreshTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'refresh_token' => FluentRule::string('Refresh Token')->required(),
        ];
    }
}