<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuotationApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|exists:sl_quotation,id',
            'approve' => 'required|boolean',
            'alasan' => 'required_if:approve,false|string|max:500'
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Quotation ID harus diisi',
            'id.exists' => 'Quotation tidak ditemukan',
            'approve.required' => 'Status approve harus diisi',
            'approve.boolean' => 'Status approve harus true atau false',
            'alasan.required_if' => 'Alasan harus diisi ketika menolak',
        ];
    }
}