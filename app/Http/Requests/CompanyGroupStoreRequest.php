<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class CompanyGroupStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_grup' => FluentRule::string('Nama grup')
                ->required()
                ->min(3)
                ->max(100)
                ->unique('sl_perusahaan_groups', 'nama_grup'),

            'perusahaan_ids' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::integer()->exists('sl_leads', 'id'),
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'nama_grup.required'      => 'Nama grup wajib diisi',
            'nama_grup.unique'        => 'Nama grup sudah digunakan',
            'nama_grup.min'           => 'Nama grup minimal 3 karakter',
            'nama_grup.max'           => 'Nama grup maksimal 100 karakter',
            'perusahaan_ids.array'    => 'Format perusahaan_ids harus berupa array',
            'perusahaan_ids.*.exists' => 'Salah satu perusahaan tidak ditemukan',
        ];
    }
}
