<?php

namespace App\Console\Commands;

use App\Models\Pks;
use App\Models\Quotation;
use App\Models\Spk;
use App\Services\Numbering\DocumentVersionChain;
use Illuminate\Console\Command;

/**
 * Audit nomor dokumen Quotation / SPK / PKS.
 *
 * READ-ONLY — command ini tidak pernah menulis ke database.
 *
 * Dibuat untuk memetakan kerusakan warisan dua bug penomoran yang sudah
 * diperbaiki di QuotationNumberingService & PksNumberingService:
 *
 *  1. Counter versi menghitung dokumen referensi sebagai anggotanya sendiri,
 *     sehingga selalu menghasilkan `02`.
 *  2. Segmen versi selalu di-append, tidak pernah mengganti segmen berhuruf
 *     sama — menghasilkan nomor seperti
 *     `QUOT/RVS/GSU/AABKJ-072025-00001-V02-V02-V02-V02`.
 *
 * Plus masalah lama: SEQ dihitung dengan `count() + 1` di atas query yang
 * mengecualikan baris soft-deleted, sehingga nomor bisa terpakai ulang.
 */
class AuditDocumentNumbers extends Command
{
    protected $signature = 'docs:audit-nomor
                            {--doc=all : Batasi ke satu dokumen: quotation|spk|pks|all}
                            {--csv= : Tulis hasil ke file CSV (mis. storage/app/audit-nomor.csv)}
                            {--limit=50 : Jumlah baris yang ditampilkan per kategori di terminal}';

    protected $description = 'Laporkan nomor Quotation/SPK/PKS yang duplikat, punya segmen versi berulang, kepanjangan, atau tidak sesuai format (read-only)';

    /** dokumen => [model, kolom induk (null jika tidak punya)] */
    private const DOCS = [
        'quotation' => [Quotation::class, 'quotation_referensi_id'],
        'spk'       => [Spk::class, null],
        'pks'       => [Pks::class, 'pks_induk_id'],
    ];

    public function handle(): int
    {
        $target = strtolower((string) $this->option('doc'));

        if ($target !== 'all' && !isset(self::DOCS[$target])) {
            $this->error("Opsi --doc tidak dikenal: {$target}. Pilihan: quotation, spk, pks, all.");

            return self::FAILURE;
        }

        $docs = $target === 'all'
            ? array_keys(self::DOCS)
            : [$target];

        $findings = [];

        foreach ($docs as $doc) {
            [$modelClass] = self::DOCS[$doc];
            $findings = array_merge($findings, $this->auditDoc($doc, $modelClass));
        }

        $this->report($findings);

        if ($csvPath = $this->option('csv')) {
            $this->writeCsv($findings, $csvPath);
        }

        // Temuan bukan kegagalan command — laporan berhasil dibuat.
        return self::SUCCESS;
    }

    /**
     * @param  class-string $modelClass
     * @return array<int, array{doc: string, id: mixed, nomor: string, masalah: string, detail: string, usulan: string}>
     */
    private function auditDoc(string $doc, string $modelClass): array
    {
        $this->line("Memeriksa <info>{$doc}</info>…");

        $rows = $modelClass::withTrashed()
            ->whereNotNull('nomor')
            ->where('nomor', '<>', '')
            ->get(['id', 'nomor', 'deleted_at']);

        $findings = [];

        // --- Duplikat (nomor sama dipakai lebih dari satu baris) ---
        $byNomor = [];
        foreach ($rows as $row) {
            $byNomor[DocumentVersionChain::stripDraft($row->nomor)][] = $row;
        }

        foreach ($byNomor as $nomor => $group) {
            if (count($group) < 2) {
                continue;
            }

            $ids = implode(', ', array_map(fn ($r) => $r->id, $group));

            foreach ($group as $row) {
                $findings[] = $this->finding(
                    $doc,
                    $row,
                    'duplikat',
                    'Nomor dipakai oleh ' . count($group) . ' baris (id: ' . $ids . ')',
                    '' // penggantian duplikat butuh keputusan manual — dokumen mana yang dipertahankan
                );
            }
        }

        // --- Per-baris: segmen berulang, kepanjangan, format tidak cocok ---
        foreach ($rows as $row) {
            $nomor = (string) $row->nomor;

            if (DocumentVersionChain::hasRepeatedCode($nomor)) {
                $findings[] = $this->finding(
                    $doc,
                    $row,
                    'segmen-versi-berulang',
                    'Rantai versi: ' . implode('-', DocumentVersionChain::parse($nomor)['chain']),
                    $this->usulkanPerbaikan($nomor)
                );
            }

            if (mb_strlen($nomor) > DocumentVersionChain::MAX_LENGTH) {
                $findings[] = $this->finding(
                    $doc,
                    $row,
                    'terlalu-panjang',
                    mb_strlen($nomor) . ' karakter (batas ' . DocumentVersionChain::MAX_LENGTH . ')',
                    $this->usulkanPerbaikan($nomor)
                );
            }

            if (DocumentVersionChain::parse($nomor)['dateSeq'] === null) {
                $findings[] = $this->finding(
                    $doc,
                    $row,
                    'format-tidak-cocok',
                    'Tidak mengandung pola {MMYYYY}-{SEQ}',
                    ''
                );
            }
        }

        return $findings;
    }

    /**
     * Usulkan nomor pengganti menurut aturan baru: pertahankan hanya kemunculan
     * PERTAMA tiap huruf, dan pakai counter tertinggi yang pernah muncul untuk
     * huruf itu. `-V02-V02-V02-V02` → `-V02`.
     *
     * Ini usulan mekanis, bukan keputusan final — nomor yang sudah beredar di
     * PDF/kontrak mungkin tidak boleh diubah.
     */
    private function usulkanPerbaikan(string $nomor): string
    {
        $parsed = DocumentVersionChain::parse($nomor);

        if ($parsed['dateSeq'] === null) {
            return '';
        }

        $tertinggi = [];
        $urutan = [];

        foreach ($parsed['chain'] as $segment) {
            $code = DocumentVersionChain::codeOf($segment);

            if ($code === null) {
                continue;
            }

            if (!isset($tertinggi[$code])) {
                $urutan[] = $code;
                $tertinggi[$code] = 0;
            }

            $tertinggi[$code] = max($tertinggi[$code], (int) substr($segment, 1));
        }

        $chainBaru = array_map(
            fn ($code) => $code . str_pad((string) $tertinggi[$code], 2, '0', STR_PAD_LEFT),
            $urutan
        );

        $clean = DocumentVersionChain::stripDraft($nomor);
        $base = substr($clean, 0, strrpos($clean, $parsed['dateSeq']));

        return DocumentVersionChain::render($base, $parsed['dateSeq'], $chainBaru);
    }

    private function finding(string $doc, $row, string $masalah, string $detail, string $usulan): array
    {
        return [
            'doc'     => $doc,
            'id'      => $row->id,
            'nomor'   => (string) $row->nomor,
            'masalah' => $masalah,
            'detail'  => $detail . ($row->deleted_at ? ' [soft-deleted]' : ''),
            'usulan'  => $usulan,
        ];
    }

    private function report(array $findings): void
    {
        $this->newLine();

        if ($findings === []) {
            $this->info('Tidak ada temuan. Semua nomor sesuai format dan unik.');

            return;
        }

        $limit = (int) $this->option('limit');

        $perMasalah = [];
        foreach ($findings as $f) {
            $perMasalah[$f['masalah']][] = $f;
        }

        foreach ($perMasalah as $masalah => $group) {
            $this->newLine();
            $this->warn(sprintf('%s — %d temuan', $masalah, count($group)));

            $shown = array_slice($group, 0, $limit);

            $this->table(
                ['Dokumen', 'ID', 'Nomor', 'Detail', 'Usulan'],
                array_map(fn ($f) => [$f['doc'], $f['id'], $f['nomor'], $f['detail'], $f['usulan']], $shown)
            );

            if (count($group) > $limit) {
                $this->line(sprintf(
                    '  … %d temuan lain tidak ditampilkan. Pakai --csv untuk daftar lengkap.',
                    count($group) - $limit
                ));
            }
        }

        $this->newLine();
        $this->warn(sprintf('Total %d temuan. Command ini tidak mengubah data apa pun.', count($findings)));
    }

    private function writeCsv(array $findings, string $path): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->error("Gagal membuat direktori: {$dir}");

            return;
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            $this->error("Gagal menulis ke: {$path}");

            return;
        }

        fputcsv($handle, ['dokumen', 'id', 'nomor', 'masalah', 'detail', 'usulan']);

        foreach ($findings as $f) {
            fputcsv($handle, [$f['doc'], $f['id'], $f['nomor'], $f['masalah'], $f['detail'], $f['usulan']]);
        }

        fclose($handle);

        $this->info("Laporan lengkap ditulis ke: {$path}");
    }
}
