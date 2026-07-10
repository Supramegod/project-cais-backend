<?php

namespace App\Http\Requests\Upah;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;
use SanderMuller\FluentValidation\HasFluentRules;

class StoreUmskRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => FluentRule::integer()->required()->min(1),
            'city_name' => FluentRule::string()->required()->max(150),
            'sektor' => FluentRule::string()->required()->max(100),
            'umsk' => FluentRule::numeric()->required()->min(1),
            'tgl_berlaku' => FluentRule::string()->required()->dateFormat('Y-m-d'),
            'sumber' => FluentRule::string()->required()->max(500),
        ];
    }

    public function messages(): array
    {
        return [
            'city_id.required' => 'City ID wajib diisi.',
            'city_name.required' => 'Nama kota/kabupaten wajib diisi.',
            'sektor.required' => 'Nama sektor wajib diisi.',
            'sektor.max' => 'Nama sektor maksimal 100 karakter.',
            'umsk.required' => 'Nilai UMSK wajib diisi.',
            'umsk.numeric' => 'Nilai UMSK harus berupa angka.',
            'umsk.min' => 'Nilai UMSK harus lebih dari 0.',
            'tgl_berlaku.required' => 'Tanggal berlaku wajib diisi.',
            'tgl_berlaku.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'sumber.required' => 'Sumber/referensi wajib diisi.',
        ];
    }
}