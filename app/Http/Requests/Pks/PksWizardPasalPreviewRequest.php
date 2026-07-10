<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class PksWizardPasalPreviewRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'regenerate' => FluentRule::boolean()->nullable(),
            'additional_articles' => FluentRule::array()->nullable()->children([
                '*' => FluentRule::array()->required(),
            ]),
            'additional_articles.*.pasal' => FluentRule::string()->required()->max(100),
            'additional_articles.*.judul' => FluentRule::string()->required()->max(255),
            'additional_articles.*.raw_text' => FluentRule::string()->required(),
        ];
    }
}
