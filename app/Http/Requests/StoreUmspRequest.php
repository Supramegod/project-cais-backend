<?php
// app/Http/Requests/StoreUmspRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUmspRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'province_id'   => ['required', 'integer', 'min:1'],
            'province_name' => ['required', 'string', 'max:150'],
            'sektor'        => ['required', 'string', 'max:100'],
            'umsp'          => ['required', 'numeric', 'min:1'],
            'tgl_berlaku'   => ['required', 'date_format:Y-m-d'],
            'sumber'        => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'province_id.required'   => 'Province ID wajib diisi.',
            'province_name.required' => 'Nama provinsi wajib diisi.',
            'sektor.required'        => 'Nama sektor wajib diisi.',
            'sektor.max'             => 'Nama sektor maksimal 100 karakter.',
            'umsp.required'          => 'Nilai UMSP wajib diisi.',
            'umsp.numeric'           => 'Nilai UMSP harus berupa angka.',
            'umsp.min'               => 'Nilai UMSP harus lebih dari 0.',
            'tgl_berlaku.required'   => 'Tanggal berlaku wajib diisi.',
            'tgl_berlaku.date_format'=> 'Format tanggal harus YYYY-MM-DD.',
            'sumber.required'        => 'Sumber/referensi wajib diisi.',
        ];
    }
}