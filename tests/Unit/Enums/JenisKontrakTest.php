<?php

namespace Tests\Unit\Enums;

use App\Enums\JenisKontrak;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mengunci normalisasi jenis_kontrak. Kolom ini string bebas yang berasal dari
 * dump lama, jadi predikatnya harus tahan kapitalisasi acak, spasi berlebih,
 * null, dan nilai warisan yang tidak ada di daftar resmi.
 */
class JenisKontrakTest extends TestCase
{
    public static function generalCleaningProvider(): array
    {
        return [
            'kapital penuh' => ['GENERAL CLEANING', true],
            'title case' => ['General Cleaning', true],
            'huruf kecil' => ['general cleaning', true],
            'ada spasi pinggir' => ['  General Cleaning  ', true],
            'pkhl' => ['PKHL', false],
            'reguler' => ['Reguler', false],
            'nilai warisan' => ['TERPADU', false],
            'null' => [null, false],
            'string kosong' => ['', false],
        ];
    }

    #[DataProvider('generalCleaningProvider')]
    public function test_is_general_cleaning(?string $jenisKontrak, bool $expected): void
    {
        $this->assertSame($expected, JenisKontrak::isGeneralCleaning($jenisKontrak));
    }

    public static function bpjsKesOpsionalProvider(): array
    {
        return [
            'general cleaning' => ['General Cleaning', true],
            'pkhl' => ['PKHL', true],
            'pkhl huruf kecil' => ['pkhl', true],
            'reguler' => ['Reguler', false],
            'borongan' => ['Borongan', false],
            'event gaji harian' => ['Event Gaji Harian', false],
            'nilai warisan' => ['TERPADU', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('bpjsKesOpsionalProvider')]
    public function test_is_bpjs_kes_opsional(?string $jenisKontrak, bool $expected): void
    {
        $this->assertSame($expected, JenisKontrak::isBpjsKesOpsional($jenisKontrak));
    }

    /**
     * Provider sendiri, tidak menumpang bpjsKesOpsionalProvider. Kedua aturan
     * kebetulan mencakup kontrak yang sama hari ini tapi dasarnya berbeda, jadi
     * ketika salah satunya berubah kegagalannya harus muncul di test yang tepat.
     */
    public static function upahHarianProvider(): array
    {
        return [
            'general cleaning' => ['General Cleaning', true],
            'pkhl' => ['PKHL', true],
            'pkhl huruf kecil' => ['pkhl', true],
            'reguler' => ['Reguler', false],
            'borongan' => ['Borongan', false],
            'event gaji harian' => ['Event Gaji Harian', false],
            'nilai warisan' => ['TERPADU', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('upahHarianProvider')]
    public function test_is_upah_harian(?string $jenisKontrak, bool $expected): void
    {
        $this->assertSame($expected, JenisKontrak::isUpahHarian($jenisKontrak));
    }

    public function test_from_nama_returns_null_for_unknown_value(): void
    {
        $this->assertNull(JenisKontrak::fromNama('KONTRAK TIDAK DIKENAL'));
        $this->assertSame(JenisKontrak::Pkhl, JenisKontrak::fromNama(' pkhl '));
    }
}
