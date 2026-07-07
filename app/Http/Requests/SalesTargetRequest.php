<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class SalesTargetRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Saat update (PUT/PATCH) field kunci menjadi opsional (sometimes),
        // meniru perilaku controller lama: rules($ignoreId).
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        $requiredOrSometimes = $isUpdate ? 'sometimes' : 'required';

        return [
            'type' => [
                $requiredOrSometimes,
                Rule::in(['personal', 'branch', 'company']),
            ],
            'period_type' => [
                $requiredOrSometimes,
                Rule::in(['monthly', 'yearly']),
            ],
            'year' => [
                $requiredOrSometimes,
                'integer',
                'min:2000',
                'max:2100',
            ],
            'month' => [
                'nullable',
                'integer',
                'between:1,12',
                'required_if:period_type,monthly',
            ],
            'user_id' => [
                'nullable',
                'string',
                'required_if:type,personal',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                'exists:m_branch,id',
                'required_if:type,branch',
            ],
            'target_amount' => [
                $requiredOrSometimes,
                'numeric',
                'min:1',
            ],
        ];
    }
}
