<?php

namespace App\Http\Requests\CustomerActivity;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi untuk CustomerActivityController@assignRO.
 */
class AssignRoRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leads_id'  => FluentRule::integer()->required()->exists('sl_leads', 'id'),
            'ro_id'     => FluentRule::integer()->required(),
            'ro_team'   => FluentRule::array()->nullable()->children([
                '*' => FluentRule::integer(),
            ]),
            'notes'     => FluentRule::string()->required(),
        ];
    }
}
