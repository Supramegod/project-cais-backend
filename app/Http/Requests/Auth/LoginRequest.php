<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

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