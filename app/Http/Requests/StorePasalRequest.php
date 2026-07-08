<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class StorePasalRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pasal' => FluentRule::string('Pasal')->required()->max(50),
            'judul' => FluentRule::string('Judul')->required()->max(255),
            'raw_text' => FluentRule::string('Isi')->required(),
        ];
    }
}
