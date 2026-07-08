<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class NotificationIndexRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_read' => FluentRule::field('Is Read')->nullable()->in([0, 1]),
            'limit'   => FluentRule::integer('Limit')->nullable()->min(1)->max(100),
            'offset'  => FluentRule::integer('Offset')->nullable()->min(0),
        ];
    }
}
