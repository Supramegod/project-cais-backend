<?php

namespace App\Http\Requests;

class QuotationStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Ambil tipe_quotation dari route parameter
        $tipe_quotation = $this->route('tipe_quotation') ?? 'baru';

        $rules = [
            'perusahaan_id' => 'required|exists:sl_leads,id',
            'entitas' => 'required|exists:mysqlhris.m_company,id',
            'layanan' => 'required|exists:m_kebutuhan,id',
            'jumlah_site' => 'required|string|in:Single Site,Multi Site',
        ];

        // Conditional validation untuk referensi berdasarkan tipe_quotation
        if (in_array($tipe_quotation, ['revisi', 'rekontrak'])) {
            $rules['quotation_referensi_id'] = 'required|exists:sl_quotation,id';
        } else {
            // Untuk quotation baru, quotation_referensi_id tidak diperlukan
            $rules['quotation_referensi_id'] = 'nullable';
        }

        // Untuk quotation BARU atau jika ada request site baru untuk REVISI/REKONTRAK
        // Kita perlu cek apakah user ingin membuat site baru atau menggunakan site dari referensi

        // SINGLE SITE: Wajib hanya jika jumlah_site = 'Single Site' 
        // DAN (tipe_quotation = 'baru' ATAU ada nama_site dalam request)
        if ($this->jumlah_site == 'Single Site') {
            if ($tipe_quotation === 'baru') {
                // Quotation baru HARUS punya site
                $rules['nama_site'] = 'required|string|max:255';
                $rules['provinsi'] = 'required|exists:mysqlhris.m_province,id';
                $rules['kota'] = 'required|exists:mysqlhris.m_city,id';
                $rules['penempatan'] = 'required|string|max:255';
            } else {
                // Untuk revisi/rekontrak, site baru optional
                // Jika ada salah satu field site, maka semua harus ada
                if ($this->has('nama_site') && !empty($this->nama_site)) {
                    $rules['nama_site'] = 'required|string|max:255';
                    $rules['provinsi'] = 'required|exists:mysqlhris.m_province,id';
                    $rules['kota'] = 'required|exists:mysqlhris.m_city,id';
                    $rules['penempatan'] = 'required|string|max:255';
                } else {
                    // Jika tidak ada site baru, maka field site tidak diperlukan
                    $rules['nama_site'] = 'nullable';
                    $rules['provinsi'] = 'nullable';
                    $rules['kota'] = 'nullable';
                    $rules['penempatan'] = 'nullable';
                }
            }
        }

        // MULTI SITE: Wajib hanya jika jumlah_site = 'Multi Site'
        // DAN (tipe_quotation = 'baru' ATAU ada multisite dalam request)
        if ($this->jumlah_site == 'Multi Site') {
            if ($tipe_quotation === 'baru') {
                // Quotation baru HARUS punya site
                $rules['multisite'] = 'required|array|min:1';
                $rules['multisite.*'] = 'required|string|max:255';
                $rules['provinsi_multi'] = 'required|array|min:1';
                $rules['provinsi_multi.*'] = 'required|exists:mysqlhris.m_province,id';
                $rules['kota_multi'] = 'required|array|min:1';
                $rules['kota_multi.*'] = 'required|exists:mysqlhris.m_city,id';
                $rules['penempatan_multi'] = 'required|array|min:1';
                $rules['penempatan_multi.*'] = 'required|string|max:255';
            } else {
                // Untuk revisi/rekontrak, site baru optional
                // Jika ada array multisite, maka semua array harus ada
                if ($this->has('multisite') && !empty($this->multisite)) {
                    $rules['multisite'] = 'required|array|min:1';
                    $rules['multisite.*'] = 'required|string|max:255';
                    $rules['provinsi_multi'] = 'required|array|min:1';
                    $rules['provinsi_multi.*'] = 'required|exists:mysqlhris.m_province,id';
                    $rules['kota_multi'] = 'required|array|min:1';
                    $rules['kota_multi.*'] = 'required|exists:mysqlhris.m_city,id';
                    $rules['penempatan_multi'] = 'required|array|min:1';
                    $rules['penempatan_multi.*'] = 'required|string|max:255';
                } else {
                    // Jika tidak ada site baru, maka array tidak diperlukan
                    $rules['multisite'] = 'nullable|array';
                    $rules['provinsi_multi'] = 'nullable|array';
                    $rules['kota_multi'] = 'nullable|array';
                    $rules['penempatan_multi'] = 'nullable|array';
                }
            }
        }

        // Validasi ukuran array harus sama untuk multi site (jika ada)
        if ($this->jumlah_site == 'Multi Site' && $this->has('multisite') && !empty($this->multisite)) {
            $siteCount = count($this->multisite);
            $rules['multisite'] = 'required|array|min:1|size:' . $siteCount;
            $rules['provinsi_multi'] = 'required|array|min:1|size:' . $siteCount;
            $rules['kota_multi'] = 'required|array|min:1|size:' . $siteCount;
            $rules['penempatan_multi'] = 'required|array|min:1|size:' . $siteCount;
        }

        return $rules;
    }

    public function messages(): array
    {
        $tipe_quotation = $this->route('tipe_quotation') ?? 'baru';

        $messages = [
            'perusahaan_id.required' => 'Perusahaan wajib dipilih',
            'perusahaan_id.exists' => 'Perusahaan tidak valid',
            'entitas.required' => 'Entitas wajib dipilih',
            'entitas.exists' => 'Entitas tidak valid',
            'layanan.required' => 'Layanan wajib dipilih',
            'layanan.exists' => 'Layanan tidak valid',
            'jumlah_site.required' => 'Jumlah site wajib dipilih',
            'jumlah_site.in' => 'Jumlah site harus Single Site atau Multi Site',
        ];

        // Pesan untuk revisi/rekontrak
        if (in_array($tipe_quotation, ['revisi', 'rekontrak'])) {
            $messages['quotation_referensi_id.required'] = 'Quotation referensi wajib dipilih untuk revisi/rekontrak';
            $messages['quotation_referensi_id.exists'] = 'Quotation referensi tidak valid';
        }

        // Pesan untuk quotation baru
        if ($tipe_quotation === 'baru') {
            if ($this->jumlah_site == 'Single Site') {
                $messages['nama_site.required'] = 'Nama site wajib diisi untuk quotation baru';
                $messages['provinsi.required'] = 'Provinsi wajib dipilih untuk quotation baru';
                $messages['kota.required'] = 'Kota wajib dipilih untuk quotation baru';
                $messages['penempatan.required'] = 'Penempatan wajib diisi untuk quotation baru';
            } else if ($this->jumlah_site == 'Multi Site') {
                $messages['multisite.required'] = 'Data multisite wajib diisi untuk quotation baru';
                $messages['provinsi_multi.required'] = 'Provinsi multisite wajib dipilih untuk quotation baru';
                $messages['kota_multi.required'] = 'Kota multisite wajib dipilih untuk quotation baru';
                $messages['penempatan_multi.required'] = 'Penempatan multisite wajib diisi untuk quotation baru';
            }
        }

        // Pesan untuk multisite
        if ($this->jumlah_site == 'Multi Site' && $this->has('multisite')) {
            $messages['multisite.size'] = 'Jumlah data multisite harus sama dengan data provinsi, kota, dan penempatan';
            $messages['provinsi_multi.size'] = 'Jumlah provinsi multisite harus sama dengan data site';
            $messages['kota_multi.size'] = 'Jumlah kota multisite harus sama dengan data site';
            $messages['penempatan_multi.size'] = 'Jumlah penempatan multisite harus sama dengan data site';
        }

        // Pesan untuk array elements
        if ($this->has('multisite')) {
            $messages['multisite.*.required'] = 'Nama site multisite wajib diisi';
            $messages['provinsi_multi.*.required'] = 'Provinsi multisite wajib dipilih';
            $messages['provinsi_multi.*.exists'] = 'Provinsi multisite tidak valid';
            $messages['kota_multi.*.required'] = 'Kota multisite wajib dipilih';
            $messages['kota_multi.*.exists'] = 'Kota multisite tidak valid';
            $messages['penempatan_multi.*.required'] = 'Penempatan multisite wajib diisi';
        }

        return $messages;
    }

    public function attributes(): array
    {
        return [
            'multisite.*' => 'nama site',
            'provinsi_multi.*' => 'provinsi',
            'kota_multi.*' => 'kota',
            'penempatan_multi.*' => 'penempatan',
        ];
    }

    protected function prepareForValidation()
    {
        // ✅ Merge tipe_quotation dari route parameter
        if ($this->route('tipe_quotation')) {
            $this->merge([
                'tipe_quotation' => $this->route('tipe_quotation')
            ]);
        }

        $tipe_quotation = $this->tipe_quotation ?? 'baru';

        // ✅ Untuk quotation baru, hapus quotation_referensi_id jika ada
        if ($tipe_quotation === 'baru' && $this->has('quotation_referensi_id')) {
            $this->merge(['quotation_referensi_id' => null]);
        }

        // ✅ Pastikan array untuk multi site selalu ada (meski empty)
        if ($this->jumlah_site == 'Multi Site') {
            $this->merge([
                'multisite' => $this->multisite ?? [],
                'provinsi_multi' => $this->provinsi_multi ?? [],
                'kota_multi' => $this->kota_multi ?? [],
                'penempatan_multi' => $this->penempatan_multi ?? [],
            ]);
        } else {
            // Untuk single site, hapus field multi site jika ada
            $this->merge([
                'multisite' => null,
                'provinsi_multi' => null,
                'kota_multi' => null,
                'penempatan_multi' => null,
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tipe_quotation = $this->tipe_quotation ?? 'baru';
            $jumlah_site = $this->jumlah_site;

            // Validasi tambahan untuk revisi/rekontrak dengan site baru
            if (in_array($tipe_quotation, ['revisi', 'rekontrak'])) {
                // Jika ada quotation_referensi_id, pastikan valid
                if ($this->has('quotation_referensi_id') && !empty($this->quotation_referensi_id)) {
                    // Cek apakah quotation referensi exist dan tidak dihapus
                    $referensiExists = \App\Models\Quotation::where('id', $this->quotation_referensi_id)
                        ->withoutTrashed()
                        ->exists();

                    if (!$referensiExists) {
                        $validator->errors()->add('quotation_referensi_id', 'Quotation referensi tidak ditemukan atau telah dihapus');
                    }
                }

                // Untuk revisi/rekontrak dengan single site: jika ada salah satu field site, semua harus ada
                if ($jumlah_site == 'Single Site') {
                    $hasSiteField = $this->has('nama_site') && !empty($this->nama_site);
                    $hasProvinceField = $this->has('provinsi') && !empty($this->provinsi);
                    $hasCityField = $this->has('kota') && !empty($this->kota);
                    $hasPenempatanField = $this->has('penempatan') && !empty($this->penempatan);

                    $siteFieldsCount = ($hasSiteField ? 1 : 0) + ($hasProvinceField ? 1 : 0) +
                        ($hasCityField ? 1 : 0) + ($hasPenempatanField ? 1 : 0);

                    if ($siteFieldsCount > 0 && $siteFieldsCount < 4) {
                        $validator->errors()->add('nama_site', 'Untuk membuat site baru pada revisi/rekontrak, semua field site (nama_site, provinsi, kota, penempatan) harus diisi');
                    }
                }

                // Untuk revisi/rekontrak dengan multi site: validasi konsistensi array
                if ($jumlah_site == 'Multi Site' && $this->has('multisite') && !empty($this->multisite)) {
                    $siteCount = count($this->multisite);
                    $provinceCount = count($this->provinsi_multi ?? []);
                    $cityCount = count($this->kota_multi ?? []);
                    $penempatanCount = count($this->penempatan_multi ?? []);

                    if ($siteCount !== $provinceCount || $siteCount !== $cityCount || $siteCount !== $penempatanCount) {
                        $validator->errors()->add('multisite', 'Jumlah data multisite, provinsi, kota, dan penempatan harus sama');
                    }
                }
            }
        });
    }
}