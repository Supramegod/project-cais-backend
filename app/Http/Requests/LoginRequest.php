<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => FluentRule::string('Username')->required(),
            'password' => FluentRule::string('Password')->required(),
        ];
    }
}