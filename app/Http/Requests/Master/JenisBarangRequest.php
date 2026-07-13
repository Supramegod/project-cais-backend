<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class JenisBarangRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => FluentRule::string('Nama')->required()->max(255),
        ];
    }
}
