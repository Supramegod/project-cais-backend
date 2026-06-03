<?php

namespace App\Rules;

use App\Models\Leads;
use Illuminate\Contracts\Validation\Rule;

class UniqueCompanyStrict implements Rule
{
    protected $excludeId;
    protected $similarCompanies = [];

    public function __construct($excludeId = null)
    {
        $this->excludeId = $excludeId;
    }

    public function passes($attribute, $value)
    {
        $this->similarCompanies = [];

        // Normalisasi & ekstrak kata inti dari input
        $inputNormalized = $this->normalize($value);
        $inputCore       = $this->extractCoreWords($value);
        $inputCoreSet    = $this->wordSet($inputCore);

        // Kalau input kosong setelah normalisasi, biarkan lolos (validasi lain yg handle)
        if ($inputNormalized === '') {
            return true;
        }

        $query = Leads::whereNull('deleted_at');
        if ($this->excludeId) {
            $query->where('id', '!=', $this->excludeId);
        }
        $companies = $query->pluck('nama_perusahaan')->toArray();

        if (empty($companies)) {
            return true;
        }

        $isDuplicate = false;

        foreach ($companies as $company) {
            if (empty($company)) {
                continue;
            }

            $companyNormalized = $this->normalize($company);
            $companyCore       = $this->extractCoreWords($company);
            $companyCoreSet    = $this->wordSet($companyCore);

            if ($companyNormalized === '') {
                continue;
            }

            $reason = null;

            // 1. EXACT MATCH — nama persis sama setelah normalisasi
            if ($inputNormalized === $companyNormalized) {
                $reason = 'nama persis sama';
            }

            // 2. CORE WORDS EXACT — set kata inti SAMA PERSIS (urutan/PT/CV diabaikan)
            //    Hanya duplikat kalau TIDAK ADA kata pembeda di kedua nama.
            elseif (!empty($inputCoreSet) && $inputCoreSet === $companyCoreSet) {
                $reason = 'kata inti sama persis';
            }

            // 3. TYPO / SALAH KETIK — sangat mirip secara karakter.
            //    Threshold tinggi (AND) supaya beda kata pembeda (ULIL vs AZMI,
            //    GUBENG vs MERR) TIDAK ikut ketolak.
            else {
                similar_text($inputNormalized, $companyNormalized, $percent);

                $distance  = levenshtein($inputNormalized, $companyNormalized);
                $maxLength = max(strlen($inputNormalized), strlen($companyNormalized));
                $lev       = $maxLength > 0 ? (1 - $distance / $maxLength) * 100 : 0;

                if ($percent >= 90 && $lev >= 90) {
                    $reason = sprintf('sangat mirip (%.1f%%)', $percent);
                }
            }

            if ($reason !== null) {
                $isDuplicate = true;
                $this->similarCompanies[] = [
                    'nama_perusahaan' => $company,
                    'alasan'          => $reason,
                ];
            }
        }

        return !$isDuplicate;
    }

    public function message()
    {
        if (empty($this->similarCompanies)) {
            return 'Nama perusahaan sudah terdaftar di database.';
        }

        $message = 'Nama perusahaan sudah ada / terlalu mirip dengan: ';

        $similarNames = [];
        foreach ($this->similarCompanies as $company) {
            $similarNames[] = "{$company['nama_perusahaan']} ({$company['alasan']})";
        }

        if (count($similarNames) > 3) {
            $shown = array_slice($similarNames, 0, 3);
            $message .= implode(', ', $shown) . ', dan ' . (count($this->similarCompanies) - 3) . ' lainnya';
        } else {
            $message .= implode(', ', $similarNames);
        }

        return $message;
    }

    /**
     * Normalisasi text: lowercase, hapus tanda baca & angka, rapikan spasi.
     */
    private function normalize($text)
    {
        if (empty($text)) {
            return '';
        }

        $text = strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\s]/u', ' ', $text); // sisakan huruf + spasi
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Ambil kata inti: buang bentuk badan usaha & kata sambung umum saja.
     * Nama lokasi TIDAK dibuang, supaya cabang beda lokasi tetap dianggap beda.
     */
    private function extractCoreWords($text)
    {
        if (empty($text)) {
            return '';
        }

        $normalized = $this->normalize($text);

        $commonWords = [
            'pt', 'cv', 'ud', 'tbk', 'persero', 'perusahaan',
            'company', 'corp', 'corporation', 'inc', 'ltd',
            'the', 'and', 'or', 'of', 'in', 'at', 'on', 'for', 'to',
            'dan', 'atau', 'dari', 'di', 'ke', 'pada', 'untuk',
        ];

        $words     = explode(' ', $normalized);
        $wordCount = count($words);

        $filtered = array_filter($words, function ($word) use ($commonWords, $wordCount) {
            $word = trim($word);

            if ($word === '') {
                return false;
            }
            // pertahankan kata pendek (< 3 huruf) supaya tidak kehilangan info
            if (strlen($word) < 3) {
                return false;
            }
            // buang common word hanya kalau bukan satu-satunya kata
            if (in_array($word, $commonWords) && $wordCount > 1) {
                return false;
            }

            return true;
        });

        if (empty($filtered)) {
            return $normalized;
        }

        sort($filtered);
        return implode(' ', $filtered);
    }

    /**
     * Ubah string kata inti jadi set unik (untuk perbandingan tepat).
     */
    private function wordSet($coreString)
    {
        if ($coreString === '') {
            return [];
        }

        $words = array_unique(array_filter(explode(' ', $coreString)));
        sort($words);

        return $words;
    }
}
