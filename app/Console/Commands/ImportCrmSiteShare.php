<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import mentah sheet "CRM Share - ALL CRM" ke stg_crm_site_share.
 *
 * Sengaja tanpa transformasi apa pun: tanggal, uang, boolean, dan nilai sampah
 * seperti "#N/A" / "-" masuk apa adanya. Normalisasi dikerjakan tahap berikutnya
 * dari staging, jadi bisa diulang tanpa import CSV lagi.
 *
 * Satu baris CSV = satu site. Kolom pertama dipakai apa adanya, urutan kolom
 * dicek terhadap HEADER_MAP supaya sheet yang strukturnya berubah gagal keras
 * daripada diam-diam salah kolom.
 */
class ImportCrmSiteShare extends Command
{
    protected $signature = 'crm:import-site-share
                            {file : Path ke file CSV hasil export sheet}
                            {--batch= : Label batch import (default: timestamp acak)}
                            {--fresh : Hapus seluruh isi staging sebelum import}
                            {--chunk=200 : Jumlah baris per insert}';

    protected $description = 'Import CSV kepemilikan site CRM ke tabel staging stg_crm_site_share';

    /**
     * Pasangan [header CSV, kolom staging], urut sesuai posisi kolom di sheet.
     *
     * Daftar (bukan map asosiatif) karena header "JABATAN" dan "GENDER" muncul
     * dua kali — sekali untuk PIC 1, sekali untuk PIC 2.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const COLUMN_PAIRS = [
        ['CRM', 'crm'],
        ['NO', 'no'],
        ['KODE SITE', 'kode_site'],
        ['NAMA PERUSAHAAN', 'nama_perusahaan'],
        ['NO PKS', 'no_pks'],
        ['NAMA PROYEK', 'nama_proyek'],
        ['Status PKS', 'status_pks'],
        ['BIDANG USAHA', 'bidang_usaha'],
        ['SERVICE', 'service'],
        ['WILAYAH', 'wilayah'],
        ['ALAMAT PERUSAHAAN', 'alamat_perusahaan'],
        ['JENIS PERUSAHAAN', 'jenis_perusahaan'],
        ['LOKASI SITE', 'lokasi_site'],
        ['Kota', 'kota'],
        ['PROVINSI', 'provinsi'],
        ['PMA/PMDN', 'pma_pmdn'],
        ['AWAL GABUNG', 'awal_gabung'],
        ['MASA KONTRAK', 'masa_kontrak'],
        ['BULAN', 'bulan'],
        ['LOYALITY', 'loyality'],
        ['START DATE', 'start_date'],
        ['END CONTRACT', 'end_contract'],
        ['Deadline', 'deadline'],
        ['KODE TAHAP', 'kode_tahap'],
        ['TAHAP REKONTRAK', 'tahap_rekontrak'],
        ['KODE REALISASI', 'kode_realisasi'],
        ['REALISASI TAHAP KONTRAK', 'realisasi_tahap_kontrak'],
        ['Tanggal Pengiriman Proposal', 'tanggal_pengiriman_proposal'],
        ['Tanggal Terminate Site', 'tanggal_terminate_site'],
        ['JUMLAH HC', 'jumlah_hc'],
        ['Total Invoice', 'total_invoice'],
        ['% MF', 'persen_mf'],
        ['Nominal MF', 'nominal_mf'],
        ['Prosentase BPJS TK', 'prosentase_bpjs_tk'],
        ['Nominal BPJS TK', 'nominal_bpjs_tk'],
        ['Prosentase BPJS KES', 'prosentase_bpjs_kes'],
        ['Nominal BPJS KES', 'nominal_bpjs_kes'],
        ['AS TK', 'as_tk'],
        ['AS KES', 'as_kes'],
        ['OHC', 'ohc'],
        ['THR PROVISI', 'thr_provisi'],
        ['THR DITAGIHKAN', 'thr_ditagihkan'],
        ['Penagihan Selisih THR', 'penagihan_selisih_thr'],
        ['KAPORLAP', 'kaporlap'],
        ['DEVICES', 'devices'],
        ['PELAKSANAAN TRAINING DALAM 1 TAHUN', 'pelaksanaan_training_dalam_1_tahun'],
        ['BIAYA TRAINING DALAM 1 TAHUN', 'biaya_training_dalam_1_tahun'],
        ['KIRIM INVOICE', 'kirim_invoice'],
        ['TIME OF PAYMENT', 'time_of_payment'],
        ['TANGGAL GAJI', 'tanggal_gaji'],
        ['PENDAFTARAN PKS', 'pendaftaran_pks'],
        ['STATUS PENDAFTARAN PKS', 'status_pendaftaran_pks'],
        ['Biaya Pendaftaran PKS', 'biaya_pendaftaran_pks'],
        ['No Bukti Pendaftaran', 'no_bukti_pendaftaran'],
        ['No Bukti Pendaftaran PKWT', 'no_bukti_pendaftaran_pkwt'],
        ['PIC 1', 'pic_1'],
        ['JABATAN', 'jabatan_1'],
        ['GENDER', 'gender_1'],
        ['PIC 2', 'pic_2'],
        ['JABATAN', 'jabatan_2'],
        ['GENDER', 'gender_2'],
        ['No Telp', 'no_telp'],
        ['HUT Perusahaan', 'hut_perusahaan'],
        ['KATEGORI SESUAI HEADCOUNT', 'kategori_sesuai_headcount'],
        ['GRUP', 'grup'],
        ['NO GRUP', 'no_grup'],
        ['INDUK', 'induk'],
        ['BENDERA SAAT INI', 'bendera_saat_ini'],
        ['UMK', 'umk'],
        ['KOMPESASI PKWT', 'kompesasi_pkwt'],
        ['ALASAN PUTUS KONTRAK', 'alasan_putus_kontrak'],
        ['KRITERIA PUTUS KONTRAK', 'kriteria_putus_kontrak'],
        ['TARGET VISIT', 'target_visit'],
        ['REALISASI VISIT', 'realisasi_visit'],
        ['EMAIL PIC', 'email_pic'],
        ['BRANCH', 'branch'],
        ['TANGGAL EMAIL INTERNAL', 'tanggal_email_internal'],
        ['REF GRUP', 'ref_grup'],
        ['STATUS PKWT', 'status_pkwt'],
        ['SPV OPS', 'spv_ops'],
    ];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');

        if ($handle === false) {
            $this->error('File tidak bisa dibuka.');

            return self::FAILURE;
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            $this->error('File kosong.');

            return self::FAILURE;
        }

        // Buang BOM UTF-8 di kolom pertama kalau ada
        $header[0] = preg_replace('/^﻿/', '', (string) $header[0]);

        $columns = $this->resolveColumns($header);

        if ($columns === null) {
            fclose($handle);

            return self::FAILURE;
        }

        $batch = $this->option('batch') ?: 'import_'.now()->format('Ymd_His').'_'.Str::random(4);
        $chunkSize = max(1, (int) $this->option('chunk'));

        if ($this->option('fresh')) {
            DB::table('stg_crm_site_share')->truncate();
            $this->warn('Staging dikosongkan (--fresh).');
        }

        $rowNumber = 1; // header = baris 1
        $buffer = [];
        $inserted = 0;
        $skipped = 0;
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Baris kosong total (sisa baris sheet) — lewati, tapi tetap hitung
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                $skipped++;

                continue;
            }

            $record = [
                'import_batch' => $batch,
                'row_number' => $rowNumber,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($columns as $index => $column) {
                $value = trim((string) ($row[$index] ?? ''));
                $record[$column] = $value === '' ? null : $value;
            }

            $buffer[] = $record;

            if (count($buffer) >= $chunkSize) {
                DB::table('stg_crm_site_share')->insert($buffer);
                $inserted += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('stg_crm_site_share')->insert($buffer);
            $inserted += count($buffer);
        }

        fclose($handle);

        $this->info("Batch      : {$batch}");
        $this->info("Baris masuk: {$inserted}");
        $this->info("Baris kosong dilewati: {$skipped}");
        $this->line('Cek: SELECT * FROM stg_crm_site_share WHERE import_batch = '."'{$batch}'".' LIMIT 5;');

        return self::SUCCESS;
    }

    /**
     * Petakan posisi kolom CSV ke kolom staging.
     *
     * Pencocokan lewat nama header, bukan posisi, supaya kolom yang digeser di
     * sheet tetap masuk ke kolom yang benar. Header kembar (JABATAN/GENDER)
     * dipakai berurutan: kemunculan pertama ke slot PIC 1, kedua ke PIC 2.
     * Header tak dikenal atau kolom kunci hilang = gagal keras, biar sheet yang
     * berubah struktur tidak diam-diam masuk ke kolom yang salah.
     *
     * @param  array<int, string|null>  $header
     * @return array<int, string>|null
     */
    private function resolveColumns(array $header): ?array
    {
        $available = self::COLUMN_PAIRS;
        $columns = [];
        $unknown = [];

        foreach ($header as $index => $raw) {
            $name = trim((string) $raw);

            if ($name === '') {
                continue;
            }

            $slot = null;
            foreach ($available as $key => [$expected, $column]) {
                if (strcasecmp($expected, $name) === 0) {
                    $slot = $key;
                    break;
                }
            }

            if ($slot === null) {
                $unknown[] = $name;

                continue;
            }

            $columns[$index] = $available[$slot][1];
            unset($available[$slot]);
        }

        if ($unknown !== []) {
            $this->error('Header tidak dikenal / kembar berlebih (sheet berubah?): '.implode(', ', $unknown));

            return null;
        }

        $missing = array_diff(
            ['crm', 'kode_site', 'nama_perusahaan', 'no_pks', 'induk'],
            array_values($columns)
        );

        if ($missing !== []) {
            $this->error('Kolom kunci tidak ada di CSV: '.implode(', ', $missing));

            return null;
        }

        return $columns;
    }
}
