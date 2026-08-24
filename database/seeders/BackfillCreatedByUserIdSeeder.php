<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillCreatedByUserIdSeeder extends Seeder
{
    protected array $tables = [
        // P0 — Critical (report & relationship)
        'sl_activity_sales',
        'log_approval',
        'log_notification',
        // P1 — Core bisnis
        'sl_leads',
        'sl_quotation',
        'sl_quotation_site',
        'sl_quotation_detail',
        'sl_quotation_detail_wages',
        'sl_quotation_detail_hpp',
        'sl_quotation_detail_coss',
        'sl_quotation_detail_requirement',
        'sl_quotation_detail_tunjangan',
        'sl_quotation_aplikasi',
        'sl_quotation_chemical',
        'sl_quotation_devices',
        'sl_quotation_kaporlap',
        'sl_quotation_kerjasama',
        'sl_quotation_margin',
        'sl_quotation_ohc',
        'sl_quotation_pic',
        'sl_quotation_training',
        'sl_spk',
        'sl_spk_site',
        'sl_pks',
        'sl_pks_perjanjian',
        'sl_pks_import',
        'sl_site',
        'sl_putus_kontrak',
        'sl_perusahaan_groups',
        'sl_perusahaan_groups_d',
        'sl_issue',
        'sl_activity_sales_file',
        // P2 — Transaksi pendukung
        'sl_submission',
        'sl_good_receipt',
        'sl_good_receipt_d',
        'sl_purchase_order',
        'sl_purchase_order_d',
        'sl_purchase_request',
        'sl_purchase_request_d',
        'sl_receiving_notes',
        'sl_receiving_notes_d',
        'sl_customer',
        'log_error',
        // P3 — Master data
        'm_barang',
        'm_barang_default_qty',
        'm_barang_import',
        'm_jenis_barang',
        'm_jenis_visit',
        'm_kebutuhan',
        'm_kebutuhan_detail',
        'm_kebutuhan_detail_requirement',
        'm_kebutuhan_detail_tunjangan',
        'm_management_fee',
        'm_requirement_posisi',
        'm_salary_rule',
        'm_tim_sales',
        'm_tim_sales_d',
        'm_tunjangan',
        'm_tunjangan_posisi',
        'm_training',
        'm_top',
        'm_ump',
        'm_umk',
        'm_umsk',
        'm_umsp',
        'm_platform',
        'm_status_leads',
        'm_status_pks',
        'm_status_quotation',
        'm_status_spk',
        'm_aplikasi_pendukung',
        'm_bidang_perusahaan',
        'm_jabatan_pic',
        'm_kategori_sesuai_hc',
        'm_loyalty',
        'm_rule_thr',
        'sysmenu',
        'sysmenu_role',
    ];

    protected int $chunkSize = 500;

    /** @var array<string, array<string, int>> Tingkat normalisasi: nama_raw => {user_id} */
    private array $fuzzyCache = [];

    public function run(): void
    {
        // ── Warm fuzzy cache: preload semua user dari mysqlhris ──────────────
        $this->buildFuzzyCache();

        foreach ($this->tables as $table) {
            $this->processTable($table);
        }

        $this->command->info(PHP_EOL . '✅ All tables processed.');
        $this->outputSummary();
    }

    // ══════════════════════════════════════════════════════════════════════
    //  FUZZY MATCH ENGINE
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Normalize nama: trim, normalize spasi, lowercase, hapus karakter non-esensial.
     */
    private function normalizeName(string $name): string
    {
        $name = trim($name);
        // Hapus BOM, karakter kontrol, newline, tab
        $name = preg_replace('/[\x00-\x1F\x7F\xFE\xFF]/u', '', $name);
        // Multiple space → single space
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        // Hapus titik di akhir (misal "Budi Santoso." → "Budi Santoso")
        $name = preg_replace('/\.+$/', '', $name);
        // Hapus spasi di dalam kurung/kutip
        $name = preg_replace('/\(\s+/', '(', $name);
        $name = preg_replace('/\s+\)/', ')', $name);
        // Hapus tanda petik yang tidak berpasangan
        // Lowercase
        $name = mb_strtolower($name);

        return $name;
    }

    /**
     * Stripped: hapus semua karakter non-alfanumerik (kecuali spasi) untuk
     * perbandingan lebih longgar. Contoh: "A. B." vs "AB" vs "A B".
     */
    private function stripName(string $name): string
    {
        $name = $this->normalizeName($name);
        // Hapus titik yang bukan bagian dari singkatan (diantara huruf)
        $name = preg_replace('/\.(?=\s|$)/', '', $name);
        // Hapus semua non-alfanumerik kecuali spasi
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Ambil inisial: huruf pertama tiap kata → cocokkan untuk singkatan.
     */
    private function initialName(string $name): string
    {
        $parts = explode(' ', $this->stripName($name));
        $initials = '';
        foreach ($parts as $p) {
            if ($p !== '') {
                $initials .= mb_substr($p, 0, 1);
            }
        }

        return $initials;
    }

    /**
     * Preload semua user dari mysqlhris, buat 3 varian key:
     *  1. normalized (normalizeName)
     *  2. stripped  (stripName)
     *  3. initials  (initialName)
     */
    private function buildFuzzyCache(): void
    {
        $this->command->info('Loading m_user from mysqlhris...');

        $allUsers = DB::connection('mysqlhris')
            ->table('m_user')
            ->select('id', 'full_name')
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '')
            ->get();

        $bar = $this->command->getOutput()->createProgressBar($allUsers->count());
        $bar->setFormat('  Loading users: %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        foreach ($allUsers as $user) {
            $raw = $user->full_name;

            // 1. Exact original → user_id
            $this->fuzzyCache[$raw] ??= $user->id;

            // 2. Normalized
            $n1 = $this->normalizeName($raw);
            $this->fuzzyCache[$n1] ??= $user->id;

            // 3. Stripped
            $n2 = $this->stripName($raw);
            $this->fuzzyCache[$n2] ??= $user->id;

            // 4. Initials
            $n3 = $this->initialName($raw);
            $this->fuzzyCache[$n3] ??= $user->id;

            $bar->advance();
        }

        $bar->finish();
        $this->command->info('');
        $this->command->info('  ✓ ' . $allUsers->count() . ' users cached');
    }

    /**
     * Cari user_id untuk sebuah nama, dari yang paling strict ke longgar.
     * Return null jika tidak ditemukan sama sekali.
     */
    private function fuzzyLookup(string $rawName): ?int
    {
        // 1. Exact match (original string)
        if (isset($this->fuzzyCache[$rawName])) {
            return $this->fuzzyCache[$rawName];
        }

        // 2. Normalized match (trim, lowercase, normalize spasi)
        $normalized = $this->normalizeName($rawName);
        if (isset($this->fuzzyCache[$normalized])) {
            // Cache mapping untuk row berikutnya
            $this->fuzzyCache[$rawName] = $this->fuzzyCache[$normalized];
            return $this->fuzzyCache[$rawName];
        }

        // 3. Stripped match (hapus non-alfanumerik)
        $stripped = $this->stripName($rawName);
        if (isset($this->fuzzyCache[$stripped])) {
            $this->fuzzyCache[$rawName] = $this->fuzzyCache[$stripped];
            return $this->fuzzyCache[$rawName];
        }

        // 4. Initials match (cocok dengan inisial)
        $initials = $this->initialName($rawName);
        if (strlen($initials) >= 2 && isset($this->fuzzyCache[$initials])) {
            $this->fuzzyCache[$rawName] = $this->fuzzyCache[$initials];
            return $this->fuzzyCache[$rawName];
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════════════
    //  PROCESSOR
    // ══════════════════════════════════════════════════════════════════════

    protected function processTable(string $table): void
    {
        $query = DB::table($table)
            ->where(function ($q) {
                $q->whereNotNull('created_by')
                  ->where('created_by', '!=', '')
                  ->whereRaw("TRIM(created_by) != ''");
            });

        if (Schema::hasColumn($table, 'created_at')) {
            $query->whereYear('created_at', '>=', 2026);
        }

        $totalPending = $query->count();

        if ($totalPending === 0) {
            $this->command->warn("  ⏭  {$table}: nothing to backfill");

            return;
        }

        $progress = $this->command->getOutput()->createProgressBar($totalPending);
        $progress->setFormat("  %message%: %current%/%max% [%bar%] %percent:3s%%");
        $progress->setMessage($table);
        $progress->start();

        $updated = 0;
        $skipped = 0;
        /** @var array<string, true> Nama yang pernah gagal match (hindari spam log) */
        $loggedMissing = [];

        $chunkQuery = DB::table($table)
            ->where(function ($q) {
                $q->whereNotNull('created_by')
                  ->where('created_by', '!=', '')
                  ->whereRaw("TRIM(created_by) != ''");
            });

        if (Schema::hasColumn($table, 'created_at')) {
            $chunkQuery->whereYear('created_at', '>=', 2026);
        }

        $chunkQuery->orderBy('id')
            ->chunkById($this->chunkSize, function ($rows) use ($table, $progress, &$updated, &$skipped, &$loggedMissing) {
                foreach ($rows as $row) {
                    $name = $row->created_by;
                    $userId = $this->fuzzyLookup($name);

                    if ($userId !== null) {
                        $existing = DB::table($table)
                            ->where('id', $row->id)
                            ->value('created_by_user_id');

                        if ((int) $existing !== $userId) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->update(['created_by_user_id' => $userId]);
                            $updated++;
                        } else {
                            $skippedSame = ($skippedSame ?? 0) + 1;
                        }
                    } else {
                        if (! isset($loggedMissing[$name])) {
                            $loggedMissing[$name] = true;
                            $this->command->warn("  ⚠  [{$table}] unmatched: \"{$name}\"");
                        }
                        $skipped++;
                    }

                    $progress->advance();
                }
            });

        $progress->finish();
        $this->command->info('');
        $this->command->line("     ✓ {$updated} updated, " . ($skippedSame ?? 0) . " already correct, {$skipped} skipped (unmatched)");
    }

    protected function outputSummary(): void
    {
        $this->command->info(PHP_EOL . '=== Unmatched Names (distinct across ALL tables) ===');

        $allUnmatched = [];

        foreach ($this->tables as $table) {
            $names = DB::table($table)
                ->whereNotNull('created_by')
                ->where('created_by', '!=', '')
                ->whereRaw("TRIM(created_by) != ''")
                ->whereNull('created_by_user_id')
                ->when(Schema::hasColumn($table, 'created_at'), fn ($q) => $q->whereYear('created_at', '>=', 2026))
                ->distinct()
                ->pluck('created_by')
                ->toArray();

            foreach ($names as $name) {
                $allUnmatched[$name] = ($allUnmatched[$name] ?? 0) + 1;
            }
        }

        if (! empty($allUnmatched)) {
            arsort($allUnmatched);
            foreach ($allUnmatched as $name => $count) {
                $this->command->line("  [{$count}x] \"{$name}\"");
            }
            $this->command->info('Total distinct unmatched names: ' . count($allUnmatched));
        } else {
            $this->command->info('✅ All names matched!');
        }
    }
}
