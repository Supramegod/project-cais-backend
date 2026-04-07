<?php
// app/Http/Requests/StoreUmpRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUmpRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'province_id'   => ['required', 'integer', 'min:1'],
            'province_name' => ['required', 'string', 'max:255'],
            'ump'           => ['required', 'numeric', 'min:0'],
            'tgl_berlaku'   => ['required', 'date'],
            'sumber'        => ['required', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'province_id.required'   => 'Province ID harus diisi.',
            'province_name.required' => 'Nama provinsi harus diisi.',
            'ump.required'           => 'Nilai UMP harus diisi.',
            'ump.numeric'            => 'Nilai UMP harus berupa angka.',
            'tgl_berlaku.required'   => 'Tanggal berlaku harus diisi.',
            'tgl_berlaku.date'       => 'Format tanggal tidak valid.',
            'sumber.required'        => 'Sumber harus diisi.',
            'sumber.url'             => 'Sumber harus berupa URL yang valid.',
        ];
    }
}