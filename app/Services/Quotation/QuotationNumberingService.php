<?php

namespace App\Services\Quotation;

use App\Models\Company;
use App\Models\Leads;
use App\Models\Quotation;
use App\Services\Numbering\DocumentFamily;
use App\Services\Numbering\DocumentVersionChain;
use Carbon\Carbon;

/**
 * Standard numbering system untuk Quotation.
 *
 * Format: {PREFIX}/{TIPE}/{COMPANY_CODE}/{LEADS_NOMOR}-{MMYYYY}-{SEQ}{VERSION}
 *
 * TIPE (selalu mengikuti tipe dokumen ini sendiri, bukan warisan referensi):
 *   ORG = Original (baru)
 *   RVS = Revisi
 *   RKT = Rekontrak
 *   ADD = Addendum
 *
 * VERSION — satu huruf tidak pernah muncul dua kali dalam satu rantai;
 * counter-nya yang naik. Nesting hanya terjadi antar huruf berbeda.
 *   (none) = Original document
 *   -V{NN} = Revisi ke-N
 *   -K{NN} = Rekontrak ke-N
 *   -A{NN} = Addendum ke-N
 *
 * Contoh:
 *   QUOT/ORG/ION/LS001-072026-00001         ← Original
 *   QUOT/RVS/ION/LS001-072026-00001-V01     ← Revisi 1
 *   QUOT/RVS/ION/LS001-072026-00001-V02     ← Revisi 2 (dari revisi 1)
 *   QUOT/RKT/ION/LS001-072026-00001-K01     ← Rekontrak 1
 *   QUOT/RVS/ION/LS001-072026-00001-K01-V01 ← Revisi 1 dari Rekontrak 1
 *   QUOT/RVS/ION/LS001-072026-00001-K01-V02 ← Revisi 2 dari Rekontrak 1
 *
 * @see DocumentVersionChain aturan penyusunan rantai versi
 * @see DocumentFamily       cara counter dihitung terhadap keluarga dokumen
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
     * Generate nomor untuk Quotation.
     *
     * @param int    $leadsId
     * @param int    $companyId
     * @param string $tipeQuotation  'baru'|'revisi'|'rekontrak'|'addendum'
     * @param int|null $referensiId ID quotation referensi (untuk revisi/rkt/add)
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

        // --- Original (baru): SEQ counter per bulan ---
        if ($tipeQuotation === 'baru') {
            $seq = $this->getNextSequence($base, $monthYear);

            return DocumentVersionChain::assertLength(
                $base . $monthYear . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT)
            );
        }

        // --- Turunan (revisi/rekontrak/addendum) — perlu referensi ---
        if (!$referensiId) {
            throw new \InvalidArgumentException(
                "Referensi ID wajib untuk tipe '{$tipeQuotation}'"
            );
        }

        $referensi = Quotation::findOrFail($referensiId);

        // dateSeq & rantai versi diwarisi dari nomor referensi. Segmen TIPE
        // TIDAK diwarisi — nomor turunan memakai TIPE code-nya sendiri
        // (lih. $base di atas).
        $parsed = DocumentVersionChain::parse($referensi->nomor);
        $dateSeq = $parsed['dateSeq'] ?? ($monthYear . '-00001');

        $versionCode = $this->getVersionCode($tipeQuotation);

        // Kalau rantai referensi sudah berakhir dengan huruf yang sama, segmen
        // itu diganti (counter naik), bukan ditambah. Tanpa ini, revisi dari
        // revisi menghasilkan `-V02-V02-V02`.
        $prefix = DocumentVersionChain::prefixFor($parsed['chain'], $versionCode);

        $counter = DocumentVersionChain::nextCounter(
            DocumentFamily::nomorOf(Quotation::class, 'quotation_referensi_id', $referensiId),
            $dateSeq,
            $prefix,
            $versionCode
        );

        $chain = DocumentVersionChain::append($prefix, $versionCode, $counter);

        return DocumentVersionChain::assertLength(
            DocumentVersionChain::render($base, $dateSeq, $chain)
        );
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
     *
     * Dihitung dari SEQ tertinggi yang sudah terpakai atas query `withTrashed()`
     * — bukan `count() + 1`. Quotation memakai SoftDeletes, jadi `count()` akan
     * melewatkan baris terhapus dan memakai ulang nomornya.
     */
    private function getNextSequence(string $base, string $monthYear): int
    {
        $prefix = $base . $monthYear . '-';

        $existing = Quotation::withTrashed()
            ->where('nomor', 'like', $prefix . '%')
            ->pluck('nomor')
            ->all();

        return DocumentVersionChain::nextSequence($existing, $prefix);
    }
}
