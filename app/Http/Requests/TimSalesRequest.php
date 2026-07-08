<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class TimSalesRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'      => FluentRule::string('Nama')->required()->max(255),
            'branch_id' => FluentRule::integer('Branch')->required()->exists('m_branch', 'id'),
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required'      => 'Nama harus diisi',
            'nama.string'        => 'Nama harus berupa teks',
            'nama.max'           => 'Nama maksimal 255 karakter',
            'branch_id.required' => 'Branch harus diisi',
            'branch_id.integer'  => 'Branch harus berupa angka',
            'branch_id.exists'   => 'Branch tidak ditemukan',
        ];
    }
}
