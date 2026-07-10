<?php

namespace App\Services\Spk;

use App\Models\Company;
use App\Models\Leads;
use App\Models\Spk;
use Carbon\Carbon;

/**
 * Standard numbering untuk SPK.
 *
 * Format: SPK/{COMPANY_CODE}/{LEADS_NOMOR}-{YYYYMM}-{SEQ}
 *
 * Contoh: SPK/ION/LS001-202612-00001
 */
class SpkNumberingService
{
    public function generate(int $leadsId, int $companyId): string
    {
        $now = Carbon::now();
        $yearMonth = $now->format('Ymd'); // 202612

        $leads = Leads::whereNull('deleted_at')->findOrFail($leadsId);
        $company = Company::find($companyId);

        $base = 'SPK/';
        $base .= $company ? $company->code . '/' : 'NN/';
        $base .= ($leads->nomor ?? 'NNNNN') . '-';

        $seq = Spk::where('nomor', 'like', $base . $yearMonth . '-%')
            ->count() + 1;

        return $base . $yearMonth . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
