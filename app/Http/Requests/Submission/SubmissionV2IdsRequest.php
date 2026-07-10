<?php

namespace App\Http\Requests\Submission;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Shared payload for SubmissionV2 bulk actions (convert & delete):
 * a non-empty array of existing sl_submission_v2 ids.
 */
class SubmissionV2IdsRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => FluentRule::array(label: 'Id')->required()->min(1),
            'id.*' => FluentRule::integer('Id')->exists('sl_submission_v2', 'id'),
        ];
    }
}
