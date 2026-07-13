<?php

namespace App\Http\Requests\Barang;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class BarangDefaultQtyRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barang_id'   => FluentRule::numeric('barang_id')->required(),
            'layanan_id'  => FluentRule::numeric('layanan_id')->required(),
            'qty_default' => FluentRule::numeric('qty_default')->required()->min(0),
        ];
    }
}
