<?php

namespace App\Rules;

use App\Models\Leads;
use Illuminate\Contracts\Validation\Rule;
use App\Models\Company;

class UniqueCompanyStrict implements Rule
{
    public function passes($attribute, $value)
    {
        // Normalisasi input
        $input = $this->normalize($value);

        // Ambil semua nama perusahaan dari model Company (field "name")
        $companies = Leads::pluck('nama_perusahaan')->toArray();

        foreach ($companies as $company) {
            $normalized = $this->normalize($company);

            // Cek exact match
            if ($input === $normalized) return false;

            // Cek urutan kata dibalik (PT Maju Jaya == Maju Jaya PT)
            if (implode(' ', array_reverse(explode(' ', $input))) === $normalized) return false;

            // Cek kemiripan huruf menggunakan similar_text
            similar_text($input, $normalized, $percent);
            if ($percent > 95) return false; // ubah ke 90 kalau mau super ketat
        }

        return true;
    }

    public function message()
    {
        return 'Nama perusahaan terlalu mirip dengan yang sudah ada.';
    }

    private function normalize($text)
    {
        // Ubah huruf ke kecil, hapus semua spasi, tanda baca, dan angka
        $text = strtolower($text);
        $text = preg_replace('/[^a-z]/', '', $text);
        return trim($text);
    }
}
