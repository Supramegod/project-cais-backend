<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class UpdatePerjanjianRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul' => FluentRule::string('Judul')->nullable(),
            'raw_text' => FluentRule::string('Isi')->required(),
        ];
    }
}
