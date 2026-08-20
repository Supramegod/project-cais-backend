<?php

namespace App\Enums;

enum JenisKontrak: string
{
    case Reguler = 'REGULER';
    case EventGajiHarian = 'EVENT GAJI HARIAN';
    case Pkhl = 'PKHL';
    case Borongan = 'BORONGAN';
    case GeneralCleaning = 'GENERAL CLEANING';

    /**
     * Nilai jenis_kontrak berasal dari dump lama sehingga kapitalisasinya tidak
     * konsisten dan masih ada nilai warisan di luar daftar ini. Nilai tak dikenal
     * sengaja dikembalikan null supaya semua predikat di bawah jatuh ke perilaku
     * kontrak umum, bukan melempar error.
     */
    public static function fromNama(?string $jenisKontrak): ?self
    {
        return self::tryFrom(strtoupper(trim($jenisKontrak ?? '')));
    }

    public static function isGeneralCleaning(?string $jenisKontrak): bool
    {
        return self::fromNama($jenisKontrak) === self::GeneralCleaning;
    }

    /**
     * General Cleaning dan PKHL adalah pekerjaan sekali jalan berdurasi pendek,
     * jadi BPJS Kesehatan tidak wajib: default mati di step 5 dan opt-out
     * is_bpjs_kes tetap dihormati walau penjamin kesehatannya BPJS.
     *
     * Sengaja dipisah dari isUpahHarian() walau himpunannya kebetulan sama —
     * dasar aturannya berbeda dan bisa berubah sendiri-sendiri.
     */
    public static function isBpjsKesOpsional(?string $jenisKontrak): bool
    {
        return in_array(self::fromNama($jenisKontrak), [self::GeneralCleaning, self::Pkhl], true);
    }

    /**
     * Upah pada GC dan PKHL disimpan sebagai upah harian sehingga harus dikalikan
     * hari kerja lebih dulu untuk mendapatkan upah bulanan.
     */
    public static function isUpahHarian(?string $jenisKontrak): bool
    {
        return in_array(self::fromNama($jenisKontrak), [self::GeneralCleaning, self::Pkhl], true);
    }
}
