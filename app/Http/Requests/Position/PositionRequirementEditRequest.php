<?php

namespace App\Http\Requests\Position;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class PositionRequirementEditRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id'          => FluentRule::integer()->required()->exists('m_requirement_posisi', 'id'),
            'requirement' => FluentRule::string()->required()->max(500),
        ];
    }

    public function messages(): array
    {
        return [
            'id.exists'        => 'Selected requirement does not exist',
            'requirement.max'  => 'Requirement text cannot exceed 500 characters',
        ];
    }
}
