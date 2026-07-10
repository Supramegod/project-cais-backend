<?php

namespace App\Services\Spk;

use App\Models\Company;
use App\Models\Leads;
use App\Models\Spk;
use Carbon\Carbon;

/**
 * Standard numbering untuk SPK.
 *
 * Format: SPK/{COMPANY_CODE}/{LEADS_NOMOR}-{YYYYMMDD}-{SEQ}
 *
 * Contoh: SPK/ION/LS001-20261225-00001
 */
class SpkNumberingService
{
    public function generate(int $leadsId, int $companyId): string
    {
        $now = Carbon::now();
        $yearMonthDay = $now->format('Ymd'); // 20261225

        $leads = Leads::whereNull('deleted_at')->findOrFail($leadsId);
        $company = Company::find($companyId);

        $base = 'SPK/';
        $base .= $company ? $company->code . '/' : 'NN/';
        $base .= ($leads->nomor ?? 'NNNNN') . '-';

        $seq = Spk::where('nomor', 'like', $base . $yearMonthDay . '-%')
            ->count() + 1;

        return $base . $yearMonthDay . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
