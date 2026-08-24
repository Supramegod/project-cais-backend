<?php

namespace App\Http\Requests\Master;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class CompanyGroupUpdateRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nama_grup' => FluentRule::string('Nama grup')
                ->required()
                ->min(3)
                ->max(100)
                ->unique('sl_perusahaan_groups', 'nama_grup', function ($rule) use ($id) {
                    if ($id !== null) {
                        $rule->ignore($id);
                    }
                }),
        ];
    }
}
