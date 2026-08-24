<?php

namespace App\Http\Requests\Quotation;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class QuotationApproveRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => FluentRule::integer()->required()->exists('sl_quotation', 'id'),
            'is_approved' => FluentRule::boolean()->required(),
            'alasan' => FluentRule::string()->requiredIf('is_approved', 'false')->max(500),
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Quotation ID harus diisi',
            'id.exists' => 'Quotation tidak ditemukan',
            'is_approved.required' => 'Status approve harus diisi',
            'is_approved.boolean' => 'Status approve harus true atau false',
            'alasan.required_if' => 'Alasan harus diisi ketika menolak',
        ];
    }

    /**
     * Ambil ID dari route parameter dan masukkan ke data request.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }
}