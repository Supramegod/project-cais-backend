<?php

namespace App\Http\Requests\Barang;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class BarangDefaultQtyBulkRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barang_id'                 => FluentRule::numeric('barang_id')->required(),
            'quantities'                => FluentRule::array(label: 'quantities')->required()->min(1),
            'quantities.*.layanan_id'   => FluentRule::numeric('layanan_id')->required(),
            'quantities.*.qty_default'  => FluentRule::numeric('qty_default')->required()->min(0),
        ];
    }
}
