<?php

namespace App\Http\Requests\Barang;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi tambah/update Barang. Cek keberadaan jenis_barang_id
 * (yang memicu 404 khusus) sengaja tetap di controller, bukan di sini.
 */
class BarangRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'            => FluentRule::string('Nama')->required(),
            'jenis_barang_id' => FluentRule::field('Jenis Barang')->required(),
            'harga'           => FluentRule::field('Harga')->required(),
        ];
    }
}
