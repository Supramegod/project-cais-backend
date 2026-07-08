<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use SanderMuller\FluentValidation\FluentRule;

class SalaryRuleRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('id');

        return [
            'nama_salary_rule' => FluentRule::string('Nama salary rule')
                ->required()
                ->max(255)
                ->rule(Rule::unique('m_salary_rule', 'nama_salary_rule')->ignore($ignoreId)),
            'cutoff_awal' => FluentRule::integer('Cutoff awal')->required()->min(1)->max(31),
            'cutoff_akhir' => FluentRule::integer('Cutoff akhir')->required()->min(1)->max(31),
            'crosscheck_absen_awal' => FluentRule::integer('Crosscheck absen awal')->required()->min(1)->max(31),
            'crosscheck_absen_akhir' => FluentRule::integer('Crosscheck absen akhir')->required()->min(1)->max(31),
            'pengiriman_invoice_awal' => FluentRule::integer('Pengiriman invoice awal')->required()->min(1)->max(31),
            'pengiriman_invoice_akhir' => FluentRule::integer('Pengiriman invoice akhir')->required()->min(1)->max(31),
            'perkiraan_invoice_diterima_awal' => FluentRule::integer('Perkiraan invoice diterima awal')->required()->min(1)->max(31),
            'perkiraan_invoice_diterima_akhir' => FluentRule::integer('Perkiraan invoice diterima akhir')->required()->min(1)->max(31),
            'pembayaran_invoice' => FluentRule::integer('Pembayaran invoice')->required()->min(1)->max(31),
            'rilis_payroll' => FluentRule::integer('Rilis payroll')->required()->min(1)->max(31),
        ];
    }
}
