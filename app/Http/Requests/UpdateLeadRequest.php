<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;
use App\Rules\UniqueCompanyStrict;

class UpdateLeadRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'nama_perusahaan' => FluentRule::string('Nama Perusahaan')
                ->nullable()
                ->min(3)
                ->max(100)
                ->rule(new UniqueCompanyStrict($this->route('id'))),

            'pic' => FluentRule::string('PIC')->required(),
            'branch' => FluentRule::numeric('Branch')->required(),
            'kebutuhan' => FluentRule::array(label: 'Kebutuhan')->required()->min(1),
            'provinsi' => FluentRule::numeric('Provinsi')->required(),
            'kota' => FluentRule::numeric('Kota')->required(),

            'telp_perusahaan' => FluentRule::string('Telp Perusahaan')->nullable(),
            'jenis_perusahaan' => FluentRule::numeric('Jenis Perusahaan')->nullable(),
            'bidang_perusahaan' => FluentRule::numeric('Bidang Perusahaan')->nullable(),
            'platform' => FluentRule::numeric('Platform')->nullable(),
            'alamat_perusahaan' => FluentRule::string('Alamat Perusahaan')->nullable(),
            'jabatan_pic' => FluentRule::field('Jabatan PIC')->nullable(),
            'no_telp' => FluentRule::string('No Telp')->nullable(),
            'email' => FluentRule::email('Email')->nullable(),
            'pma' => FluentRule::string('PMA')->nullable(),
            'detail_leads' => FluentRule::string('Detail Leads')->nullable(),
            'kecamatan' => FluentRule::numeric('Kecamatan')->nullable(),
            'kelurahan' => FluentRule::numeric('Kelurahan')->nullable(),
            'benua' => FluentRule::numeric('Benua')->nullable(),
            'negara' => FluentRule::numeric('Negara')->nullable(),

            // Multi PIC (opsional). Kolom datar pic/jabatan_pic/no_telp/email
            // tetap diisi dari PIC pertama untuk kompatibilitas mundur.
            'pics' => FluentRule::array(label: 'PIC')->nullable()
                ->each([
                    'pic'         => FluentRule::string('Nama PIC')->required(),
                    'jabatan_pic' => FluentRule::field('Jabatan PIC')->nullable(),
                    'no_telp'     => FluentRule::string('No Telp')->nullable(),
                    'email'       => FluentRule::email('Email')->nullable(),
                ]),

            'assignments' => FluentRule::array(label: 'Assignments')->nullable()
                ->each([
                    'tim_sales_d_id' => FluentRule::integer()->required()->exists('m_tim_sales_d', 'id'),
                    'kebutuhan_ids' => FluentRule::array()->required()->min(1)->each(
                        FluentRule::integer()->exists('m_kebutuhan', 'id')
                    ),
                ]),
        ];
    }

    public function messages(): array
    {
        return [
            'kebutuhan.required' => 'Kebutuhan harus dipilih minimal 1',
            'kebutuhan.array' => 'Kebutuhan harus berupa array',
            'kebutuhan.min' => 'Kebutuhan harus dipilih minimal 1',
        ];
    }
}