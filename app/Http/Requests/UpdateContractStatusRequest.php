<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi untuk CustomerActivityController@updateContractStatus.
 */
class UpdateContractStatusRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pks_id'        => FluentRule::integer()->required()->exists('sl_pks', 'id'),
            'status_pks_id' => FluentRule::integer()->required()->exists('m_status_pks', 'id'),
            'notes'         => FluentRule::string()->required(),
        ];
    }
}
