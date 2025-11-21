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
                $rules['tipe_hari_invoice'] = 'sometimes|string|in:Kerja,Kalender';
                $rules['evaluasi_kontrak'] = 'sometimes|string';
                $rules['durasi_kerjasama'] = 'sometimes|string';
                $rules['durasi_karyawan'] = 'sometimes|string';
                $rules['evaluasi_karyawan'] = 'sometimes|string';
                $rules['ada_cuti'] = 'required|string|in:Ada,Tidak Ada';
                $rules['cuti'] = 'required_if:ada_cuti,Ada|array';
                $rules['cuti.*'] = 'sometimes|string|in:Cuti Tahunan,Cuti Melahirkan,Cuti Kematian,Istri Melahirkan,Cuti Menikah,Cuti Roster,Tidak Ada';
                $rules['gaji_saat_cuti'] = 'required_if:ada_cuti,Ada|string|in:No Work No Pay,Prorate';
                $rules['prorate'] = 'required_if:gaji_saat_cuti,Prorate|integer|min:0';
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
            case 9:
                // Validasi untuk single chemical
                $rules['barang_id'] = 'sometimes|required_without:chemicals|exists:m_barang,id';
                $rules['jumlah'] = 'sometimes|required_without:chemicals|integer|min:0';
                $rules['masa_pakai'] = 'sometimes|integer|min:1';
                $rules['harga'] = 'sometimes|numeric|min:0';
                // Validasi untuk multiple chemicals
                $rules['chemicals'] = 'sometimes|array';
                $rules['chemicals.*.barang_id'] = 'required_with:chemicals|exists:m_barang,id';
                $rules['chemicals.*.jumlah'] = 'required_with:chemicals|integer|min:0';
                $rules['chemicals.*.masa_pakai'] = 'sometimes|integer|min:1';
                $rules['chemicals.*.harga'] = 'sometimes|numeric|min:0';
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
            // Step 1 Messages
            'jenis_kontrak.required' => 'Jenis kontrak harus diisi',
            'jenis_kontrak.string' => 'Jenis kontrak harus berupa teks',
            'jenis_kontrak.in' => 'Jenis kontrak harus salah satu dari: Reguler, Event Gaji Harian, PKHL, Borongan',

            // Step 2 Messages
            'mulai_kontrak.required' => 'Mulai kontrak harus diisi',
            'mulai_kontrak.date' => 'Mulai kontrak harus berupa tanggal yang valid',
            'kontrak_selesai.required' => 'Kontrak selesai harus diisi',
            'kontrak_selesai.date' => 'Kontrak selesai harus berupa tanggal yang valid',
            'kontrak_selesai.after_or_equal' => 'Kontrak selesai harus setelah atau sama dengan mulai kontrak',
            'tgl_penempatan.required' => 'Tanggal penempatan harus diisi',
            'tgl_penempatan.date' => 'Tanggal penempatan harus berupa tanggal yang valid',
            'top.required' => 'TOP harus diisi',
            'salary_rule.required' => 'Salary rule harus diisi',
            'salary_rule.exists' => 'Salary rule tidak valid',
            'ada_cuti.required' => 'Status cuti harus dipilih',
            'ada_cuti.in' => 'Status cuti harus Ada atau Tidak Ada',
            'cuti.required_if' => 'Jenis cuti harus dipilih ketika memilih ada cuti',
            'cuti.array' => 'Jenis cuti harus berupa array',
            'cuti.*.in' => 'Jenis cuti tidak valid',
            'gaji_saat_cuti.required_if' => 'Gaji saat cuti harus diisi ketika memilih ada cuti',
            'gaji_saat_cuti.in' => 'Gaji saat cuti harus salah satu dari: No Work No Pay, Prorate',
            'prorate.required_if' => 'Prorate harus diisi ketika memilih gaji saat cuti Prorate',
            'prorate.integer' => 'Prorate harus berupa angka',
            'prorate.min' => 'Prorate tidak boleh kurang dari 0',

            // Step 3 Messages
            'headCountData.*.quotation_site_id.required' => 'Site ID harus diisi',
            'headCountData.*.quotation_site_id.integer' => 'Site ID harus berupa angka',
            'headCountData.*.position_id.required' => 'Position ID harus diisi',
            'headCountData.*.position_id.integer' => 'Position ID harus berupa angka',
            'headCountData.*.jumlah_hc.required' => 'Jumlah HC harus diisi',
            'headCountData.*.jumlah_hc.integer' => 'Jumlah HC harus berupa angka',
            'headCountData.*.jumlah_hc.min' => 'Jumlah HC minimal 1',
            'headCountData.*.jabatan_kebutuhan.required' => 'Jabatan kebutuhan harus diisi',
            'headCountData.*.jabatan_kebutuhan.string' => 'Jabatan kebutuhan harus berupa teks',
            'headCountData.*.nama_site.required' => 'Nama site harus diisi',
            'headCountData.*.nama_site.string' => 'Nama site harus berupa teks',

            // Step 4 Messages
            'position_data.required' => 'Data posisi harus diisi',
            'position_data.array' => 'Data posisi harus berupa array',
            'position_data.*.quotation_detail_id.required' => 'Quotation detail ID harus diisi',
            'position_data.*.quotation_detail_id.exists' => 'Quotation detail ID tidak valid',
            'position_data.*.upah.required' => 'Jenis upah harus dipilih',
            'position_data.*.upah.in' => 'Jenis upah harus salah satu dari: UMP, UMK, Custom',
            'position_data.*.manajemen_fee.required' => 'Manajemen fee harus dipilih',
            'position_data.*.manajemen_fee.exists' => 'Manajemen fee tidak valid',
            'position_data.*.persentase.required' => 'Persentase harus diisi',
            'position_data.*.persentase.numeric' => 'Persentase harus berupa angka',
            'position_data.*.persentase.min' => 'Persentase tidak boleh kurang dari 0',
            'position_data.*.persentase.max' => 'Persentase tidak boleh lebih dari 100',
            'position_data.*.hitungan_upah.required_if' => 'Hitungan upah harus diisi ketika memilih upah custom',
            'position_data.*.hitungan_upah.in' => 'Hitungan upah harus salah satu dari: Per Bulan, Per Hari, Per Jam',
            'position_data.*.custom_upah.required_if' => 'Nominal upah custom harus diisi ketika memilih upah custom',

            // Step 5 Messages
            'jenis-perusahaan.required' => 'Jenis perusahaan harus dipilih',
            'jenis-perusahaan.exists' => 'Jenis perusahaan tidak valid',
            'bidang-perusahaan.required' => 'Bidang perusahaan harus dipilih',
            'bidang-perusahaan.exists' => 'Bidang perusahaan tidak valid',
            'resiko.required' => 'Resiko harus diisi',
            'program-bpjs.required' => 'Program BPJS harus diisi',

            // Step 6 Messages
            'aplikasi_pendukung.*.exists' => 'Aplikasi pendukung tidak valid',

            // Step 9 Messages
            'barang_id.required_without' => 'Barang ID harus diisi',
            'barang_id.exists' => 'Barang tidak valid',
            'jumlah.required_without' => 'Jumlah harus diisi',
            'jumlah.integer' => 'Jumlah harus berupa angka',
            'jumlah.min' => 'Jumlah tidak boleh kurang dari 0',
            'masa_pakai.integer' => 'Masa pakai harus berupa angka',
            'masa_pakai.min' => 'Masa pakai minimal 1',
            'harga.numeric' => 'Harga harus berupa angka',
            'harga.min' => 'Harga tidak boleh kurang dari 0',
            'chemicals.*.barang_id.required_with' => 'Barang ID harus diisi',
            'chemicals.*.barang_id.exists' => 'Barang tidak valid',
            'chemicals.*.jumlah.required_with' => 'Jumlah harus diisi',
            'chemicals.*.jumlah.integer' => 'Jumlah harus berupa angka',
            'chemicals.*.jumlah.min' => 'Jumlah tidak boleh kurang dari 0',

            // Step 10 Messages
            'jumlah_kunjungan_operasional.required' => 'Jumlah kunjungan operasional harus diisi',
            'jumlah_kunjungan_operasional.integer' => 'Jumlah kunjungan operasional harus berupa angka',
            'jumlah_kunjungan_operasional.min' => 'Jumlah kunjungan operasional tidak boleh kurang dari 0',
            'bulan_tahun_kunjungan_operasional.required' => 'Periode kunjungan operasional harus dipilih',
            'bulan_tahun_kunjungan_operasional.in' => 'Periode kunjungan operasional harus Bulan atau Tahun',
            'jumlah_kunjungan_tim_crm.required' => 'Jumlah kunjungan tim CRM harus diisi',
            'jumlah_kunjungan_tim_crm.integer' => 'Jumlah kunjungan tim CRM harus berupa angka',
            'jumlah_kunjungan_tim_crm.min' => 'Jumlah kunjungan tim CRM tidak boleh kurang dari 0',
            'bulan_tahun_kunjungan_tim_crm.required' => 'Periode kunjungan tim CRM harus dipilih',
            'bulan_tahun_kunjungan_tim_crm.in' => 'Periode kunjungan tim CRM harus Bulan atau Tahun',

            // Step 11 Messages
            'penagihan.required' => 'Metode penagihan harus diisi',
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

                // Validasi khusus: gaji_saat_cuti hanya wajib jika ada Cuti Melahirkan
                if (
                    $this->ada_cuti === 'Ada' &&
                    in_array('Cuti Melahirkan', $this->cuti) &&
                    empty($this->gaji_saat_cuti)
                ) {
                    $validator->errors()->add('gaji_saat_cuti', 'Gaji saat cuti harus diisi ketika memilih Cuti Melahirkan.');
                }
            }
        });
    }
}