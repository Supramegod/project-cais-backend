<?php

namespace App\Services\Numbering;

/**
 * Helper bersama untuk membaca & menyusun segmen versi pada nomor dokumen
 * (Quotation, PKS, dan dokumen lain yang mengikuti format standar).
 *
 * Bentuk nomor:
 *
 *     {PREFIX}/{TIPE}/{COMPANY}/{LEADS}-{MMYYYY}-{SEQ}[-{CHAIN}]
 *                                       └── dateSeq ──┘  └ chain ┘
 *
 * `chain` adalah rangkaian segmen versi, mis. `K01`, atau `K01-V02`.
 *
 * ATURAN INTI — sebuah huruf tidak pernah muncul dua kali dalam satu chain.
 * Merevisi dokumen yang sudah bertipe revisi menaikkan counter `V`, bukan
 * menambah segmen `V` baru. Nesting hanya terjadi antar huruf berbeda:
 *
 *     ...-00001          + V  →  ...-00001-V01
 *     ...-00001-V01      + V  →  ...-00001-V02      (ganti, bukan tambah)
 *     ...-00001-K01      + V  →  ...-00001-K01-V01  (huruf beda → nesting)
 *     ...-00001-K01-V01  + V  →  ...-00001-K01-V02  (ganti segmen terakhir)
 *
 * Sebelum perbaikan ini, segmen selalu di-append sehingga muncul nomor rusak
 * seperti `QUOT/RVS/GSU/AABKJ-072025-00001-V02-V02-V02-V02`.
 */
class DocumentVersionChain
{
    /**
     * Huruf versi yang dikenali.
     *   V = revisi, K = rekontrak, A = addendum, R = cadangan (SPK/dokumen lain)
     */
    public const CODES = ['V', 'K', 'A', 'R'];

    /** Character class untuk dipakai di regex. */
    private const CODE_CLASS = 'VKAR';

    /**
     * Batas panjang nomor yang dianggap aman.
     *
     * CATATAN: tabel `sl_quotation` / `sl_spk` / `sl_pks` bersifat legacy dan
     * tidak punya migration di repo ini, jadi panjang kolom `nomor` yang
     * sebenarnya hanya bisa dipastikan lewat `SHOW CREATE TABLE` di produksi.
     * Nilai di bawah adalah batas konservatif supaya nomor tidak terpotong
     * diam-diam oleh MySQL. Sesuaikan setelah schema produksi dikonfirmasi.
     */
    public const MAX_LENGTH = 100;

    /**
     * Buang prefix `draft/` (dipakai PKS wizard sebelum finalize).
     */
    public static function stripDraft(?string $nomor): string
    {
        return preg_replace('/^draft\//', '', (string) $nomor);
    }

    /**
     * Pecah nomor menjadi bagian dateSeq dan chain versinya.
     *
     * @return array{dateSeq: ?string, chain: string[]}
     */
    public static function parse(?string $nomor): array
    {
        $clean = self::stripDraft($nomor);

        $pattern = '/-(\d{6}-\d{5})((?:-[' . self::CODE_CLASS . ']\d{2})*)$/';

        if (!preg_match($pattern, $clean, $m)) {
            return ['dateSeq' => null, 'chain' => []];
        }

        $chain = $m[2] === ''
            ? []
            : explode('-', ltrim($m[2], '-'));

        return ['dateSeq' => $m[1], 'chain' => $chain];
    }

    /**
     * Chain induk untuk segmen bertipe `$code`.
     *
     * Kalau chain parent sudah berakhir dengan huruf yang sama, segmen itu
     * dilepas — counter-nya yang akan naik, bukan segmen baru yang ditambah.
     * Inilah perbaikan atas bug penumpukan `-V02-V02-V02`.
     *
     * @param  string[] $parentChain
     * @return string[]
     */
    public static function prefixFor(array $parentChain, string $code): array
    {
        $chain = array_values($parentChain);
        $last = end($chain);

        if ($last !== false && self::codeOf($last) === $code) {
            array_pop($chain);
        }

        return $chain;
    }

    /**
     * Susun chain final dari prefix + segmen baru.
     *
     * @param  string[] $prefix
     * @return string[]
     */
    public static function append(array $prefix, string $code, int $counter): array
    {
        $chain = array_values($prefix);
        $chain[] = $code . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);

        return $chain;
    }

    /**
     * Rakit nomor lengkap.
     *
     * @param string   $base    mis. "QUOT/RVS/GSU/AABKJ-"
     * @param string[] $chain
     */
    public static function render(string $base, string $dateSeq, array $chain): string
    {
        $nomor = $base . $dateSeq;

        if ($chain !== []) {
            $nomor .= '-' . implode('-', $chain);
        }

        return $nomor;
    }

    /**
     * Counter berikutnya untuk `$code` di bawah `$prefix`, dihitung dari
     * nomor-nomor yang sudah ada dalam satu keluarga dokumen.
     *
     * Dihitung dari string nomor — bukan dari `count()` baris — supaya baris
     * yang di-soft-delete maupun urutan pembuatan tidak bisa membuat counter
     * mengulang nilai yang sudah terpakai.
     *
     * @param string[] $existingNomor
     * @param string[] $prefix
     */
    public static function nextCounter(array $existingNomor, string $dateSeq, array $prefix, string $code): int
    {
        $depth = count($prefix);
        $max = 0;

        foreach ($existingNomor as $nomor) {
            $parsed = self::parse($nomor);

            if ($parsed['dateSeq'] !== $dateSeq) {
                continue;
            }

            $chain = $parsed['chain'];

            // Harus tepat satu segmen lebih dalam dari prefix...
            if (count($chain) !== $depth + 1) {
                continue;
            }

            // ...dan segmen-segmen sebelumnya harus identik dengan prefix.
            if (array_slice($chain, 0, $depth) !== array_values($prefix)) {
                continue;
            }

            $last = $chain[$depth];

            if (self::codeOf($last) !== $code) {
                continue;
            }

            $max = max($max, (int) substr($last, 1));
        }

        return $max + 1;
    }

    /**
     * Ambil huruf dari sebuah segmen (`V02` → `V`).
     */
    public static function codeOf(string $segment): ?string
    {
        return preg_match('/^([' . self::CODE_CLASS . '])\d{2}$/', $segment, $m)
            ? $m[1]
            : null;
    }

    /**
     * Apakah nomor punya huruf versi yang muncul lebih dari sekali?
     * Dipakai oleh command audit untuk mendeteksi nomor rusak warisan bug lama.
     */
    public static function hasRepeatedCode(?string $nomor): bool
    {
        $chain = self::parse($nomor)['chain'];
        $codes = array_filter(array_map([self::class, 'codeOf'], $chain));

        return count($codes) !== count(array_unique($codes));
    }

    /**
     * Lempar exception kalau nomor melebihi batas aman, supaya kita tidak
     * menemukan nomor terpotong di database setelah fakta.
     *
     * @throws \RuntimeException
     */
    public static function assertLength(string $nomor): string
    {
        if (mb_strlen($nomor) > self::MAX_LENGTH) {
            throw new \RuntimeException(sprintf(
                'Nomor dokumen "%s" (%d karakter) melebihi batas %d karakter.',
                $nomor,
                mb_strlen($nomor),
                self::MAX_LENGTH
            ));
        }

        return $nomor;
    }

    /**
     * Sequence berikutnya untuk satu prefix bulanan, dihitung dari SEQ
     * tertinggi yang sudah dipakai (bukan dari jumlah baris).
     *
     * @param string[] $existingNomor
     */
    public static function nextSequence(array $existingNomor, string $prefix): int
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{5})/';
        $max = 0;

        foreach ($existingNomor as $nomor) {
            if (preg_match($pattern, self::stripDraft($nomor), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }
}
