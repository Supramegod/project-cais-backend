<?php

namespace App\Http\Requests; // Ubah namespace-nya ke folder App

use Illuminate\Foundation\Http\FormRequest as LaravelFormRequest; // Alias-kan file aslinya
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

// Kita tetap "extends" file aslinya, tapi kita timpa (override) fungsinya
class BaseRequest extends LaravelFormRequest
{
    /**
     * Copy paste fungsi failedValidation yang kamu modifikasi tadi ke sini
     */
    protected function failedValidation(Validator $validator)
    {
        $messages = $validator->errors()->toArray();

        throw new HttpResponseException(
            response()->json([
                'message' => $messages
            ], 422)
        );
    }
    
    // Kamu tidak perlu meng-copy semua 200 baris kode lainnya, 
    // karena secara otomatis sudah "dipinjam" dari LaravelFormRequest (extends).
}