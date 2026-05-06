<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class QuotationStoreRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tipe_quotation = $this->route('tipe_quotation') ?? 'baru';

        $rules = [
            'perusahaan_id'          => FluentRule::integer()->required()->exists('sl_leads'),
            'entitas'                => FluentRule::integer()->required()->exists('mysqlhris.m_company'),
            'layanan'                => FluentRule::integer()->required()->exists('m_kebutuhan'),
            'jumlah_site'            => FluentRule::string()->required()->in(['Single Site', 'Multi Site']),
            'quotation_referensi_id' => FluentRule::integer()->nullable()->exists('sl_quotation'),
        ];

        if ($this->jumlah_site == 'Single Site') {
            if ($this->has('nama_site') && !empty($this->nama_site)) {
                $rules['nama_site']  = FluentRule::string()->required()->max(255);
                $rules['provinsi']   = FluentRule::integer()->required()->exists('mysqlhris.m_province');
                $rules['kota']       = FluentRule::integer()->required()->exists('mysqlhris.m_city');
                $rules['penempatan'] = FluentRule::string()->required()->max(255);
            } else {
                $rules['nama_site']  = FluentRule::string()->nullable();
                $rules['provinsi']   = FluentRule::integer()->nullable();
                $rules['kota']       = FluentRule::integer()->nullable();
                $rules['penempatan'] = FluentRule::string()->nullable();
            }
        }

        if ($this->jumlah_site == 'Multi Site') {
            if ($this->has('multisite') && !empty($this->multisite)) {
                $siteCount = count($this->multisite);

                $rules['multisite'] = FluentRule::array()->required()->min(1)->size($siteCount)->children([
                    '*' => FluentRule::string()->required()->max(255),
                ]);
                $rules['provinsi_multi'] = FluentRule::array()->required()->min(1)->size($siteCount)->children([
                    '*' => FluentRule::integer()->required()->exists('mysqlhris.m_province'),
                ]);
                $rules['kota_multi'] = FluentRule::array()->required()->min(1)->size($siteCount)->children([
                    '*' => FluentRule::integer()->required()->exists('mysqlhris.m_city'),
                ]);
                $rules['penempatan_multi'] = FluentRule::array()->required()->min(1)->size($siteCount)->children([
                    '*' => FluentRule::string()->required()->max(255),
                ]);
            } else {
                $rules['multisite']        = FluentRule::array()->nullable();
                $rules['provinsi_multi']   = FluentRule::array()->nullable();
                $rules['kota_multi']       = FluentRule::array()->nullable();
                $rules['penempatan_multi'] = FluentRule::array()->nullable();
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        $tipe_quotation = $this->route('tipe_quotation') ?? 'baru';

        $messages = [
            'perusahaan_id.required' => 'Perusahaan wajib dipilih',
            'perusahaan_id.exists'   => 'Perusahaan tidak valid',
            'entitas.required'       => 'Entitas wajib dipilih',
            'entitas.exists'         => 'Entitas tidak valid',
            'layanan.required'       => 'Layanan wajib dipilih',
            'layanan.exists'         => 'Layanan tidak valid',
            'jumlah_site.required'   => 'Jumlah site wajib dipilih',
            'jumlah_site.in'         => 'Jumlah site harus Single Site atau Multi Site',
            'site_existing_allowed'  => 'Site ini sudah ada di database. Data dari referensi akan disalin ke site ini.',
            'site_new_required'      => 'Untuk quotation baru tanpa referensi, data site wajib diisi.',
        ];

        if (in_array($tipe_quotation, ['revisi', 'rekontrak'])) {
            $messages['quotation_referensi_id.required'] = 'Quotation referensi wajib dipilih untuk revisi/rekontrak';
            $messages['quotation_referensi_id.exists']   = 'Quotation referensi tidak valid';
            $messages['site_count_mismatch']             = 'Karena jumlah site berbeda dengan referensi, data site baru wajib diisi.';
        }

        if ($this->jumlah_site == 'Multi Site' && $this->has('multisite')) {
            $messages['multisite.size']          = 'Jumlah data multisite harus sama dengan data provinsi, kota, dan penempatan';
            $messages['provinsi_multi.size']     = 'Jumlah provinsi multisite harus sama dengan data site';
            $messages['kota_multi.size']         = 'Jumlah kota multisite harus sama dengan data site';
            $messages['penempatan_multi.size']   = 'Jumlah penempatan multisite harus sama dengan data site';
        }

        if ($this->has('multisite')) {
            $messages['multisite.*.required']       = 'Nama site multisite wajib diisi';
            $messages['provinsi_multi.*.required']   = 'Provinsi multisite wajib dipilih';
            $messages['provinsi_multi.*.exists']     = 'Provinsi multisite tidak valid';
            $messages['kota_multi.*.required']       = 'Kota multisite wajib dipilih';
            $messages['kota_multi.*.exists']         = 'Kota multisite tidak valid';
            $messages['penempatan_multi.*.required'] = 'Penempatan multisite wajib diisi';
        }

        return $messages;
    }

    public function attributes(): array
    {
        return [
            'multisite.*'       => 'nama site',
            'provinsi_multi.*'  => 'provinsi',
            'kota_multi.*'      => 'kota',
            'penempatan_multi.*'=> 'penempatan',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->route('tipe_quotation')) {
            $this->merge([
                'tipe_quotation' => $this->route('tipe_quotation')
            ]);
        }

        $tipe_quotation = $this->tipe_quotation ?? 'baru';

        if ($this->jumlah_site == 'Multi Site') {
            $this->merge([
                'multisite'        => $this->multisite ?? [],
                'provinsi_multi'   => $this->provinsi_multi ?? [],
                'kota_multi'       => $this->kota_multi ?? [],
                'penempatan_multi' => $this->penempatan_multi ?? [],
            ]);
        } else {
            $this->merge([
                'multisite'        => null,
                'provinsi_multi'   => null,
                'kota_multi'       => null,
                'penempatan_multi' => null,
            ]);
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $tipe_quotation = $this->tipe_quotation ?? 'baru';
            $jumlah_site = $this->jumlah_site;
            $perusahaan_id = $this->perusahaan_id;
            $hasReferensi = $this->has('quotation_referensi_id') && !empty($this->quotation_referensi_id);

            if ($hasReferensi) {
                $referensiExists = \App\Models\Quotation::where('id', $this->quotation_referensi_id)
                    ->withoutTrashed()
                    ->exists();

                if (!$referensiExists) {
                    $validator->errors()->add('quotation_referensi_id', 'Quotation referensi tidak ditemukan atau telah dihapus');
                }
            }

            if (in_array($tipe_quotation, ['revisi', 'rekontrak']) && !$hasReferensi) {
                $validator->errors()->add('quotation_referensi_id', 'Quotation referensi wajib dipilih untuk ' . $tipe_quotation);
            }

            if ($jumlah_site == 'Single Site') {
                $hasSiteField = $this->has('nama_site') && !empty($this->nama_site);
                $hasProvinceField = $this->has('provinsi') && !empty($this->provinsi);
                $hasCityField = $this->has('kota') && !empty($this->kota);
                $hasPenempatanField = $this->has('penempatan') && !empty($this->penempatan);

                $siteFieldsCount = ($hasSiteField ? 1 : 0) + ($hasProvinceField ? 1 : 0) +
                    ($hasCityField ? 1 : 0) + ($hasPenempatanField ? 1 : 0);

                if ($siteFieldsCount > 0 && $siteFieldsCount < 4) {
                    $validator->errors()->add('nama_site', 'Untuk membuat/menggunakan site, semua field site (nama_site, provinsi, kota, penempatan) harus diisi lengkap');
                }
            }

            if ($jumlah_site == 'Multi Site' && $this->has('multisite') && !empty($this->multisite)) {
                $siteCount = count($this->multisite);
                $provinceCount = count($this->provinsi_multi ?? []);
                $cityCount = count($this->kota_multi ?? []);
                $penempatanCount = count($this->penempatan_multi ?? []);

                if ($siteCount !== $provinceCount || $siteCount !== $cityCount || $siteCount !== $penempatanCount) {
                    $validator->errors()->add('multisite', 'Jumlah data multisite, provinsi, kota, dan penempatan harus sama');
                }
            }

            if ($tipe_quotation === 'baru' && !$hasReferensi) {
                $hasSiteData = false;

                if ($jumlah_site == 'Single Site') {
                    $hasSiteData = $this->has('nama_site') && !empty($this->nama_site);
                } else if ($jumlah_site == 'Multi Site') {
                    $hasSiteData = $this->has('multisite') && !empty($this->multisite);
                }

                if (!$hasSiteData) {
                    $validator->errors()->add('nama_site', 'Data site wajib diisi untuk quotation baru tanpa referensi');
                }
            }

            if (in_array($tipe_quotation, ['revisi', 'rekontrak']) && $hasReferensi) {
                $referensi = \App\Models\Quotation::find($this->quotation_referensi_id);

                if ($referensi) {
                    if ($this->jumlah_site !== $referensi->jumlah_site) {
                        if ($this->jumlah_site == 'Single Site') {
                            if (!$this->has('nama_site') || empty($this->nama_site)) {
                                $validator->errors()->add('nama_site', 'Karena jumlah site berbeda dengan referensi, data site baru wajib diisi');
                            }
                        } else if ($this->jumlah_site == 'Multi Site') {
                            if (!$this->has('multisite') || empty($this->multisite)) {
                                $validator->errors()->add('multisite', 'Karena jumlah site berbeda dengan referensi, data multisite baru wajib diisi');
                            }
                        }
                    }
                }
            }
        });
    }
}