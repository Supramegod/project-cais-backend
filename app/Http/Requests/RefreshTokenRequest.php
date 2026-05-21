<?php

namespace App\Http\Requests;

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