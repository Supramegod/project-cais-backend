<?php

namespace App\Rules;

use App\Models\Leads;
use App\Models\Village;
use App\Models\Benua;
use App\Models\City;
use App\Models\Province;
use App\Models\District;
use Illuminate\Contracts\Validation\Rule;

class UniqueCompanyStrict implements Rule
{
    protected $excludeId;
    protected $similarCompanies = [];
    protected $geographicNames = [];

    public function __construct($excludeId = null)
    {
        $this->excludeId = $excludeId;
        $this->loadGeographicNames();
    }

    /**
     * Load semua nama geografis dari database
     */
    private function loadGeographicNames()
    {
        // Ambil nama villages
        $villages = Village::pluck('name')
            ->map(fn($name) => strtolower(trim($name)))
            ->filter()
            ->toArray();

        // Ambil nama benua
        $benuas = Benua::pluck('nama_benua')
            ->map(fn($name) => strtolower(trim($name)))
            ->filter()
            ->toArray();

        // Ambil nama cities
        $cities = City::where('is_active', 1)
            ->pluck('name')
            ->map(fn($name) => strtolower(trim($name)))
            ->filter()
            ->toArray();

        // Ambil nama provinces
        $provinces = Province::where('is_active', 1)
            ->pluck('name')
            ->map(fn($name) => strtolower(trim($name)))
            ->filter()
            ->toArray();

        // Ambil nama districts
        $districts = District::pluck('name')
            ->map(fn($name) => strtolower(trim($name)))
            ->filter()
            ->toArray();

        // Gabungkan semua nama geografis
        $this->geographicNames = array_unique(array_merge(
            $villages,
            $benuas,
            $cities,
            $provinces,
            $districts
        ));
    }

    public function passes($attribute, $value)
    {
        // Debug logging
        // \Log::info('UniqueCompanyStrict validation started', [
        //     'attribute' => $attribute,
        //     'value' => $value,
        //     'excludeId' => $this->excludeId
        // ]);

        // Reset similar companies
        $this->similarCompanies = [];

        // Normalisasi input untuk perbandingan
        $inputNormalized = $this->normalize($value);

        // Ekstrak core words (kata inti tanpa common words)
        $inputCoreWords = $this->extractCoreWords($value);

        \Log::info('After normalization', [
            'original' => $value,
            'normalized' => $inputNormalized,
            'core_words' => $inputCoreWords
        ]);

        // Query untuk mengambil data perusahaan
        $query = Leads::whereNull('deleted_at');

        // Jika ada excludeId (untuk update), kecualikan record tersebut
        if ($this->excludeId) {
            $query->where('id', '!=', $this->excludeId);
        }

        $companies = $query->pluck('nama_perusahaan')->toArray();

        // \Log::info('Companies in database', [
        //     'count' => count($companies),
        //     'companies' => array_slice($companies, 0, 5)
        // ]);

        // Jika tidak ada data di database, langsung return true
        if (empty($companies)) {
            \Log::info('No companies in database, validation passed');
            return true;
        }

        $isSimilar = false;

        foreach ($companies as $company) {
            if (empty($company))
                continue;

            $companyNormalized = $this->normalize($company);
            $companyCoreWords = $this->extractCoreWords($company);

            // Skip jika kosong setelah normalisasi
            if (empty($inputNormalized) || empty($companyNormalized)) {
                continue;
            }

            $similarityReasons = [];

            // 1. EXACT MATCH - Prioritas tertinggi
            if ($inputNormalized === $companyNormalized) {
                $similarityReasons[] = "nama persis sama";
            }

            // 2. CORE WORDS EXACT MATCH - Jika semua kata inti sama persis
            if (!empty($inputCoreWords) && !empty($companyCoreWords)) {
                if ($inputCoreWords === $companyCoreWords) {
                    $similarityReasons[] = "kata inti persis sama";
                }
            }

            // 3. SUBSTRING MATCH - Hanya jika salah satu SEPENUHNYA mengandung yang lain
            // DAN panjang substring minimal 60% dari string yang lebih panjang
            $longerLength = max(strlen($inputNormalized), strlen($companyNormalized));
            $shorterLength = min(strlen($inputNormalized), strlen($companyNormalized));

            if ($longerLength > 0 && ($shorterLength / $longerLength) >= 0.6) {
                if (str_contains($companyNormalized, $inputNormalized)) {
                    $similarityReasons[] = "'{$inputNormalized}' adalah bagian dari '{$companyNormalized}'";
                } elseif (str_contains($inputNormalized, $companyNormalized)) {
                    $similarityReasons[] = "'{$companyNormalized}' adalah bagian dari '{$inputNormalized}'";
                }
            }

            // 4. SIMILAR_TEXT - Tingkatkan threshold menjadi 90%
            similar_text($inputNormalized, $companyNormalized, $percent);
            if ($percent >= 90) {
                $similarityReasons[] = sprintf("tingkat kemiripan %.1f%%", $percent);
            }

            // 5. LEVENSHTEIN DISTANCE - Tingkatkan threshold menjadi 92%
            $distance = levenshtein($inputNormalized, $companyNormalized);
            $maxLength = max(strlen($inputNormalized), strlen($companyNormalized));
            if ($maxLength > 0) {
                $similarity = (1 - $distance / $maxLength) * 100;

                if ($similarity >= 92) {
                    $similarityReasons[] = sprintf("edit distance %.1f%%", $similarity);
                }
            }

            // 6. KEYWORD MATCHING - Lebih ketat
            $keywordMatchResult = $this->hasSignificantKeywordOverlap(
                $inputCoreWords,
                $companyCoreWords
            );

            if ($keywordMatchResult['is_match']) {
                $similarityReasons[] = $keywordMatchResult['reason'];
            }

            // Jika ada alasan kemiripan, tambahkan ke daftar
            if (!empty($similarityReasons)) {
                $isSimilar = true;
                $this->similarCompanies[] = [
                    'nama_perusahaan' => $company,
                    'alasan' => implode(', ', $similarityReasons)
                ];

                \Log::info('Similar company found', [
                    'input' => $value,
                    'similar_to' => $company,
                    'reasons' => $similarityReasons
                ]);
            }
        }

        $result = !$isSimilar;
        \Log::info('UniqueCompanyStrict validation result', [
            'passed' => $result,
            'similar_companies_count' => count($this->similarCompanies)
        ]);

        return $result;
    }

    public function message()
    {
        if (empty($this->similarCompanies)) {
            return 'Nama perusahaan terlalu mirip dengan yang sudah ada di database.';
        }

        $message = 'Nama perusahaan terlalu mirip dengan: ';

        $similarNames = [];
        foreach ($this->similarCompanies as $company) {
            $similarNames[] = "{$company['nama_perusahaan']} ({$company['alasan']})";
        }

        // Batasi maksimal 3 perusahaan yang ditampilkan
        if (count($similarNames) > 3) {
            $similarNames = array_slice($similarNames, 0, 3);
            $message .= implode(', ', $similarNames) . ', dan ' . (count($this->similarCompanies) - 3) . ' lainnya';
        } else {
            $message .= implode(', ', $similarNames);
        }

        return $message;
    }

    /**
     * Normalisasi text - JANGAN hapus angka terlalu dini
     */
    private function normalize($text)
    {
        if (empty($text)) {
            return '';
        }

        // Ubah huruf ke kecil dan trim
        $text = strtolower(trim($text));

        // Hapus tanda baca DAN angka di tahap ini
        // Gabungkan untuk konsistensi
        $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);

        // Ganti multiple spaces dengan single space
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Ekstrak core words - kata-kata inti setelah membuang common words
     */
    private function extractCoreWords($text)
    {
        if (empty($text)) {
            return '';
        }

        // Normalisasi dulu
        $normalized = $this->normalize($text);

        // Common words yang akan dihapus
        $commonWords = [
            'pt',
            'cv',
            'ud',
            'tbk',
            'persero',
            'perusahaan',
            'company',
            'corp',
            'corporation',
            'inc',
            'ltd',
            'group',
            'holding',
            'international',
            'global',
            'national',
            'nasional',
            'pusat',
            'cabang',
            'kantor',
            'toko',
            'warung',
            'industri',
            'enterprise',
            'services',
            'service',
            'solution',
            'tech',
            'technology',
            'technologies',
            'the',
            'and',
            'or',
            'of',
            'in',
            'at',
            'on',
            'for',
            'to',
            'dan',
            'atau',
            'dari',
            'di',
            'ke',
            'pada',
            'untuk',
        ];

        $words = explode(' ', $normalized);
        $wordCount = count($words);

        $filteredWords = array_filter($words, function ($word) use ($commonWords, $wordCount) {
            $word = trim($word);

            // Jangan hapus jika kosong
            if (empty($word)) {
                return false;
            }

            // Jangan hapus kata sangat pendek (< 3 karakter)
            if (strlen($word) < 3) {
                return false;
            }

            // Hapus common words hanya jika bukan satu-satunya kata
            if (in_array($word, $commonWords) && $wordCount > 1) {
                return false;
            }

            // Hapus nama geografis hanya jika bukan satu-satunya kata
            if (in_array($word, $this->geographicNames) && $wordCount > 1) {
                return false;
            }

            return true;
        });

        // Jika semua kata terhapus, kembalikan normalized text
        if (empty($filteredWords)) {
            return $normalized;
        }

        // Urutkan dan gabungkan untuk konsistensi
        sort($filteredWords);
        return implode(' ', $filteredWords);
    }

    /**
     * Cek apakah ada overlap kata kunci yang signifikan
     * LEBIH KETAT: butuh minimal 2 kata ATAU 1 kata sangat panjang DAN spesifik
     */
    private function hasSignificantKeywordOverlap($coreWords1, $coreWords2)
    {
        if (empty($coreWords1) || empty($coreWords2)) {
            return ['is_match' => false, 'reason' => ''];
        }

        $words1 = array_filter(explode(' ', $coreWords1), function ($word) {
            return strlen($word) >= 4; // Minimal 4 karakter
        });

        $words2 = array_filter(explode(' ', $coreWords2), function ($word) {
            return strlen($word) >= 4; // Minimal 4 karakter
        });

        if (empty($words1) || empty($words2)) {
            return ['is_match' => false, 'reason' => ''];
        }

        // Cari kata yang sama
        $commonWords = array_intersect($words1, $words2);
        $commonCount = count($commonWords);

        // CASE 1: Minimal 3 kata kunci sama (sangat kuat)
        if ($commonCount >= 3) {
            return [
                'is_match' => true,
                'reason' => $commonCount . ' kata kunci sama: ' . implode(', ', array_slice($commonWords, 0, 3))
            ];
        }

        // CASE 2: 2 kata kunci sama DAN salah satunya panjang (>= 6 karakter)
        if ($commonCount >= 2) {
            $hasLongWord = false;
            foreach ($commonWords as $word) {
                if (strlen($word) >= 6) {
                    $hasLongWord = true;
                    break;
                }
            }

            if ($hasLongWord) {
                return [
                    'is_match' => true,
                    'reason' => '2 kata kunci signifikan: ' . implode(', ', array_slice($commonWords, 0, 2))
                ];
            }
        }

        // CASE 3: 1 kata yang SANGAT spesifik (>= 10 karakter) DAN bukan kata umum
        if ($commonCount >= 1) {
            foreach ($commonWords as $word) {
                if (strlen($word) >= 10) {
                    // Cek apakah kata ini cukup unik (bukan repetisi sederhana)
                    if ($this->isUniqueWord($word)) {
                        return [
                            'is_match' => true,
                            'reason' => 'kata kunci sangat spesifik: ' . $word
                        ];
                    }
                }
            }
        }

        // CASE 4: Kata-kata yang sama adalah mayoritas dari kedua nama (>70%)
        $totalWords1 = count($words1);
        $totalWords2 = count($words2);
        $minWords = min($totalWords1, $totalWords2);

        if ($minWords > 0 && ($commonCount / $minWords) >= 0.7) {
            return [
                'is_match' => true,
                'reason' => 'mayoritas kata kunci sama (' . $commonCount . '/' . $minWords . ')'
            ];
        }

        return ['is_match' => false, 'reason' => ''];
    }

    /**
     * Cek apakah kata cukup unik (bukan repetisi sederhana seperti "mamamamama")
     */
    private function isUniqueWord($word)
    {
        $length = strlen($word);
        if ($length < 4) {
            return false;
        }

        // Cek repetisi karakter
        $chars = str_split($word);
        $uniqueChars = array_unique($chars);
        $uniqueRatio = count($uniqueChars) / $length;

        // Jika kurang dari 40% karakter unik, kemungkinan repetisi
        return $uniqueRatio >= 0.4;
    }
}