<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step11Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'penagihan' => FluentRule::string()->required(),
            'tunjangan_data' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::array()->sometimes()->each([
                    'nama_tunjangan' => FluentRule::string()->required()->max(255),
                    'nominal' => FluentRule::numeric()->required()->min(0),
                ]),
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'penagihan.required' => 'Metode penagihan harus diisi',
            'tunjangan_data.array' => 'Data tunjangan harus berupa array',
            'tunjangan_data.*.*.nama_tunjangan.required' => 'Nama tunjangan harus diisi',
            'tunjangan_data.*.*.nama_tunjangan.max' => 'Nama tunjangan maksimal 255 karakter',
            'tunjangan_data.*.*.nominal.required' => 'Nominal tunjangan harus diisi',
            'tunjangan_data.*.*.nominal.min' => 'Nominal tunjangan tidak boleh kurang dari 0',
        ];
    }
}
