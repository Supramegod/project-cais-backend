<?php

namespace App\Http\Requests\Quotation\Steps;

use App\Http\Requests\BaseRequest;
use SanderMuller\FluentValidation\FluentRule;

class Step7Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'edit' => FluentRule::boolean()->sometimes(),
            'kaporlaps' => FluentRule::array()->sometimes(),
        ];
    }
}
