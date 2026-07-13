<?php

namespace App\Http\Requests\TimSales;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class TimSalesAddMemberRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => FluentRule::integer('User')->required()->exists('mysqlhris.m_user', 'id'),
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User harus diisi',
            'user_id.integer'  => 'User harus berupa angka',
            'user_id.exists'   => 'User tidak ditemukan',
        ];
    }
}
