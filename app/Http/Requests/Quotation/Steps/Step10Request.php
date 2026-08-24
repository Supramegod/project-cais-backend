<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step10Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'jumlah_kunjungan_operasional' => FluentRule::integer()->required()->min(0),
            'bulan_tahun_kunjungan_operasional' => FluentRule::string()->required()->in(['Bulan', 'Tahun']),
            'jumlah_kunjungan_tim_crm' => FluentRule::integer()->required()->min(0),
            'bulan_tahun_kunjungan_tim_crm' => FluentRule::string()->required()->in(['Bulan', 'Tahun']),
            'keterangan_kunjungan_operasional' => FluentRule::string()->sometimes(),
            'keterangan_kunjungan_tim_crm' => FluentRule::string()->sometimes(),
            'ada_training' => FluentRule::string()->sometimes()->in(['Ada', 'Tidak Ada']),
            'training' => FluentRule::string()->sometimes(),
            'persen_bunga_bank' => FluentRule::numeric()->sometimes()->min(0),
        ];
    }

    public function messages(): array
    {
        return [
            'jumlah_kunjungan_operasional.required' => 'Jumlah kunjungan operasional harus diisi',
            'jumlah_kunjungan_operasional.min' => 'Jumlah kunjungan operasional tidak boleh kurang dari 0',
            'bulan_tahun_kunjungan_operasional.required' => 'Periode kunjungan operasional harus dipilih',
            'bulan_tahun_kunjungan_operasional.in' => 'Periode kunjungan operasional harus Bulan atau Tahun',
            'jumlah_kunjungan_tim_crm.required' => 'Jumlah kunjungan tim CRM harus diisi',
            'jumlah_kunjungan_tim_crm.min' => 'Jumlah kunjungan tim CRM tidak boleh kurang dari 0',
            'bulan_tahun_kunjungan_tim_crm.required' => 'Periode kunjungan tim CRM harus dipilih',
            'bulan_tahun_kunjungan_tim_crm.in' => 'Periode kunjungan tim CRM harus Bulan atau Tahun',
            'ada_training.in' => 'Ada training harus salah satu dari: Ada, Tidak Ada',
            'persen_bunga_bank.min' => 'Persen bunga bank tidak boleh kurang dari 0',
        ];
    }
}
