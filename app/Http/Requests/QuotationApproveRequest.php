<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class QuotationApproveRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => FluentRule::integer()->required()->exists('sl_quotation'),
            'approve' => FluentRule::boolean()->required(),
            'alasan' => FluentRule::string()->requiredIf('approve', 'false')->max(500),
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