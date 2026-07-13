<?php

namespace App\Http\Requests\CustomerActivity;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi untuk CustomerActivityController@addContractActivity.
 */
class StoreContractActivityRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pks_id'        => FluentRule::integer()->required()->exists('sl_pks', 'id'),
            'tgl_activity'  => FluentRule::date()->required(),
            'tipe'          => FluentRule::string()->required(),
            'tgl_realisasi' => FluentRule::date()->nullable(),
        ];
    }
}
