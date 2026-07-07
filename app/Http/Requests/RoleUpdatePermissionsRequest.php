<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class RoleUpdatePermissionsRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'akses' => FluentRule::array(label: 'Akses')->nullable(),
            'akses.*.sysmenu_id' => FluentRule::integer('Sysmenu ID')->required(),
            'akses.*.field' => FluentRule::string('Field')->required(),
            'akses.*.value' => FluentRule::field('Value')->required(),
        ];
    }
}
