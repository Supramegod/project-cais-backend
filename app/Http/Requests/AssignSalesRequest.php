<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class AssignSalesRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignments' => FluentRule::array(label: 'Assignments')->required()->min(1)
                ->each([
                    'tim_sales_d_id' => FluentRule::integer()->required()->exists('m_tim_sales_d', 'id'),
                    'kebutuhan_ids'  => FluentRule::array()->required()->min(1)->each(
                        FluentRule::integer()->exists('m_kebutuhan', 'id')
                    ),
                ]),
        ];
    }
}
