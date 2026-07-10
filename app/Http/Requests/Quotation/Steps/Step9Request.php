<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step9Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'barang_id' => FluentRule::integer()->sometimes()->requiredWithout('chemicals')->exists('m_barang', 'id'),
            'jumlah' => FluentRule::integer()->sometimes()->requiredWithout('chemicals')->min(0),
            'masa_pakai' => FluentRule::integer()->sometimes()->min(1),
            'harga' => FluentRule::numeric()->sometimes()->min(0),
            'chemicals' => FluentRule::array()->sometimes()->each([
                'barang_id' => FluentRule::integer()->requiredWith('chemicals')->exists('m_barang', 'id'),
                'jumlah' => FluentRule::integer()->requiredWith('chemicals')->min(0),
                'masa_pakai' => FluentRule::integer()->sometimes()->min(1),
                'harga' => FluentRule::numeric()->sometimes()->min(0),
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'barang_id.required_without' => 'Barang ID harus diisi',
            'barang_id.exists' => 'Barang tidak valid',
            'jumlah.required_without' => 'Jumlah harus diisi',
            'jumlah.min' => 'Jumlah tidak boleh kurang dari 0',
            'masa_pakai.min' => 'Masa pakai minimal 1',
            'harga.min' => 'Harga tidak boleh kurang dari 0',
            'chemicals.*.barang_id.required_with' => 'Barang ID harus diisi',
            'chemicals.*.barang_id.exists' => 'Barang tidak valid',
            'chemicals.*.jumlah.required_with' => 'Jumlah harus diisi',
            'chemicals.*.jumlah.min' => 'Jumlah tidak boleh kurang dari 0',
            'chemicals.*.masa_pakai.min' => 'Masa pakai minimal 1',
            'chemicals.*.harga.min' => 'Harga tidak boleh kurang dari 0',
        ];
    }
}
