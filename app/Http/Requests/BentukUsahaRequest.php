<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class BentukUsahaRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => FluentRule::string('Nama')->required()->max(100),
        ];
    }
}
