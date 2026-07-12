<?php

namespace App\Services\Quotation;

use App\Models\Company;
use App\Models\Leads;
use App\Models\Quotation;
use Carbon\Carbon;

/**
 * Standard numbering system untuk Quotation.
 *
 * Format: {PREFIX}/{TIPE}/{COMPANY_CODE}/{LEADS_NOMOR}-{MMYYYY}-{SEQ}{VERSION}
 *
 * TIPE:
 *   ORG = Original (baru)
 *   RVS = Revisi
 *   RKT = Rekontrak
 *   ADD = Addendum
 *
 * VERSION (multi-level):
 *   (none) = Original document
 *   -V{01} = Revisi ke-N dari dokumen
 *   -K{01} = Rekontrak ke-N dari dokumen
 *   -A{01} = Addendum ke-N dari dokumen
 *   -R{01} = untuk SPK atau dokumen lain
 *
 * Contoh:
 *   QUOT/ORG/ION/LS001-072026-00001         ← Original
 *   QUOT/RVS/ION/LS001-072026-00001-V01     ← Revisi 1
 *   QUOT/RKT/ION/LS001-072026-00001-K01     ← Rekontrak 1
 *   QUOT/ADD/ION/LS001-072026-00001-A01     ← Addendum 1
 *   QUOT/RVS/ION/LS001-072026-00001-K01-V01 ← Revisi 1 dari Rekontrak 1
 */
class QuotationNumberingService
{
    const TIPE_ORG = 'ORG';
    const TIPE_RVS = 'RVS';
    const TIPE_RKT = 'RKT';
    const TIPE_ADD = 'ADD';

    /**
     * Mapping tipe_quotation dari database ke kode standar.
     */
    const TIPE_MAP = [
        'baru'      => self::TIPE_ORG,
        'revisi'    => self::TIPE_RVS,
        'rekontrak' => self::TIPE_RKT,
        'addendum'  => self::TIPE_ADD,
    ];

    /**
     * Generate nomor untuk Quotation baru (original).
     *
     * @param int    $leadsId
     * @param int    $companyId
     * @param string $tipeQuotation  'baru'|'revisi'|'rekontrak'|'addendum'
     * @param int|null $referensiId ID of parent quotation (untuk revisi/rkt/add)
     * @return string
     */
    public function generate(
        int $leadsId,
        int $companyId,
        string $tipeQuotation = 'baru',
        ?int $referensiId = null
    ): string {
        $now = Carbon::now();
        $monthYear = $now->format('mY'); // 072026

        if (!array_key_exists($tipeQuotation, self::TIPE_MAP)) {
            throw new \InvalidArgumentException("Tipe Quotation '{$tipeQuotation}' tidak dikenal");
        }

        $leads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);
        $tipeCode = self::TIPE_MAP[$tipeQuotation];

        // Base: QUOT/{TIPE}/{COMPANY}/{LEADS_NOMOR}-
        $base = 'QUOT/' . $tipeCode . '/';
        $base .= $company ? $company->code . '/' : 'NN/';
        $base .= ($leads->nomor ?? 'NNNNN') . '-';

        // --- Generate SEQ & VERSION ---

        // Original (baru): SEQ counter per bulan
        if ($tipeQuotation === 'baru') {
            $seq = $this->getNextSequence($base, $monthYear);
            return $base . $monthYear . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
        }

        // Turunan (revisi/rekontrak/addendum) — perlu referensi
        if (!$referensiId) {
            throw new \InvalidArgumentException(
                "Referensi ID wajib untuk tipe '{$tipeQuotation}'"
            );
        }

        $referensi = Quotation::findOrFail($referensiId);
        $nomorReferensi = $referensi->nomor;

        // Ekstrak {MMYYYY}-{SEQ} dari nomor referensi (bagian numerik saja, TIPE
        // code TIDAK diikutkan karena nomor turunan harus pakai TIPE code-nya
        // sendiri, bukan warisan dari referensi — lih. $base di atas).
        // Contoh: QUOT/ORG/ION/LS001-072026-00001 → dateSeq = 072026-00001
        preg_match('/-(\d{6}-\d{5})(?:-[VKA]\d{2}(?:-[VKA]\d{2})*)?$/', $nomorReferensi, $matches);
        $dateSeq = $matches[1] ?? ($monthYear . '-00001');

        // Ekstrak existing version suffix dari referensi
        // Contoh: QUOT/ORG/ION/LS001-072026-00001-K01 → K01
        //         QUOT/ORG/ION/LS001-072026-00001-K01-V01 → K01-V01
        $existingVersion = '';
        if (preg_match('/-((?:[VKA]\d{2}(?:-[VKA]\d{2})*))$/', $nomorReferensi, $vMatches)) {
            $existingVersion = $vMatches[1];
        }

        // Tentukan version code baru
        $versionCode = $this->getVersionCode($tipeQuotation);

        // Hitung urutan untuk version ini
        $counter = $this->getVersionCounter($referensiId, $tipeQuotation, $now->year);

        // Gabungkan: existingVersion + versionCode+counter baru (mis. K01-V01, bukan K-01-V-01)
        $versionSegment = $versionCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
        $fullVersion = $existingVersion
            ? $existingVersion . '-' . $versionSegment
            : $versionSegment;

        return $base . $dateSeq . '-' . $fullVersion;
    }

    /**
     * Dapatkan kode version untuk tipe tertentu.
     */
    private function getVersionCode(string $tipeQuotation): string
    {
        return match ($tipeQuotation) {
            'revisi'    => 'V',
            'rekontrak' => 'K',
            'addendum'  => 'A',
            default     => throw new \InvalidArgumentException("Tipe '{$tipeQuotation}' tidak memiliki version code"),
        };
    }

    /**
     * Dapatkan sequence number untuk original document per bulan.
     */
    private function getNextSequence(string $base, string $monthYear): int
    {
        return Quotation::where('nomor', 'like', $base . $monthYear . '-%')
            ->count() + 1;
    }

    /**
     * Dapatkan counter untuk version tertentu (revisi ke berapa).
     */
    private function getVersionCounter(
        int $referensiId,
        string $tipeQuotation,
        int $year
    ): int {
        // Cari berdasarkan parent + tipe
        return Quotation::where(function ($q) use ($referensiId) {
                $q->where('quotation_referensi_id', $referensiId)
                  ->orWhere('id', $referensiId);
            })
            ->where('tipe_quotation', $tipeQuotation)
            ->whereYear('created_at', $year)
            ->count() + 1;
    }
}
