<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class SupplierRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => FluentRule::string('Nama')->required()->max(255),
            'pic' => FluentRule::string('PIC')->required()->max(255),
            'alamat' => FluentRule::string('Alamat')->required(),
            'kontak' => FluentRule::string('Kontak')->required()->max(50),
        ];
    }
}
