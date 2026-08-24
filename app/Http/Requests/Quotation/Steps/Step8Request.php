<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step8Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'devices' => FluentRule::array()->sometimes(),
        ];
    }
}
