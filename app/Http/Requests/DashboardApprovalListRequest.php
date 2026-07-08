<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

class DashboardApprovalListRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe' => FluentRule::string('Tipe')
                ->nullable()
                ->in(['menunggu-anda', 'menunggu-approval', 'quotation-belum-lengkap']),
        ];
    }
}
