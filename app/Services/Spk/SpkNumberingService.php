<?php

namespace App\Services\Spk;

use App\Models\Company;
use App\Models\Leads;
use App\Models\Spk;
use App\Services\Numbering\DocumentVersionChain;
use Carbon\Carbon;

/**
 * Standard numbering untuk SPK.
 *
 * Format: SPK/{TIPE}/{COMPANY_CODE}/{LEADS_NOMOR}-{MMYYYY}-{SEQ}
 *
 * TIPE:
 *   ORG = Original (baru) — satu-satunya tipe SPK saat ini.
 *
 * Segmen TIPE disiapkan agar konsisten dengan PksNumberingService &
 * QuotationNumberingService — jaga-jaga apabila SPK ke depannya juga
 * punya turunan (mis. rekontrak/addendum) seperti PKS & Quotation.
 * Belum ada tipe turunan untuk SPK saat ini, jadi hanya ORG yang dipakai.
 *
 * Contoh: SPK/ORG/ION/LS001-072026-00001
 */
class SpkNumberingService
{
    const TIPE_ORG = 'ORG';

    public function generate(int $leadsId, int $companyId): string
    {
        $now = Carbon::now();
        $monthYear = $now->format('mY'); // 072026

        $leads = Leads::whereNull('deleted_at')->findOrFail($leadsId);
        $company = Company::find($companyId);

        $base = 'SPK/' . self::TIPE_ORG . '/';
        $base .= $company ? $company->code . '/' : 'NN/';
        $base .= ($leads->nomor ?? 'NNNNN') . '-';

        $prefix = $base . $monthYear . '-';

        // SEQ diambil dari nomor tertinggi yang sudah terpakai, atas query
        // `withTrashed()`. Spk memakai SoftDeletes, jadi `count() + 1` akan
        // melewatkan baris terhapus dan memakai ulang nomornya.
        $existing = Spk::withTrashed()
            ->where('nomor', 'like', $prefix . '%')
            ->pluck('nomor')
            ->all();

        $seq = DocumentVersionChain::nextSequence($existing, $prefix);

        return DocumentVersionChain::assertLength(
            $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT)
        );
    }
}
