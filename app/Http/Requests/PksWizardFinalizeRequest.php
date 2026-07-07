<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class PksWizardFinalizeRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_finalize' => FluentRule::boolean()->required(),
        ];
    }
}
