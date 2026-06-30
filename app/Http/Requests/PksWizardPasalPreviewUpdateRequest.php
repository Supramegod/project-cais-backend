<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class PksWizardPasalPreviewUpdateRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pasal' => FluentRule::string()->nullable()->max(100),
            'judul' => FluentRule::string()->nullable()->max(255),
            'raw_text' => FluentRule::string()->required(),
        ];
    }
}
