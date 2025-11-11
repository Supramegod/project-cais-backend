<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qasal_id' => 'required|exists:sl_quotation,id',
            'qtujuan_id' => 'required|exists:sl_quotation,id',
            'alasan' => 'sometimes|string|max:500'
        ];
    }

    public function messages(): array
    {
        return [
            'qasal_id.required' => 'Quotation asal harus dipilih',
            'qasal_id.exists' => 'Quotation asal tidak ditemukan',
            'qtujuan_id.required' => 'Quotation tujuan harus dipilih',
            'qtujuan_id.exists' => 'Quotation tujuan tidak ditemukan',
        ];
    }
}