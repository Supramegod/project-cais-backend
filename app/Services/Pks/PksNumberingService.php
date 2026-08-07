<?php

namespace App\Services\Pks;

use App\Models\Company;
use App\Models\Leads;
use App\Models\Pks;
use App\Services\Numbering\DocumentFamily;
use App\Services\Numbering\DocumentVersionChain;
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
 * VERSION — satu huruf tidak pernah muncul dua kali dalam satu rantai;
 * counter-nya yang naik. Nesting hanya terjadi antar huruf berbeda.
 *   -K{NN} = Rekontrak ke-N (pks_induk_id wajib)
 *   -A{NN} = Addendum ke-N (pks_induk_id wajib)
 *
 * Contoh:
 *   PKS/ORG/ION/LS001-072026-00001         ← Original
 *   PKS/RKT/ION/LS001-072026-00001-K01     ← Rekontrak 1 dari Original
 *   PKS/RKT/ION/LS001-072026-00001-K02     ← Rekontrak 2 (dari Rekontrak 1)
 *   PKS/ADD/ION/LS001-072026-00001-A01     ← Addendum 1 dari Original
 *   PKS/ADD/ION/LS001-072026-00001-K01-A01 ← Addendum 1 dari Rekontrak 1
 *
 * @see DocumentVersionChain aturan penyusunan rantai versi
 * @see DocumentFamily       cara counter dihitung terhadap keluarga dokumen
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

        if (!array_key_exists($tipePks, self::TIPE_MAP)) {
            throw new \InvalidArgumentException("Tipe PKS '{$tipePks}' tidak dikenal");
        }

        $leads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);
        $tipeCode = self::TIPE_MAP[$tipePks];

        // Base: PKS/{TIPE}/{COMPANY}/{LEADS}-
        $base = 'PKS/' . $tipeCode . '/';
        $base .= $company ? $company->code . '/' : 'NN/';
        $base .= ($leads->nomor ?? 'NNNNN') . '-';

        // --- ORIGINAL (baru): SEQ sendiri ---
        if ($tipePks === 'baru') {
            $seq = $this->getNextSequence($base, $monthYear);

            return DocumentVersionChain::assertLength(
                $base . $monthYear . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT)
            );
        }

        // --- REKONTRAK & ADDENDUM (terikat ke keluarga induk) ---
        if (!$pksIndukId) {
            throw new \InvalidArgumentException("PKS induk wajib untuk tipe '{$tipePks}'");
        }

        $pksInduk = Pks::findOrFail($pksIndukId);

        // parse() sekaligus membuang prefix `draft/` yang dipakai PKS wizard.
        $parsed = DocumentVersionChain::parse($pksInduk->nomor);
        $dateSeq = $parsed['dateSeq'] ?? ($monthYear . '-00001');

        $versionCode = match ($tipePks) {
            'rekontrak' => 'K',
            'addendum'  => 'A',
            default     => throw new \InvalidArgumentException("Tipe '{$tipePks}' tidak memiliki version code"),
        };

        // Huruf yang sama tidak diulang — segmen terakhir diganti, counter naik.
        $prefix = DocumentVersionChain::prefixFor($parsed['chain'], $versionCode);

        $counter = DocumentVersionChain::nextCounter(
            DocumentFamily::nomorOf(Pks::class, 'pks_induk_id', $pksIndukId),
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
     * SEQ bulanan berdasarkan nomor tertinggi yang sudah terpakai.
     *
     * Pks memakai SoftDeletes, jadi `count() + 1` akan memakai ulang nomor
     * milik baris yang sudah dihapus. Query di bawah pakai `withTrashed()`.
     */
    private function getNextSequence(string $base, string $monthYear): int
    {
        $prefix = $base . $monthYear . '-';

        // `draft/` diperhitungkan: nomor draft memakai slot SEQ yang sama.
        $existing = Pks::withTrashed()
            ->where(function ($q) use ($prefix) {
                $q->where('nomor', 'like', $prefix . '%')
                  ->orWhere('nomor', 'like', 'draft/' . $prefix . '%');
            })
            ->pluck('nomor')
            ->all();

        return DocumentVersionChain::nextSequence($existing, $prefix);
    }
}
