<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step4Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'is_ppn' => FluentRule::integer()->required()->in([0, 1]),
            'ppn_pph_dipotong' => FluentRule::string()->required()->in(['Total Invoice', 'Management Fee']),
            'management_fee_id' => FluentRule::integer()->required()->exists('m_management_fee', 'id'),
            'persentase' => FluentRule::numeric()->required()->min(0)->max(100),
            'position_data' => FluentRule::array()->required()->min(1)->each([
                'quotation_detail_id' => FluentRule::integer()->required()->exists('sl_quotation_detail', 'id'),
                'upah' => FluentRule::string()->required()->in(['UMP', 'UMK', 'Custom']),
                'hitungan_upah' => FluentRule::string()->requiredIf('upah', 'Custom')->in(['Per Bulan', 'Per Hari', 'Per Jam']),
                'nominal_upah' => FluentRule::numeric()->requiredIf('upah', 'Custom')->min(0),
                'lembur' => FluentRule::string()->sometimes()->in(['Flat', 'Tidak Ada', 'Normatif']),
                'nominal_lembur' => FluentRule::numeric()->requiredIf('lembur', 'Flat')->min(0),
                'jenis_bayar_lembur' => FluentRule::string()->requiredIf('lembur', 'Flat')->in(['Per Bulan', 'Per Hari', 'Per Jam']),
                'jam_per_bulan_lembur' => FluentRule::integer()->requiredIf('jenis_bayar_lembur', 'Per Jam')->min(0),
                'lembur_ditagihkan' => FluentRule::string()->rule('required_if:lembur,Flat,Normatif')->in(['Ditagihkan', 'Ditagihkan Terpisah']),
                'kompensasi' => FluentRule::string()->sometimes()->in(['Diprovisikan', 'Diberikan Langsung', 'Ditagihkan', 'Tidak Ada']),
                'thr' => FluentRule::string()->sometimes()->in(['Diprovisikan', 'Ditagihkan', 'Diberikan Langsung', 'Tidak Ada']),
                'tunjangan_holiday' => FluentRule::string()->sometimes()->in(['Flat', 'Tidak Ada', 'Normatif']),
                'nominal_tunjangan_holiday' => FluentRule::numeric()->requiredIf('tunjangan_holiday', 'Flat')->min(0),
                'jenis_bayar_tunjangan_holiday' => FluentRule::string()->requiredIf('tunjangan_holiday', 'Flat')->in(['Per Bulan', 'Per Hari', 'Per Jam']),
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'is_ppn.in' => 'Status PPN harus 0 atau 1',
            'is_ppn.required' => 'Status PPN harus diisi',
            'ppn_pph_dipotong.in' => 'PPN PPH dipotong harus salah satu dari: Total Invoice, Management Fee',
            'ppn_pph_dipotong.required' => 'PPN PPH dipotong harus diisi',
            'management_fee_id.required' => 'Management fee harus diisi',
            'management_fee_id.exists' => 'Management fee tidak valid',
            'persentase.required' => 'Persentase harus diisi',
            'persentase.numeric' => 'Persentase harus berupa angka',
            'persentase.min' => 'Persentase tidak boleh kurang dari 0',
            'persentase.max' => 'Persentase tidak boleh lebih dari 100',
            'position_data.required' => 'Data posisi harus diisi',
            'position_data.min' => 'Minimal satu data posisi harus dikirim',
            'position_data.*.quotation_detail_id.required' => 'Quotation detail ID harus diisi',
            'position_data.*.quotation_detail_id.exists' => 'Quotation detail ID tidak valid',
            'position_data.*.upah.in' => 'Jenis upah harus salah satu dari: UMP, UMK, Custom',
            'position_data.*.hitungan_upah.in' => 'Hitungan upah harus salah satu dari: Per Bulan, Per Hari, Per Jam',
            'position_data.*.nominal_upah.min' => 'Nominal upah tidak boleh kurang dari 0',
            'position_data.*.lembur.in' => 'Lembur harus salah satu dari: Flat, Tidak Ada, Normatif',
            'position_data.*.nominal_lembur.min' => 'Nominal lembur tidak boleh kurang dari 0',
            'position_data.*.jenis_bayar_lembur.in' => 'Jenis bayar lembur harus salah satu dari: Per Bulan, Per Hari, Per Jam',
            'position_data.*.jam_per_bulan_lembur.min' => 'Jam per bulan lembur tidak boleh kurang dari 0',
            'position_data.*.lembur_ditagihkan.in' => 'Lembur ditagihkan harus salah satu dari: Ditagihkan, Ditagihkan Terpisah',
            'position_data.*.tunjangan_holiday.in' => 'Tunjangan holiday harus salah satu dari: Flat, Tidak Ada, Normatif',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('manajemen_fee')) {
            $this->merge(['management_fee_id' => $this->manajemen_fee]);
        }
    }
}
