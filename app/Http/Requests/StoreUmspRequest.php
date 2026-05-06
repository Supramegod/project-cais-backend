<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreUmspRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'province_id' => FluentRule::integer()->required()->min(1),
            'province_name' => FluentRule::string()->required()->max(150),
            'sektor' => FluentRule::string()->required()->max(100),
            'umsp' => FluentRule::numeric()->required()->min(1),
            'tgl_berlaku' => FluentRule::string()->required()->dateFormat('Y-m-d'),
            'sumber' => FluentRule::string()->required()->max(500),
        ];
    }

    public function messages(): array
    {
        return [
            'province_id.required' => 'Province ID wajib diisi.',
            'province_name.required' => 'Nama provinsi wajib diisi.',
            'sektor.required' => 'Nama sektor wajib diisi.',
            'sektor.max' => 'Nama sektor maksimal 100 karakter.',
            'umsp.required' => 'Nilai UMSP wajib diisi.',
            'umsp.numeric' => 'Nilai UMSP harus berupa angka.',
            'umsp.min' => 'Nilai UMSP harus lebih dari 0.',
            'tgl_berlaku.required' => 'Tanggal berlaku wajib diisi.',
            'tgl_berlaku.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'sumber.required' => 'Sumber/referensi wajib diisi.',
        ];
    }
}