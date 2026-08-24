<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseRequest;
use App\Services\MenuPermissionService;
use App\Traits\ValidatesRoleUser;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

class RoleUpdatePermissionsRequest extends BaseRequest
{
    use ValidatesRoleUser;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => FluentRule::integer('User ID')->nullable()->exists('mysqlhris.m_user', 'id'),
            'akses' => FluentRule::array(label: 'Akses')->nullable(),
            'akses.*.sysmenu_id' => FluentRule::integer('Sysmenu ID')->required()->exists('sysmenu', 'id'),
            'akses.*.field' => FluentRule::string('Field')->required()->in(MenuPermissionService::FIELDS),
            'akses.*.value' => FluentRule::boolean('Value')->required(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateUserBelongsToRole($validator));
    }
}
