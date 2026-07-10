<?php

namespace App\Http\Requests\Leads;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class ImportLeadRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => FluentRule::file('File')->required()->mimes('csv', 'xls', 'xlsx'),
        ];
    }
}
