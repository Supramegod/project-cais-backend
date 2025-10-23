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

            // 3. Cek kemiripan menggunakan similar_text dengan threshold ketat
            similar_text($input, $normalized, $percent);
            if ($percent > 80) {
                $similarityReasons[] = "tingkat kemiripan {$percent}%";
            }

            // 4. Cek kata kunci utama (token matching)
            if ($this->hasCommonKeywords($input, $normalized)) {
                $similarityReasons[] = "memiliki kata kunci yang sama";
            }

            // 5. Cek Levenshtein distance (edit distance)
            $distance = levenshtein($input, $normalized);
            $maxLength = max(strlen($input), strlen($normalized));
            if ($maxLength > 0) {
                $similarity = (1 - $distance / $maxLength) * 100;
                
                if ($similarity > 85) {
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
        
        // Hapus semua karakter non-alphanumeric dan non-spasi, termasuk tanda baca
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        
        // Ganti multiple spaces dengan single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Hapus kata umum yang tidak penting untuk perbandingan
        $commonWords = [
            'pt', 'cv', 'ud', 'tbk', 'company', 'corp', 'inc', 'ltd', 
            'indonesia', 'surabaya', 'jakarta', 'bandung', 'semarang', 
            'yogyakarta', 'indonesia', 'group', 'holding', 'corporation',
            'kupang', 'bali', 'medan', 'makassar', 'palembang', 'kebumen',
            // Tambahkan kata umum lainnya yang sering muncul
        ];
        
        $words = array_filter(explode(' ', $text), function($word) use ($commonWords) {
            return !in_array($word, $commonWords) && strlen($word) > 2;
        });
        
        // Urutkan kata untuk konsistensi dan gabungkan kembali
        sort($words);
        return implode(' ', $words);
    }

    private function hasCommonKeywords($text1, $text2)
    {
        $words1 = array_filter(explode(' ', $text1), function($word) {
            return strlen($word) > 3;
        });
        
        $words2 = array_filter(explode(' ', $text2), function($word) {
            return strlen($word) > 3;
        });

        if (empty($words1) || empty($words2)) {
            return false;
        }

        // Cari kata yang sama di kedua teks
        $commonWords = array_intersect($words1, $words2);
        
        // Jika ada minimal 3 kata kunci yang sama, anggap mirip
        return count($commonWords) >= 3;
    }
}