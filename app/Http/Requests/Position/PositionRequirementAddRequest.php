<?php

namespace App\Http\Requests\Position;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class PositionRequirementAddRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'position_id' => FluentRule::integer()->required()->exists('mysqlhris.m_position', 'id'),
            'nama'        => FluentRule::string()->required()->max(255)->unique('mysqlhris.m_position', 'name'),
            'layanan_id'  => FluentRule::integer()->required()->exists('m_kebutuhan', 'id'),
        ];
    }

    public function messages(): array
    {
        return [
            'position_id.exists' => 'Selected position does not exist',
            'layanan_id.exists'  => 'Selected service does not exist',
            'nama.max'           => 'Requirement text cannot exceed 500 characters',
        ];
    }
}
