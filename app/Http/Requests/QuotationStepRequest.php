<?php

namespace App\Http\Requests;

use App\Models\QuotationDetail;
use App\Models\QuotationSite;
use App\Models\Umk;
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
                $rules['mulai_kontrak'] = 'required|date|after_or_equal:today';
                $rules['kontrak_selesai'] = 'required|date|after_or_equal:mulai_kontrak';
                $rules['tgl_penempatan'] = 'required|date';
                $rules['top'] = 'required|in:Non TOP,Kurang Dari 7 Hari,Lebih Dari 7 Hari';
                $rules['salary_rule'] = 'required|exists:m_salary_rule,id';
                $rules['jumlah_hari_invoice'] = 'required_if:top,Lebih Dari 7 Hari|integer|min:1';
                $rules['tipe_hari_invoice'] = 'required_if:top,Lebih Dari 7 Hari|string|in:Kerja,Kalender';
                $rules['evaluasi_kontrak'] = 'sometimes|string';
                $rules['durasi_kerjasama'] = 'sometimes|string';
                $rules['durasi_karyawan'] = 'sometimes|string';
                $rules['evaluasi_karyawan'] = 'sometimes|string';
                $rules['ada_cuti'] = 'required|string|in:Ada,Tidak Ada';
                $rules['cuti'] = 'required_if:ada_cuti,Ada|array';
                $rules['cuti.*'] = 'sometimes|string|in:Cuti Tahunan,Cuti Melahirkan,Cuti Kematian,Istri Melahirkan,Cuti Menikah,Cuti Roster,Tidak Ada';
                // $rules['gaji_saat_cuti'] = 'required_if:ada_cuti,Ada|string|in:No Work No Pay,Prorate';
                $rules['prorate'] = 'required_if:gaji_saat_cuti,Prorate|integer|min:0';
                $rules['shift_kerja'] = 'sometimes|string';
                $rules['hari_kerja'] = 'sometimes|string';
                $rules['jam_kerja'] = 'sometimes|string';
                break;
            case 3:
                $rules['headCountData'] = 'required|array';
                $rules['headCountData.*.quotation_site_id'] = 'required|required|integer';
                $rules['headCountData.*.position_id'] = 'required|required|integer';
                $rules['headCountData.*.jumlah_hc'] = 'required|required|integer|min:1';
                $rules['headCountData.*.jabatan_kebutuhan'] = 'required|required|string';
                $rules['headCountData.*.nama_site'] = 'required|required|string';
                break;

            case 4:
                $rules['is_ppn'] = 'sometimes|in:0,1';
                $rules['ppn_pph_dipotong'] = 'sometimes|in:Total Invoice,Management Fee';
                $rules['management_fee_id'] = 'sometimes|exists:m_management_fee,id';
                $rules['persentase'] = 'sometimes|numeric|min:0|max:100';
                $rules['position_data'] = 'required|array|min:1';

                $rules['position_data.*.quotation_detail_id'] = 'required|exists:sl_quotation_detail,id';
                $rules['position_data.*.upah'] = 'required|string|in:UMP,UMK,Custom';
                $rules['position_data.*.hitungan_upah'] = 'required_if:position_data.*.upah,Custom|string|in:Per Bulan,Per Hari,Per Jam';
                $rules['position_data.*.nominal_upah'] = 'required_if:position_data.*.upah,Custom|numeric|min:0';

                $rules['position_data.*.lembur'] = 'sometimes|in:Flat,Tidak Ada,Normatif';
                $rules['position_data.*.nominal_lembur'] = 'required_if:position_data.*.lembur,Flat|numeric|min:0';
                $rules['position_data.*.jenis_bayar_lembur'] = 'required_if:position_data.*.lembur,Flat|in:Per Bulan,Per Hari,Per Jam';
                $rules['position_data.*.jam_per_bulan_lembur'] = 'required_if:position_data.*.jenis_bayar_lembur,Per Jam|integer|min:0 ';
                $rules['position_data.*.lembur_ditagihkan'] = 'required_if:position_data.*.lembur,Flat,Normatif|in:Ditagihkan,Ditagihkan Terpisah';
                $rules['position_data.*.kompensasi'] = 'required|in:Diprovisikan,Ditagihkan,Tidak Ada';
                $rules['position_data.*.thr'] = 'required|in:Diprovisikan,Ditagihkan,Diberikan Langsung,Tidak Ada';

                $rules['position_data.*.tunjangan_holiday'] = 'sometimes|in:Flat,Tidak Ada,Normatif';
                $rules['position_data.*.nominal_tunjangan_holiday'] = 'required_if:position_data.*.tunjangan_holiday,Flat|numeric|min:0';
                $rules['position_data.*.jenis_bayar_tunjangan_holiday'] = 'required_if:position_data.*.tunjangan_holiday,Flat|in:Per Bulan,Per Hari,Per Jam';
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
                $rules['tunjangan_data'] = 'sometimes|array';
                $rules['tunjangan_data.*'] = 'sometimes|array'; // Data per detail_id
                $rules['tunjangan_data.*.*.nama_tunjangan'] = 'required_with:tunjangan_data.*|string|max:255';
                $rules['tunjangan_data.*.*.nominal'] = 'required_with:tunjangan_data.*|numeric|min:0';
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
            'mulai_kontrak.after_or_equal' => 'Tanggal mulai kontrak tidak boleh kurang dari hari ini.',
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

            // Step 4 Messages - GLOBAL DATA
            'is_ppn.in' => 'Status PPN harus 0 atau 1',
            'ppn_pph_dipotong.string' => 'PPN PPH dipotong harus berupa teks',
            'management_fee_id.exists' => 'Management fee tidak valid',
            'persentase.numeric' => 'Persentase harus berupa angka',
            'persentase.min' => 'Persentase tidak boleh kurang dari 0',
            'persentase.max' => 'Persentase tidak boleh lebih dari 100',

            // Step 4 Messages - POSITION DATA
            'position_data.*.quotation_detail_id.required_with' => 'Quotation detail ID harus diisi',
            'position_data.*.quotation_detail_id.exists' => 'Quotation detail ID tidak valid',
            'position_data.*.upah.required_with' => 'Jenis upah harus dipilih',
            'position_data.*.upah.in' => 'Jenis upah harus salah satu dari: UMK, Custom',
            'position_data.*.hitungan_upah.required_if' => 'Hitungan upah harus diisi ketika memilih upah custom',
            'position_data.*.hitungan_upah.in' => 'Hitungan upah harus salah satu dari: Per Bulan, Per Hari, Per Jam',
            'position_data.*.nominal_upah.required_if' => 'Nominal upah custom harus diisi ketika memilih upah custom',
            'position_data.*.nominal_upah.numeric' => 'Nominal upah harus berupa angka',
            'position_data.*.nominal_upah.min' => 'Nominal upah tidak boleh kurang dari 0',
            'position_data.*.thr.required' => 'tunjangan hari raya  harus diisi',
            'position_data.*.kompensasi.required' => 'kompensasi harus diisi',


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

            // Validasi custom untuk step 4
            if ($step == 4) {
                $hasGlobalData = $this->hasAny(['is_ppn', 'ppn_pph_dipotong', 'management_fee_id', 'persentase']);
                $hasPositionData = $this->has('position_data') && !empty($this->position_data);

                if (!$hasGlobalData && !$hasPositionData) {
                    $validator->errors()->add('base', 'Minimal satu data (global data atau position data) harus dikirim untuk step 4.');
                }

                // ✅ VALIDASI BARU: Nominal upah custom minimal 85% dari UMK
                if ($hasPositionData) {
                    foreach ($this->position_data as $index => $positionData) {
                        // Skip jika bukan custom upah
                        if (($positionData['upah'] ?? null) !== 'Custom') {
                            continue;
                        }

                        $nominalUpah = $positionData['nominal_upah'] ?? 0;

                        // Convert string to numeric if needed
                        if (is_string($nominalUpah)) {
                            $nominalUpah = (int) str_replace('.', '', $nominalUpah);
                        }

                        // Get detail untuk ambil site_id
                        $detailId = $positionData['quotation_detail_id'] ?? null;
                        if (!$detailId) {
                            continue;
                        }

                        $detail = QuotationDetail::find($detailId);
                        if (!$detail || !$detail->quotation_site_id) {
                            continue;
                        }

                        // Get UMK dari site
                        $site = QuotationSite::find($detail->quotation_site_id);
                        if (!$site || !$site->kota_id) {
                            continue;
                        }

                        $umk = Umk::byCity($site->kota_id)
                            ->active()
                            ->first();

                        if (!$umk) {
                            continue;
                        }

                        // Hitung minimal 85% dari UMK
                        $minimalUpah = $umk->umk * 0.85;

                        // Validasi
                        if ($nominalUpah < $minimalUpah) {
                            $validator->errors()->add(
                                "position_data.{$index}.nominal_upah",
                                sprintf(
                                    'Nominal upah custom minimal 85%% dari UMK (Rp %s). Minimal: Rp %s',
                                    number_format((float) $umk->umk, 0, ',', '.'),
                                    number_format($minimalUpah, 0, ',', '.')
                                )
                            );

                            \Log::warning("Nominal upah custom kurang dari 85% UMK", [
                                'detail_id' => $detailId,
                                'nominal_upah' => $nominalUpah,
                                'umk' => $umk->umk,
                                'minimal_85_persen' => $minimalUpah,
                                'site_id' => $site->id,
                                'city_id' => $site->kota_id
                            ]);
                        }
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     * Untuk handle field dengan nama yang berbeda di frontend vs backend
     */
    protected function prepareForValidation()
    {
        $step = $this->route('step');

        // Untuk step 4, handle field management_fee_id yang mungkin dikirim sebagai manajemen_fee
        if ($step == 4 && $this->has('manajemen_fee')) {
            $this->merge([
                'management_fee_id' => $this->manajemen_fee
            ]);
        }

        // Untuk step 5, handle field dengan dash
        if ($step == 5) {
            $this->merge([
                'jenis_perusahaan_id' => $this->input('jenis-perusahaan'),
                'bidang_perusahaan_id' => $this->input('bidang-perusahaan')
            ]);
        }

        // Convert empty strings to null untuk field optional
        $this->merge([
            'is_ppn' => $this->is_ppn ?? null,
            'ppn_pph_dipotong' => $this->ppn_pph_dipotong ?? null,
            'management_fee_id' => $this->management_fee_id ?? null,
            'persentase' => $this->persentase ?? null,
        ]);
    }
}