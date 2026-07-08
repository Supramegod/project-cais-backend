<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class JenisPerusahaanRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'   => FluentRule::string('Nama')->required()->max(255),
            'resiko' => FluentRule::string('Resiko')->required()->max(100),
        ];
    }
}
