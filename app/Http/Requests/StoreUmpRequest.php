<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreUmpRequest extends BaseRequest
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
            'ump' => FluentRule::numeric()->required()->min(1),
            'tgl_berlaku' => FluentRule::string()->required()->dateFormat('Y-m-d'),
            'sumber' => FluentRule::string()->required()->max(500),
        ];
    }

    public function messages(): array
    {
        return [
            'province_id.required' => 'Province ID wajib diisi.',
            'province_name.required' => 'Nama provinsi wajib diisi.',
            'ump.required' => 'Nilai UMP wajib diisi.',
            'ump.numeric' => 'Nilai UMP harus berupa angka.',
            'ump.min' => 'Nilai UMP harus lebih dari 0.',
            'tgl_berlaku.required' => 'Tanggal berlaku wajib diisi.',
            'tgl_berlaku.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'sumber.required' => 'Sumber/referensi wajib diisi.',
        ];
    }
}