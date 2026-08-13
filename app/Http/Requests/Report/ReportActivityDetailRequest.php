<?php

namespace App\Http\Requests\Report;

use SanderMuller\FluentValidation\FluentRule;

/**
 * Validasi periode laporan + filter jenis aktivitas (semua OPSIONAL).
 * Dipakai oleh: activityDetail (sales regular).
 * Kontrak 422: bentuk BaseRequest { message: { field: [...] } }.
 */
class ReportActivityDetailRequest extends ReportPeriodOptionalRequest
{
    /**
     * Jenis aktivitas yang tercatat untuk sales regular.
     *
     * @var list<string>
     */
    public const JENIS_ACTIVITY = [
        'Leads',
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
            'jenis_activity' => FluentRule::string()->nullable()->in($this->allowedJenisActivity()),
        ];
    }

    /**
     * Jenis aktivitas yang valid untuk endpoint ini.
     *
     * @return list<string>
     */
    protected function allowedJenisActivity(): array
    {
        return self::JENIS_ACTIVITY;
    }
}
