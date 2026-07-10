<?php

namespace App\Http\Requests\Submission;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi daftar id submission untuk aksi massal (convert / delete).
 * Dipakai bersama oleh SubmissionController::convert dan ::delete
 * karena aturan validasinya identik.
 */
class SubmissionIdRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => FluentRule::array(label: 'ID')->required()->min(1)->each(
                FluentRule::integer()->exists('sl_submission', 'id')
            ),
        ];
    }
}
