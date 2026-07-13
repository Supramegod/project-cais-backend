<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class UploadPksRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => FluentRule::file('File')->required()->mimes('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png')->max(10240),
        ];
    }
}
