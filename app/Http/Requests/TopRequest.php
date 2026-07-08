<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class TopRequest extends BaseRequest
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
                ->rule(Rule::unique('m_top', 'nama')->ignore($id)->whereNull('deleted_at')),
            'persentase' => FluentRule::numeric('Persentase')
                ->required()
                ->min(0)
                ->max(100),
        ];
    }
}
