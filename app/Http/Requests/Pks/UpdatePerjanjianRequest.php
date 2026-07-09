<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;

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
