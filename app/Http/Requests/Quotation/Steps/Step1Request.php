<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step1Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'jenis_kontrak' => FluentRule::string()->required()->in([
                'Reguler',
                'Event Gaji Harian',
                'PKHL',
                'Borongan',
                'General Cleaning',
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'jenis_kontrak.required' => 'Jenis kontrak harus diisi',
            'jenis_kontrak.string' => 'Jenis kontrak harus berupa teks',
            'jenis_kontrak.in' => 'Jenis kontrak harus salah satu dari: Reguler, Event Gaji Harian, PKHL, Borongan',
        ];
    }
}
