<?php

namespace App\Http\Requests\Report;

/**
 * Validasi periode laporan + filter jenis aktivitas untuk telesales.
 * Dipakai oleh: activityDetailTele (cais_role_id = 30).
 * Enum lebih sempit dari sales regular: hanya Leads, Assignment, Appointment.
 */
class ReportActivityDetailTeleRequest extends ReportActivityDetailRequest
{
    /**
     * Jenis aktivitas yang tercatat untuk telesales.
     *
     * @var list<string>
     */
    public const JENIS_ACTIVITY_TELE = [
        'Leads',
        'Assignment',
        'Appointment',
    ];

    protected function allowedJenisActivity(): array
    {
        return self::JENIS_ACTIVITY_TELE;
    }
}
