<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseRequest;
use App\Models\User;
use App\Services\MenuPermissionService;
use Illuminate\Contracts\Validation\Validator;
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
            'user_id' => FluentRule::integer('User ID')->nullable()->exists('mysqlhris.m_user', 'id'),
            'akses' => FluentRule::array(label: 'Akses')->nullable(),
            'akses.*.sysmenu_id' => FluentRule::integer('Sysmenu ID')->required(),
            'akses.*.field' => FluentRule::string('Field')->required()->in(MenuPermissionService::FIELDS),
            'akses.*.value' => FluentRule::boolean('Value')->required(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userId = $this->input('user_id');

            if ($userId === null || $validator->errors()->has('user_id')) {
                return;
            }

            $user = User::query()->find($userId);

            if (! $user) {
                return;
            }

            if ((int) $user->cais_role_id !== (int) $this->route('id')) {
                $validator->errors()->add(
                    'user_id',
                    'User tidak berada pada role ini.'
                );
            }
        });
    }
}
