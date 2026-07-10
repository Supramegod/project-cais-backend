<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;

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
