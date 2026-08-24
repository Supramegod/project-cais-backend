<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class UserListRequest extends BaseRequest
{
    public const IS_ACTIVE_ALL = 'all';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => FluentRule::integer('Role ID')->nullable()->exists('mysqlhris.m_role', 'id'),
            'branch_id' => FluentRule::integer('Branch ID')->nullable(),
            'is_active' => FluentRule::string('Is Active')->nullable()->in(['0', '1', self::IS_ACTIVE_ALL]),
            'search' => FluentRule::string('Search')->nullable()->max(100),
            'per_page' => FluentRule::integer('Per Page')->nullable()->min(1)->max(100),
        ];
    }
}
