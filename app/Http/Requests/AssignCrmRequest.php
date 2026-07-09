<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi untuk CustomerActivityController@assignCRM.
 */
class AssignCrmRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leads_id'  => FluentRule::integer()->required()->exists('sl_leads', 'id'),
            'crm_id'    => FluentRule::integer()->required(),
            'crm_team'  => FluentRule::array()->nullable()->children([
                '*' => FluentRule::integer(),
            ]),
            'notes'     => FluentRule::string()->required(),
        ];
    }
}
