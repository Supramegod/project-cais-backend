<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseRequest;
use App\Traits\ValidatesRoleUser;
use Illuminate\Contracts\Validation\Validator;
use SanderMuller\FluentValidation\FluentRule;

class RoleShowRequest extends BaseRequest
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateUserBelongsToRole($validator));
    }
}
