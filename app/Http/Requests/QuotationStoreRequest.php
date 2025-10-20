<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'perusahaan_id' => 'required|exists:sl_leads,id',
            'entitas' => 'required|exists:mysqlhris.m_company,id',
            'layanan' => 'required|exists:m_kebutuhan,id',
            'jumlah_site' => 'required|string|in:Single Site,Multi Site',
        ];

        // Rules untuk Single Site
        if ($this->jumlah_site == 'Single Site') {
            $rules['nama_site'] = 'required|string|max:255';
            $rules['provinsi'] = 'required|exists:mysqlhris.m_province,id';
            $rules['kota'] = 'required|exists:mysqlhris.m_city,id';
            $rules['penempatan'] = 'required|string|max:255';
        }

        // Rules untuk Multi Site
        if ($this->jumlah_site == 'Multi Site') {
            $rules['multisite'] = 'required|array|min:1';
            $rules['multisite.*'] = 'required|string|max:255';
            $rules['provinsi_multi'] = 'required|array|min:1';
            $rules['provinsi_multi.*'] = 'required|exists:mysqlhris.m_province,id';
            $rules['kota_multi'] = 'required|array|min:1';
            $rules['kota_multi.*'] = 'required|exists:mysqlhris.m_city,id';
            $rules['penempatan_multi'] = 'required|array|min:1';
            $rules['penempatan_multi.*'] = 'required|string|max:255';
        }

        // Validasi ukuran array harus sama untuk multi site
        if ($this->jumlah_site == 'Multi Site') {
            $rules['multisite'] = 'required|array|min:1|size:' . count($this->provinsi_multi ?? []);
            $rules['provinsi_multi'] = 'required|array|min:1|size:' . count($this->multisite ?? []);
            $rules['kota_multi'] = 'required|array|min:1|size:' . count($this->multisite ?? []);
            $rules['penempatan_multi'] = 'required|array|min:1|size:' . count($this->multisite ?? []);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'perusahaan_id.required' => 'Perusahaan wajib dipilih',
            'perusahaan_id.exists' => 'Perusahaan tidak valid',
            'entitas.required' => 'Entitas wajib dipilih',
            'entitas.exists' => 'Entitas tidak valid',
            'layanan.required' => 'Layanan wajib dipilih',
            'layanan.exists' => 'Layanan tidak valid',
            'jumlah_site.required' => 'Jumlah site wajib dipilih',
            'jumlah_site.in' => 'Jumlah site harus Single Site atau Multi Site',
            
            'nama_site.required_if' => 'Nama site wajib diisi untuk single site',
            'provinsi.required_if' => 'Provinsi wajib dipilih untuk single site',
            'kota.required_if' => 'Kota wajib dipilih untuk single site',
            'penempatan.required_if' => 'Penempatan wajib diisi untuk single site',
            
            'multisite.required_if' => 'Data multisite wajib diisi',
            'multisite.size' => 'Jumlah data multisite harus sama dengan data provinsi, kota, dan penempatan',
            'provinsi_multi.required_if' => 'Provinsi multisite wajib dipilih',
            'provinsi_multi.size' => 'Jumlah provinsi multisite harus sama dengan data site',
            'kota_multi.required_if' => 'Kota multisite wajib dipilih',
            'kota_multi.size' => 'Jumlah kota multisite harus sama dengan data site',
            'penempatan_multi.required_if' => 'Penempatan multisite wajib diisi',
            'penempatan_multi.size' => 'Jumlah penempatan multisite harus sama dengan data site',
            
            'multisite.*.required' => 'Nama site multisite wajib diisi',
            'provinsi_multi.*.required' => 'Provinsi multisite wajib dipilih',
            'provinsi_multi.*.exists' => 'Provinsi multisite tidak valid',
            'kota_multi.*.required' => 'Kota multisite wajib dipilih',
            'kota_multi.*.exists' => 'Kota multisite tidak valid',
            'penempatan_multi.*.required' => 'Penempatan multisite wajib diisi',
        ];
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

    /**
     * Prepare data untuk validasi
     */
    protected function prepareForValidation()
    {
        // Pastikan array untuk multi site selalu ada (meski empty)
        if ($this->jumlah_site == 'Multi Site') {
            $this->merge([
                'multisite' => $this->multisite ?? [],
                'provinsi_multi' => $this->provinsi_multi ?? [],
                'kota_multi' => $this->kota_multi ?? [],
                'penempatan_multi' => $this->penempatan_multi ?? [],
            ]);
        }
    }
}