<?php
// app/Http/Requests/StoreUmskRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUmskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'min:1'],
            'city_name' => ['required', 'string', 'max:150'],
            'sektor' => ['required', 'string', 'max:100'],
            'umsk' => ['required', 'numeric', 'min:1'],
            'tgl_berlaku' => ['required', 'date_format:Y-m-d'],
            'sumber' => ['required', 'string', 'max:500'],
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