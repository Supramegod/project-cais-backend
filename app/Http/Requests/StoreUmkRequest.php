<?php
// app/Http/Requests/StoreUmkRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUmkRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'city_id'     => ['required', 'integer', 'min:1'],
            'city_name'   => ['required', 'string', 'max:255'],
            'umk'         => ['required', 'numeric', 'min:0'],
            'tgl_berlaku' => ['required', 'date'],
            'sumber'      => ['required', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'city_id.required'     => 'City ID harus diisi.',
            'city_name.required'   => 'Nama kota/kabupaten harus diisi.',
            'umk.required'         => 'Nilai UMK harus diisi.',
            'umk.numeric'          => 'Nilai UMK harus berupa angka.',
            'tgl_berlaku.required' => 'Tanggal berlaku harus diisi.',
            'tgl_berlaku.date'     => 'Format tanggal tidak valid.',
            'sumber.required'      => 'Sumber harus diisi.',
            'sumber.url'           => 'Sumber harus berupa URL yang valid.',
        ];
    }
}