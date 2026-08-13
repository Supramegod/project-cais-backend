<?php

namespace App\Http\Requests\Report;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi periode laporan + filter jenis aktivitas (semua OPSIONAL).
 * Dipakai oleh: activityDetail, activityDetailTele.
 * Kontrak 422: bentuk BaseRequest { message: { field: [...] } }.
 */
class ReportActivityDetailRequest extends ReportPeriodOptionalRequest
{
    /**
     * Gabungan jenis aktivitas sales regular dan telesales.
     *
     * @var list<string>
     */
    public const JENIS_ACTIVITY = [
        'Leads',
        'Assignment',
        'Appointment',
        'Visit',
        'Quotation',
        'SPK',
        'PKS',
        'Follow Up',
        'Kirim Berkas',
        'Email',
    ];

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'jenis_activity' => FluentRule::string()->nullable()->in(self::JENIS_ACTIVITY),
        ];
    }
}
