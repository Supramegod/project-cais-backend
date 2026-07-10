<?php

namespace App\Services\Pks;

use App\Models\Company;
use App\Models\Leads;
use App\Models\Pks;
use Carbon\Carbon;

/**
 * Standard numbering untuk PKS.
 *
 * Format: {PREFIX}/{TIPE}/{COMPANY_CODE}/{LEADS_NOMOR}-{MMYYYY}-{SEQ}{VERSION}
 *
 * TIPE:
 *   ORG = Original (baru)
 *   RKT = Rekontrak
 *   ADD = Addendum
 *
 * VERSION:
 *   -K{NN} = Rekontrak ke-N
 *   -A{NN} = Addendum ke-N
 *
 * Contoh:
 *   PKS/ORG/ION/LS001-072026-00001         ← Original
 *   PKS/RKT/ION/LS001-072026-00001-K01     ← Rekontrak 1
 *   PKS/ADD/ION/LS001-072026-00001-A01     ← Addendum 1
 */
class PksNumberingService
{
    const TIPE_ORG = 'ORG';
    const TIPE_RKT = 'RKT';
    const TIPE_ADD = 'ADD';

    const TIPE_MAP = [
        'baru'      => self::TIPE_ORG,
        'rekontrak' => self::TIPE_RKT,
        'addendum'  => self::TIPE_ADD,
    ];

    /**
     * Generate nomor PKS.
     *
     * @param int      $leadsId
     * @param int      $companyId
     * @param string   $tipePks     'baru'|'rekontrak'|'addendum'
     * @param int|null $pksIndukId  Parent PKS ID (wajib untuk rekontrak/addendum)
     * @return string
     */
    public function generate(int $leadsId, int $companyId, string $tipePks = 'baru', ?int $pksIndukId = null): string
    {
        $now = Carbon::now();
        $monthYear = $now->format('mY');

        $leads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);
        $tipeCode = self::TIPE_MAP[$tipePks] ?? self::TIPE_ORG;

        // Base: PKS/{TIPE}/{COMPANY}/{LEADS}-
        $base = 'PKS/' . $tipeCode . '/';
        $base .= $company ? $company->code . '/' : 'NN/';
        $base .= ($leads->nomor ?? 'NNNNN') . '-';

        // --- ORIGINAL (baru) & REKONTRAK (own SEQ) ---
        if ($tipePks === 'baru' || $tipePks === 'rekontrak') {
            $seq = Pks::where('nomor', 'like', $base . $monthYear . '-%')
                ->count() + 1;
            return $base . $monthYear . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
        }

        // --- ADDENDUM (tied to parent SEQ) ---
        if (!$pksIndukId) {
            throw new \InvalidArgumentException("PKS induk wajib untuk tipe '{$tipePks}'");
        }

        $pksInduk = Pks::findOrFail($pksIndukId);
        $nomorInduk = preg_replace('/^draft\//', '', $pksInduk->nomor);

        // Ekstrak segmen bulan-tahun-SEQ dari nomor induk (mis. 072026-00001)
        preg_match('/-(\d+-\d{5})(?:-[KA]\d{2}(?:-[KA]\d{2})*)?$/', $nomorInduk, $matches);
        $dateSeq = $matches[1] ?? ($monthYear . '-00001');

        // Ekstrak existing version suffix
        $existingVersion = '';
        if (preg_match('/-((?:[KA]\d{2}(?:-[KA]\d{2})*))$/', $nomorInduk, $vMatches)) {
            $existingVersion = $vMatches[1];
        }

        $versionCode = match ($tipePks) {
            'rekontrak' => 'K',
            'addendum'  => 'A',
            default     => throw new \InvalidArgumentException("Tipe '{$tipePks}' tidak memiliki version code"),
        };

        $fullVersion = $existingVersion
            ? $existingVersion . '-' . $versionCode
            : $versionCode;

        $counter = Pks::where(function ($q) use ($pksIndukId) {
                $q->where('pks_induk_id', $pksIndukId)
                  ->orWhere('id', $pksIndukId);
            })
            ->where('tipe_pks', $tipePks)
            ->whereYear('created_at', $now->year)
            ->count() + 1;

        return $base . $dateSeq . '-' . $fullVersion . '-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
    }
}
