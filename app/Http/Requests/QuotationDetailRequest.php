<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class QuotationDetailRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quotation_id'     => FluentRule::integer()->required()->exists('sl_quotation', 'id'),
            'site_id'          => FluentRule::integer()->required()->exists('sl_quotation_site', 'id'),
            'position_id'      => FluentRule::integer()->required()->exists('mysqlhris.m_position', 'id'),
            'jumlah_hc'        => FluentRule::integer()->required()->min(1),

            'requirement'      => FluentRule::string()->sometimes()->max(500),
            'namaTunjangan'    => FluentRule::string()->sometimes()->max(255),
            'nominalTunjangan' => FluentRule::numeric()->sometimes()->min(0),
            'nama'             => FluentRule::string()->sometimes()->max(255),
            'jabatan'          => FluentRule::integer()->sometimes()->exists('m_jabatan_pic', 'id'),
            'no_telp'          => FluentRule::string()->sometimes()->max(20),
            'email'            => FluentRule::string()->sometimes()->email()->max(255),

            'training_id'      => FluentRule::array()->sometimes()->children([
                '*' => FluentRule::integer()->exists('m_training', 'id'),
            ]),

            'barang'           => FluentRule::integer()->sometimes()->exists('m_barang'),
            'jumlah'           => FluentRule::integer()->sometimes()->min(0),
            'harga'            => FluentRule::numeric()->sometimes()->min(0),
            'masa_pakai'       => FluentRule::integer()->sometimes()->min(1),
        ];
    }

    public function messages(): array
    {
        return [
            'quotation_id.required' => 'Quotation ID harus diisi',
            'quotation_id.exists'   => 'Quotation tidak ditemukan',
            'site_id.required'      => 'Site ID harus diisi',
            'site_id.exists'        => 'Site tidak ditemukan',
            'position_id.required'  => 'Posisi harus dipilih',
            'position_id.exists'    => 'Posisi tidak valid',
            'jumlah_hc.required'    => 'Jumlah HC harus diisi',
            'jumlah_hc.integer'     => 'Jumlah HC harus berupa angka',
            'jumlah_hc.min'         => 'Jumlah HC minimal 1',
        ];
    }
}