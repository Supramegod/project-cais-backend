<?php

namespace App\Http\Requests\Menu;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class MenuGroupStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => FluentRule::string('Nama')->required()->max(100)
                ->unique('sysmenu_group', 'nama', message: 'Nama grup sudah digunakan.'),
        ];
    }
}
