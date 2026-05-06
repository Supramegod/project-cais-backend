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
            'quotation_id'      => FluentRule::make()->required()->exists('sl_quotation', 'id'),
            'site_id'           => FluentRule::make()->required()->exists('sl_quotation_site', 'id'),
            'position_id'       => FluentRule::make()->required()->exists('mysqlhris.m_position', 'id'),
            'jumlah_hc'         => FluentRule::integer()->required()->min(1),

            // Untuk tambahan requirement
            'requirement'       => FluentRule::string()->sometimes()->max(500),

            // Untuk tunjangan
            'namaTunjangan'     => FluentRule::string()->sometimes()->max(255),
            'nominalTunjangan'  => FluentRule::numeric()->sometimes()->min(0),

            // Untuk PIC
            'nama'              => FluentRule::string()->sometimes()->max(255),
            'jabatan'           => FluentRule::make()->sometimes()->exists('m_jabatan_pic', 'id'),
            'no_telp'           => FluentRule::string()->sometimes()->max(20),
            'email'             => FluentRule::make()->sometimes()->email()->max(255),

            // Untuk training
            'training_id'       => FluentRule::array()->sometimes(),
            'training_id.*'     => FluentRule::make()->exists('m_training', 'id'),

            // Untuk barang/kaporlap
            'barang'            => FluentRule::make()->sometimes()->exists('m_barang', 'id'),
            'jumlah'            => FluentRule::integer()->sometimes()->min(0),
            'harga'             => FluentRule::numeric()->sometimes()->min(0),
            'masa_pakai'        => FluentRule::integer()->sometimes()->min(1),
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