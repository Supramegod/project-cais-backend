<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationDetailRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quotation_id' => 'required|exists:sl_quotation,id',
            'site_id' => 'required|exists:sl_quotation_site,id',
            'position_id' => 'required|exists:mysqlhris.m_position,id',
            'jumlah_hc' => 'required|integer|min:1',
            
            // Untuk tambahan requirement
            'requirement' => 'sometimes|string|max:500',
            
            // Untuk tunjangan
            'namaTunjangan' => 'sometimes|string|max:255',
            'nominalTunjangan' => 'sometimes|numeric|min:0',
            
            // Untuk PIC
            'nama' => 'sometimes|string|max:255',
            'jabatan' => 'sometimes|exists:m_jabatan_pic,id',
            'no_telp' => 'sometimes|string|max:20',
            'email' => 'sometimes|email|max:255',
            
            // Untuk training
            'training_id' => 'sometimes|array',
            'training_id.*' => 'exists:m_training,id',
            
            // Untuk barang/kaporlap
            'barang' => 'sometimes|exists:m_barang,id',
            'jumlah' => 'sometimes|integer|min:0',
            'harga' => 'sometimes|numeric|min:0',
            'masa_pakai' => 'sometimes|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'quotation_id.required' => 'Quotation ID harus diisi',
            'quotation_id.exists' => 'Quotation tidak ditemukan',
            'site_id.required' => 'Site ID harus diisi',
            'site_id.exists' => 'Site tidak ditemukan',
            'position_id.required' => 'Posisi harus dipilih',
            'position_id.exists' => 'Posisi tidak valid',
            'jumlah_hc.required' => 'Jumlah HC harus diisi',
            'jumlah_hc.integer' => 'Jumlah HC harus berupa angka',
            'jumlah_hc.min' => 'Jumlah HC minimal 1',
        ];
    }
}