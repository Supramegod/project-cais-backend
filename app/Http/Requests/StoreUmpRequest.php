<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUmpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'province_id' => ['required', 'integer', 'min:1'],
            'province_name' => ['required', 'string', 'max:150'],
            'ump' => ['required', 'numeric', 'min:1'],
            'tgl_berlaku' => ['required', 'date_format:Y-m-d'],
            'sumber' => ['required', 'string', 'max:500'],
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