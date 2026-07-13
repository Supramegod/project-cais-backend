<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class ManagementFeeRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nama' => FluentRule::string('Nama')
                ->required()
                ->max(255)
                ->unique('m_management_fee', 'nama', function ($rule) use ($id) {
                    if ($id !== null) {
                        $rule->ignore($id);
                    }
                }),
        ];
    }
}
