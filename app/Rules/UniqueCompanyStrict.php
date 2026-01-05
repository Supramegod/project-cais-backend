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
        // Reset similar companies
        $this->similarCompanies = [];

        // Normalisasi input
        $input = $this->normalize($value);

        // Query untuk mengambil data perusahaan
        $query = Leads::whereNull('deleted_at');

        // Jika ada excludeId (untuk update), kecualikan record tersebut
        if ($this->excludeId) {
            $query->where('id', '!=', $this->excludeId);
        }

        $companies = $query->pluck('nama_perusahaan')->toArray();

        // Jika tidak ada data di database, langsung return true
        if (empty($companies)) {
            return true;
        }

        $isSimilar = false;

        foreach ($companies as $company) {
            if (empty($company)) continue;

            $normalized = $this->normalize($company);

            // Skip jika kosong setelah normalisasi
            if (empty($input) || empty($normalized)) {
                continue;
            }

            $similarityReasons = [];

            // 1. Cek exact match setelah normalisasi
            if ($input === $normalized) {
                $similarityReasons[] = "nama persis sama";
            }

            // 2. Cek apakah satu string mengandung string lainnya
            if (str_contains($normalized, $input) || str_contains($input, $normalized)) {
                $similarityReasons[] = "mengandung nama yang sama";
            }

            // 3. Cek kemiripan menggunakan similar_text dengan threshold yang lebih longgar
            similar_text($input, $normalized, $percent);
            if ($percent > 90) { // Naik dari 80 ke 90
                $similarityReasons[] = "tingkat kemiripan {$percent}%";
            }

            // 4. Cek kata kunci utama (token matching) - hanya jika kata panjang dan banyak yang sama
            if ($this->hasCommonKeywords($input, $normalized)) {
                $similarityReasons[] = "memiliki kata kunci yang sama";
            }

            // 5. Cek Levenshtein distance (edit distance) dengan threshold lebih longgar
            $distance = levenshtein($input, $normalized);
            $maxLength = max(strlen($input), strlen($normalized));
            if ($maxLength > 0) {
                $similarity = (1 - $distance / $maxLength) * 100;
                
                if ($similarity > 92) { // Naik dari 85 ke 92
                    $similarityReasons[] = "tingkat kemiripan {$similarity}% berdasarkan edit distance";
                }
            }

            // Jika ada alasan kemiripan, tambahkan ke daftar
            if (!empty($similarityReasons)) {
                $isSimilar = true;
                $this->similarCompanies[] = [
                    'nama_perusahaan' => $company,
                    'alasan' => implode(', ', $similarityReasons)
                ];
            }
        }

        return !$isSimilar;
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

        // Batasi maksimal 3 perusahaan yang ditampilkan agar tidak terlalu panjang
        if (count($similarNames) > 3) {
            $similarNames = array_slice($similarNames, 0, 3);
            $message .= implode(', ', $similarNames) . ', dan lainnya';
        } else {
            $message .= implode(', ', $similarNames);
        }

        return $message;
    }

    private function normalize($text)
    {
        if (empty($text)) {
            return '';
        }

        // Ubah huruf ke kecil
        $text = strtolower(trim($text));
        
        // Hapus semua tanda baca dan karakter khusus terlebih dahulu
        // Termasuk: . , ! ? ; : " ' ` ( ) [ ] { } / \ | @ # $ % ^ & * + = - _ ~ < >
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Hapus angka jika tidak diperlukan dalam perbandingan
        $text = preg_replace('/[0-9]/', '', $text);
        
        // Ganti multiple spaces dengan single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Hapus kata umum yang tidak penting untuk perbandingan
        $commonWords = [
            'pt', 'cv', 'ud', 'tbk', 'company', 'corp', 'inc', 'ltd', 
            'group', 'holding', 'corporation', 'international', 'global',
            'national', 'pusat', 'cabang', 'kantor', 'toko', 'warung','industri', 'perusahaan', 'enterprise', 'services', 'solution', 'tech',
            'technology','nasional','cabang'
        ];
        
        // Hapus nama geografis dari text
        $words = explode(' ', $text);
        $filteredWords = array_filter($words, function($word) use ($commonWords) {
            // Hapus common words
            if (in_array($word, $commonWords)) {
                return false;
            }
            
            // Hapus nama geografis
            if (in_array($word, $this->geographicNames)) {
                return false;
            }
            
            // Hapus kata yang terlalu pendek
            if (strlen($word) <= 2) {
                return false;
            }
            
            return true;
        });
        
        // Urutkan kata untuk konsistensi dan gabungkan kembali
        sort($filteredWords);
        return implode(' ', $filteredWords);
    }

    private function hasCommonKeywords($text1, $text2)
    {
        // Filter kata yang panjangnya lebih dari 4 karakter (lebih spesifik)
        $words1 = array_filter(explode(' ', $text1), function($word) {
            return strlen($word) > 4; // Naik dari 3 ke 4
        });
        
        $words2 = array_filter(explode(' ', $text2), function($word) {
            return strlen($word) > 4; // Naik dari 3 ke 4
        });

        if (empty($words1) || empty($words2)) {
            return false;
        }

        // Cari kata yang sama di kedua teks
        $commonWords = array_intersect($words1, $words2);
        
        // Perlu ada minimal 2 kata kunci yang sama DAN panjang
        // ATAU 1 kata yang sangat panjang (lebih dari 8 karakter)
        if (count($commonWords) >= 2) {
            return true;
        }
        
        // Cek apakah ada 1 kata yang sangat spesifik (panjang)
        foreach ($commonWords as $word) {
            if (strlen($word) > 8) {
                return true;
            }
        }
        
        return false;
    }
}