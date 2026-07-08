<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class KebutuhanDetailTunjanganRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kebutuhan_id' => FluentRule::integer('Kebutuhan')->required(),
            'position_id'  => FluentRule::integer('Posisi')->nullable(),
            'nama'         => FluentRule::string('Nama')->required(),
            'nominal'      => FluentRule::string('Nominal')->required(),
        ];
    }
}
