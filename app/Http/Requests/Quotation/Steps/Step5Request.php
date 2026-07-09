<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step5Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'jenis-perusahaan' => FluentRule::integer()->required()->exists('m_jenis_perusahaan', 'id'),
            'bidang-perusahaan' => FluentRule::integer()->required()->exists('m_bidang_perusahaan', 'id'),
            'resiko' => FluentRule::string()->required(),
            'program-bpjs' => FluentRule::string()->required(),
            'penjamin' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::string()->sometimes(),
            ]),
            'jkk' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::boolean()->sometimes(),
            ]),
            'jkm' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::boolean()->sometimes(),
            ]),
            'jht' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::boolean()->sometimes(),
            ]),
            'jp' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::boolean()->sometimes(),
            ]),
            'nominal_takaful' => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::numeric()->sometimes()->min(0),
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'jenis-perusahaan.required' => 'Jenis perusahaan harus dipilih',
            'jenis-perusahaan.exists' => 'Jenis perusahaan tidak valid',
            'bidang-perusahaan.required' => 'Bidang perusahaan harus dipilih',
            'bidang-perusahaan.exists' => 'Bidang perusahaan tidak valid',
            'resiko.required' => 'Resiko harus diisi',
            'program-bpjs.required' => 'Program BPJS harus diisi',
            'penjamin.array' => 'Penjamin harus berupa array',
            'jkk.*.boolean' => 'JKK harus berupa boolean (true/false)',
            'jkm.*.boolean' => 'JKM harus berupa boolean (true/false)',
            'jht.*.boolean' => 'JHT harus berupa boolean (true/false)',
            'jp.*.boolean' => 'JP harus berupa boolean (true/false)',
            'nominal_takaful.*.min' => 'Nominal takaful tidak boleh kurang dari 0',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'jenis_perusahaan_id' => $this->input('jenis-perusahaan'),
            'bidang_perusahaan_id' => $this->input('bidang-perusahaan'),
        ]);
    }
}
