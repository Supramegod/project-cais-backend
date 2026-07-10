<?php

namespace App\Http\Requests\Position;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class PositionEditRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entitas' => FluentRule::integer()->required()->exists('mysqlhris.m_company', 'id'),
            'layanan' => FluentRule::integer()->required()->exists('m_kebutuhan', 'id'),
        ];
    }

    public function messages(): array
    {
        return [
            'entitas.exists' => 'Selected company does not exist',
            'layanan.exists' => 'Selected service does not exist',
        ];
    }
}
