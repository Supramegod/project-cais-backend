<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\BaseRequest;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi periode laporan dengan month/year OPSIONAL.
 * Dipakai oleh: monthly, activityDetail, activityDetailTele.
 * Kontrak 422: bentuk BaseRequest { message: { field: [...] } }.
 */
class ReportPeriodOptionalRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month'     => FluentRule::integer()->nullable()->between(1, 12),
            'year'      => FluentRule::integer()->nullable()->digits(4),
            'branch_id' => FluentRule::integer()->nullable()->exists('mysqlhris.m_branch', 'id'),
        ];
    }
}
