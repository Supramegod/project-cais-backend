<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class RemoveSalesRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kebutuhan_ids' => FluentRule::array(label: 'Kebutuhan')->required()->min(1)->each(
                FluentRule::integer()->exists('m_kebutuhan', 'id')
            ),
        ];
    }
}
