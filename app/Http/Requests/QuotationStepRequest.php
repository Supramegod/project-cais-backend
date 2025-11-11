<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = $this->route('step');

        $rules = [
            'edit' => 'sometimes|boolean',
        ];

        switch ($step) {
            case 1:
                $rules['jenis_kontrak'] = 'required|string|in:Reguler,Event Gaji Harian,PKHL,Borongan';
                break;

            case 2:
                $rules['mulai_kontrak'] = 'required|date';
                $rules['kontrak_selesai'] = 'required|date|after_or_equal:mulai_kontrak';
                $rules['tgl_penempatan'] = 'required|date';
                $rules['top'] = 'required|string';
                $rules['salary_rule'] = 'required|exists:m_salary_rule,id';
                $rules['jumlah_hari_invoice'] = 'sometimes|integer|min:1';
                $rules['tipe_hari_invoice'] = 'sometimes|string|in:Hari Kerja,Hari Kalender';
                $rules['evaluasi_kontrak'] = 'sometimes|string';
                $rules['durasi_kerjasama'] = 'sometimes|string';
                $rules['durasi_karyawan'] = 'sometimes|string';
                $rules['evaluasi_karyawan'] = 'sometimes|string';
                $rules['ada_cuti'] = 'sometimes|string|in:Ada,Tidak Ada';
                $rules['cuti'] = 'sometimes|array';
                $rules['cuti.*'] = 'sometimes|string|in:Cuti Tahunan,Cuti Melahirkan,Cuti Kematian,Istri Melahirkan,Cuti Menikah';
                $rules['hari_cuti_kematian'] = 'sometimes|integer|min:0';
                $rules['hari_istri_melahirkan'] = 'sometimes|integer|min:0';
                $rules['hari_cuti_menikah'] = 'sometimes|integer|min:0';
                $rules['gaji_saat_cuti'] = 'sometimes|string|in:Full Pay,Prorate';
                $rules['prorate'] = 'sometimes|integer|min:0';
                $rules['shift_kerja'] = 'sometimes|string';
                $rules['hari_kerja'] = 'sometimes|string';
                $rules['jam_kerja'] = 'sometimes|string';
                break;
            case 3:
                $rules['headCountData'] = 'sometimes|array';
                $rules['headCountData.*.quotation_site_id'] = 'sometimes|required|integer';
                $rules['headCountData.*.position_id'] = 'sometimes|required|integer';
                $rules['headCountData.*.jumlah_hc'] = 'sometimes|required|integer|min:1';
                $rules['headCountData.*.jabatan_kebutuhan'] = 'sometimes|required|string';
                $rules['headCountData.*.nama_site'] = 'sometimes|required|string';

                break;

            case 4:
                $rules['position_data'] = 'required|array';
                $rules['position_data.*.quotation_detail_id'] = 'required|exists:sl_quotation_detail,id';
                $rules['position_data.*.upah'] = 'required|string|in:UMP,UMK,Custom';
                $rules['position_data.*.manajemen_fee'] = 'required|exists:m_management_fee,id';
                $rules['position_data.*.persentase'] = 'required|numeric|min:0|max:100';
                $rules['position_data.*.hitungan_upah'] = 'required_if:position_data.*.upah,Custom|string|in:Per Bulan,Per Hari,Per Jam';
                $rules['position_data.*.custom_upah'] = 'required_if:position_data.*.upah,Custom|string';

                // Field yang sekarang bertipe string
                $rules['position_data.*.lembur'] = 'sometimes|string';
                $rules['position_data.*.nominal_lembur'] = 'sometimes|numeric|min:0';
                $rules['position_data.*.jenis_bayar_lembur'] = 'sometimes|string';
                $rules['position_data.*.jam_per_bulan_lembur'] = 'sometimes|integer|min:0';
                $rules['position_data.*.lembur_ditagihkan'] = 'sometimes|string';
                $rules['position_data.*.kompensasi'] = 'sometimes|string';
                $rules['position_data.*.thr'] = 'sometimes|string';
                $rules['position_data.*.tunjangan_holiday'] = 'sometimes|string';
                $rules['position_data.*.nominal_tunjangan_holiday'] = 'sometimes|numeric|min:0';
                $rules['position_data.*.jenis_bayar_tunjangan_holiday'] = 'sometimes|string';
                $rules['position_data.*.is_ppn'] = 'sometimes|string';
                $rules['position_data.*.ppn_pph_dipotong'] = 'sometimes|string';
                break;

            case 5:
                $rules['jenis-perusahaan'] = 'required|exists:m_jenis_perusahaan,id';
                $rules['bidang-perusahaan'] = 'required|exists:m_bidang_perusahaan,id';
                $rules['resiko'] = 'required|string';
                $rules['program-bpjs'] = 'required|string';
                $rules['penjamin'] = 'sometimes|array';
                $rules['penjamin.*'] = 'sometimes|string';
                $rules['jkk'] = 'sometimes|array';
                $rules['jkk.*'] = 'sometimes|boolean';
                $rules['jkm'] = 'sometimes|array';
                $rules['jkm.*'] = 'sometimes|boolean';
                $rules['jht'] = 'sometimes|array';
                $rules['jht.*'] = 'sometimes|boolean';
                $rules['jp'] = 'sometimes|array';
                $rules['jp.*'] = 'sometimes|boolean';
                $rules['nominal_takaful'] = 'sometimes|array';
                $rules['nominal_takaful.*'] = 'sometimes|numeric|min:0';
                break;

            case 6:
                $rules['aplikasi_pendukung'] = 'sometimes|array';
                $rules['aplikasi_pendukung.*'] = 'exists:m_aplikasi_pendukung,id';
                break;

            case 10:
                $rules['jumlah_kunjungan_operasional'] = 'required|integer|min:0';
                $rules['bulan_tahun_kunjungan_operasional'] = 'required|string|in:Bulan,Tahun';
                $rules['jumlah_kunjungan_tim_crm'] = 'required|integer|min:0';
                $rules['bulan_tahun_kunjungan_tim_crm'] = 'required|string|in:Bulan,Tahun';
                $rules['keterangan_kunjungan_operasional'] = 'sometimes|string';
                $rules['keterangan_kunjungan_tim_crm'] = 'sometimes|string';
                $rules['ada_training'] = 'sometimes|string|in:Ada,Tidak Ada';
                $rules['training'] = 'sometimes|string';
                $rules['persen_bunga_bank'] = 'sometimes|numeric|min:0';
                break;

            case 11:
                $rules['penagihan'] = 'required|string';
                break;

            case 12:
                // Tidak ada field khusus, hanya konfirmasi final
                break;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'mulai_kontrak.required' => 'Mulai Kontrak harus diisi',
            'kontrak_selesai.required' => 'Kontrak Selesai harus diisi',
            'kontrak_selesai.after_or_equal' => 'Kontrak Selesai harus setelah atau sama dengan Mulai Kontrak',
            'tgl_penempatan.required' => 'Tanggal Penempatan harus diisi',
            'top.required' => 'TOP harus diisi',
            'salary_rule.required' => 'Salary Rule harus diisi',
            'salary_rule.exists' => 'Salary Rule tidak valid',
            'upah.required' => 'Jenis upah harus dipilih',
            'manajemen_fee.required' => 'Manajemen Fee harus dipilih',
            'manajemen_fee.exists' => 'Manajemen Fee tidak valid',
            'persentase.required' => 'Persentase harus diisi',
            'persentase.numeric' => 'Persentase harus berupa angka',
            'persentase.min' => 'Persentase tidak boleh kurang dari 0',
            'custom-upah.required_if' => 'Nominal upah custom harus diisi',
            'jenis-perusahaan.required' => 'Jenis perusahaan harus dipilih',
            'bidang-perusahaan.required' => 'Bidang perusahaan harus dipilih',
            'resiko.required' => 'Resiko harus diisi',
            'program-bpjs.required' => 'Program BPJS harus diisi',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $step = $this->route('step');

            // Validasi custom untuk step 2
            if ($step == 2) {
                if ($this->mulai_kontrak && $this->kontrak_selesai) {
                    if ($this->mulai_kontrak > $this->kontrak_selesai) {
                        $validator->errors()->add('mulai_kontrak', 'Mulai Kontrak tidak boleh lebih dari Kontrak Selesai');
                    }
                    if ($this->tgl_penempatan < $this->mulai_kontrak) {
                        $validator->errors()->add('tgl_penempatan', 'Tanggal Penempatan tidak boleh kurang dari Mulai Kontrak');
                    }
                    if ($this->tgl_penempatan > $this->kontrak_selesai) {
                        $validator->errors()->add('tgl_penempatan', 'Tanggal Penempatan tidak boleh lebih dari Kontrak Selesai');
                    }
                }
            }
        });
    }
}