<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class QuotationReferenceRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe_quotation' => FluentRule::string()->required()->in(['baru', 'revisi', 'rekontrak', 'addendum']),
        ];
    }

    public function messages(): array
    {
        return [
            'tipe_quotation.required' => 'Tipe quotation wajib diisi',
            'tipe_quotation.in' => 'Tipe quotation harus salah satu dari: baru, revisi, rekontrak, addendum',
        ];
    }
}
