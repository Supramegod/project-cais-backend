<?php

namespace App\Http\Requests\Pks;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

class ComparePerjanjianRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pks_perjanjian_id' => FluentRule::field('Pasal PKS')->required()->exists('sl_pks_perjanjian', 'id'),
            'history_id' => FluentRule::field('History')->nullable()->exists('sl_pks_perjanjian_history', 'id'),
        ];
    }
}
