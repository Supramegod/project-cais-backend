<?php

namespace App\Http\Requests\Position;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class PositionSaveRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entitas'  => FluentRule::integer()->required()->exists('mysqlhris.m_company', 'id'),
            'layanan'  => FluentRule::integer()->required()->exists('m_kebutuhan', 'id'),
            'nama'     => FluentRule::string()->required()->max(255)->unique('mysqlhris.m_position', 'name'),
            'deskripsi' => FluentRule::string()->required(),
        ];
    }

    public function messages(): array
    {
        return [
            'nama.unique'     => 'Position name already exists',
            'entitas.exists'  => 'Selected company does not exist',
            'layanan.exists'  => 'Selected service does not exist',
        ];
    }
}
