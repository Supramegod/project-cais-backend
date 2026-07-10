<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step6Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'aplikasi_pendukung' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::integer()->exists('m_aplikasi_pendukung', 'id'),
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'aplikasi_pendukung.array' => 'Aplikasi pendukung harus berupa array',
            'aplikasi_pendukung.*.exists' => 'Aplikasi pendukung tidak valid',
        ];
    }
}
