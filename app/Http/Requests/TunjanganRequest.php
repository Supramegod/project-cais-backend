<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class TunjanganRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => FluentRule::string('Nama')->required()->max(255),
            'nominal' => FluentRule::numeric('Nominal')->required()->min(0),
            'kebutuhan_id' => FluentRule::numeric('Kebutuhan')->required()->exists('m_kebutuhan', 'id'),
            'position_id' => FluentRule::numeric('Position')->required()->exists('mysqlhris.m_position', 'id'),
        ];
    }
}
