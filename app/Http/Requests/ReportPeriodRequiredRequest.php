<?php

namespace App\Http\Requests;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi periode laporan dengan month/year WAJIB.
 * Dipakai oleh: weekly, monthlyRole30, weeklyRole30.
 * Kontrak 422: bentuk BaseRequest { message: { field: [...] } }.
 */
class ReportPeriodRequiredRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month'     => FluentRule::integer()->required()->between(1, 12),
            'year'      => FluentRule::integer()->required()->digits(4),
            'branch_id' => FluentRule::integer()->nullable()->exists('mysqlhris.m_branch', 'id'),
        ];
    }
}
