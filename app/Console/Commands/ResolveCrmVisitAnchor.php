<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tentukan visit anchor dari staging sheet CRM (stg_crm_site_share).
 *
 * Tiga tahap, semuanya bisa diulang tanpa import CSV lagi:
 *   1. match  — cocokkan tiap baris sheet (satu baris = satu site) ke data CAIS.
 *   2. resolve— kelompokkan per perusahaan, ambil baris INDUK sebagai anchor.
 *   3. apply  — tulis sl_site.is_visit_anchor (hanya kalau --apply diberikan).
 *
 * Aturan match mengikuti CRM: NO PKS dulu; kalau nomornya bukan terbitan CAIS
 * (banyak PKS diterbitkan customer, jadi formatnya beda) baru jatuh ke nama.
 * NAMA PERUSAHAAN di sheet praktis unik per baris karena CAIS lama sempat
 * memecah satu perusahaan jadi beberapa leads, jadi nama dicocokkan ke
 * sl_site.nama_site lebih dulu, baru ke sl_leads.nama_perusahaan.
 *
 * INDUK diisi TRUE sekali per perusahaan, bukan per PKS — satu perusahaan bisa
 * punya beberapa PKS dengan kebutuhan berbeda dan tetap satu anchor.
 */
class ResolveCrmVisitAnchor extends Command
{
    protected $signature = 'crm:resolve-visit-anchor
                            {--batch= : Batch staging yang diproses (default: batch terbaru)}
                            {--rematch : Hitung ulang kolom match walau sudah terisi}
                            {--apply : Tulis hasilnya ke sl_site.is_visit_anchor}
                            {--limit=0 : Batasi jumlah baris staging yang diproses (0 = semua)}';

    protected $description = 'Cocokkan sheet CRM ke data CAIS lalu tentukan visit anchor per perusahaan';

    /** Nilai kolom INDUK yang dianggap benar. */
    private const INDUK_TRUE = ['TRUE', '1', 'YES', 'Y', 'IYA', 'INDUK'];

    public function handle(): int
    {
        if (! $this->tablesReady()) {
            return self::FAILURE;
        }

        $batch = $this->option('batch') ?: DB::table('stg_crm_site_share')
            ->orderByDesc('id')
            ->value('import_batch');

        if (! $batch) {
            $this->error('Staging kosong. Jalankan crm:import-site-share dulu.');

            return self::FAILURE;
        }

        $this->info("Batch: {$batch}");

        $matched = $this->matchRows($batch);
        $this->line("Match  : {$matched['matched']} matched, {$matched['ambiguous']} ambiguous, {$matched['not_found']} not_found, {$matched['terminated']} terminated (dilewati)");

        $groups = $this->resolveGroups($batch);
        $this->line("Resolve: {$groups['resolved']} resolved, {$groups['inherited']} inherited, {$groups['no_induk']} no_induk, {$groups['multi_induk']} multi_induk, {$groups['site_not_found']} site_not_found");

        if ($this->option('apply')) {
            $applied = $this->applyAnchors($batch);
            $this->line("Apply  : {$applied['flagged']} site di-set anchor, {$applied['cleared']} flag lama dibersihkan");
        } else {
            $this->comment('Dry-run — belum ada perubahan di sl_site. Tambahkan --apply untuk menulis.');
        }

        $this->newLine();
        $this->line('Cek hasil:');
        $this->line("  select resolve_status, count(*) from crm_visit_anchor_map where import_batch = '{$batch}' group by 1;");
        $this->line("  select * from crm_visit_anchor_map where import_batch = '{$batch}' and resolve_status <> 'resolved' limit 20;");

        return self::SUCCESS;
    }

    private function tablesReady(): bool
    {
        foreach (['stg_crm_site_share', 'crm_visit_anchor_map'] as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $this->error("Tabel {$table} belum ada. Jalankan php artisan migrate dulu.");

                return false;
            }
        }

        return true;
    }

    /**
     * Tahap 1 — isi matched_pks_id / matched_site_id / matched_leads_id.
     *
     * @return array{matched: int, ambiguous: int, not_found: int, terminated: int}
     */
    private function matchRows(string $batch): array
    {
        $pksByNomor = $this->indexPksByNomor();
        $sitesByName = $this->indexSitesByName();
        $leadsByName = $this->indexLeadsByName();

        $tally = ['matched' => 0, 'ambiguous' => 0, 'not_found' => 0, 'terminated' => 0];
        $limit = (int) $this->option('limit');

        $query = DB::table('stg_crm_site_share')
            ->where('import_batch', $batch)
            ->when(! $this->option('rematch'), fn ($q) => $q->whereNull('match_status'))
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $bar = $this->output->createProgressBar();
        $bar->setRedrawFrequency(100);
        $bar->start();

        $handle = function ($rows) use (&$tally, $pksByNomor, $sitesByName, $leadsByName, $bar) {
            foreach ($rows as $row) {
                $result = $this->matchRow($row, $pksByNomor, $sitesByName, $leadsByName);

                DB::table('stg_crm_site_share')->where('id', $row->id)->update($result);

                $tally[$result['match_status']]++;
                $bar->advance();
            }
        };

        if ($limit > 0) {
            $handle($query->get());
        } else {
            $query->chunkById(500, $handle);
        }

        $bar->finish();
        $this->newLine();

        return $tally;
    }

    /**
     * @param  array<string, array<int, object>>  $pksByNomor
     * @param  array<string, array<int, object>>  $sitesByName
     * @param  array<string, array<int, object>>  $leadsByName
     * @return array<string, mixed>
     */
    private function matchRow(object $row, array $pksByNomor, array $sitesByName, array $leadsByName): array
    {
        $blank = [
            'matched_pks_id' => null,
            'matched_site_id' => null,
            'matched_leads_id' => null,
            'match_by' => null,
            'match_status' => 'not_found',
            'match_note' => null,
            'updated_at' => now(),
        ];

        // Site yang sudah terminate tidak ikut dicocokkan sama sekali: kalau
        // ikut, baris terminate yang kebetulan INDUK=TRUE bisa merebut anchor
        // dari site yang masih jalan.
        if ($this->isTerminated($row->tanggal_terminate_site ?? null)) {
            return array_merge($blank, [
                'match_status' => 'terminated',
                'match_note' => 'Tanggal Terminate Site terisi ('.trim((string) $row->tanggal_terminate_site).'), site sudah putus.',
            ]);
        }

        $nomor = $this->normalizeNomor($row->no_pks);
        $nama = $this->normalizeName($row->nama_perusahaan);

        // 1. NO PKS — patokan utama.
        if ($nomor !== '' && isset($pksByNomor[$nomor])) {
            $candidates = $pksByNomor[$nomor];

            if (count($candidates) > 1) {
                return array_merge($blank, [
                    'match_by' => 'no_pks',
                    'match_status' => 'ambiguous',
                    'match_note' => 'NO PKS cocok ke '.count($candidates).' PKS di CAIS.',
                ]);
            }

            $pks = $candidates[0];
            $site = $this->pickSite($sitesByName, $nama, (int) $pks->id);

            return array_merge($blank, [
                'matched_pks_id' => $pks->id,
                'matched_leads_id' => $pks->leads_id,
                'matched_site_id' => $site?->id,
                'match_by' => 'no_pks',
                'match_status' => $site ? 'matched' : 'ambiguous',
                'match_note' => $site
                    ? null
                    : 'PKS cocok lewat NO PKS tapi nama site tidak ketemu di PKS tersebut.',
            ]);
        }

        if ($nama === '') {
            return array_merge($blank, ['match_note' => 'NO PKS tidak cocok dan NAMA PERUSAHAAN kosong.']);
        }

        // 2. Nama dicocokkan ke nama site.
        if (isset($sitesByName[$nama])) {
            $candidates = $sitesByName[$nama];

            if (count($candidates) > 1) {
                return array_merge($blank, [
                    'match_by' => 'nama_site',
                    'match_status' => 'ambiguous',
                    'match_note' => 'Nama cocok ke '.count($candidates).' site di CAIS.',
                ]);
            }

            $site = $candidates[0];

            return array_merge($blank, [
                'matched_site_id' => $site->id,
                'matched_pks_id' => $site->pks_id,
                'matched_leads_id' => $site->leads_id,
                'match_by' => 'nama_site',
                'match_status' => 'matched',
            ]);
        }

        // 3. Nama dicocokkan ke nama perusahaan di leads (CAIS lama menjadikan
        //    beberapa nama site sebagai leads terpisah).
        if (isset($leadsByName[$nama])) {
            $candidates = $leadsByName[$nama];

            if (count($candidates) > 1) {
                return array_merge($blank, [
                    'match_by' => 'nama_perusahaan',
                    'match_status' => 'ambiguous',
                    'match_note' => 'Nama cocok ke '.count($candidates).' leads di CAIS.',
                ]);
            }

            $leads = $candidates[0];

            return array_merge($blank, [
                'matched_leads_id' => $leads->id,
                'match_by' => 'nama_perusahaan',
                'match_status' => 'ambiguous',
                'match_note' => 'Ketemu leads tapi belum ketemu site-nya.',
            ]);
        }

        return array_merge($blank, ['match_note' => 'Tidak ketemu lewat NO PKS maupun nama.']);
    }

    /**
     * Ambil site dengan nama yang cocok, dibatasi ke satu PKS kalau diketahui.
     *
     * @param  array<string, array<int, object>>  $sitesByName
     */
    private function pickSite(array $sitesByName, string $nama, ?int $pksId): ?object
    {
        if ($nama === '' || ! isset($sitesByName[$nama])) {
            return null;
        }

        $candidates = $sitesByName[$nama];

        if ($pksId !== null) {
            $scoped = array_values(array_filter(
                $candidates,
                fn ($site) => (int) $site->pks_id === $pksId
            ));

            if (count($scoped) === 1) {
                return $scoped[0];
            }

            return null;
        }

        return count($candidates) === 1 ? $candidates[0] : null;
    }

    /**
     * Tahap 2 — kelompokkan per perusahaan lalu pilih barisnya yang INDUK.
     *
     * @return array{resolved: int, no_induk: int, multi_induk: int, site_not_found: int}
     */
    private function resolveGroups(string $batch): array
    {
        $rows = DB::table('stg_crm_site_share')
            ->where('import_batch', $batch)
            // Site terminate tidak dihitung: bukan calon anchor, dan tidak
            // membuat grup jadi "ada isinya" kalau semua sitenya sudah putus.
            ->where(function ($q) {
                $q->whereNull('tanggal_terminate_site')
                    ->orWhereRaw("trim(tanggal_terminate_site) = ''");
            })
            ->select(
                'row_number', 'kode_site', 'nama_perusahaan', 'no_pks', 'service', 'induk',
                'matched_pks_id', 'matched_site_id', 'matched_leads_id'
            )
            ->orderBy('row_number')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada baris aktif di batch ini.');
        }

        /** @var array<string, array{by: string, rows: array<int, object>}> $groups */
        $groups = [];

        foreach ($rows as $row) {
            [$key, $by] = $this->groupKey($row);
            $groups[$key] ??= ['by' => $by, 'rows' => []];
            $groups[$key]['rows'][] = $row;
        }

        $tally = ['resolved' => 0, 'inherited' => 0, 'no_induk' => 0, 'multi_induk' => 0, 'site_not_found' => 0];
        $now = now();

        DB::table('crm_visit_anchor_map')->where('import_batch', $batch)->delete();

        foreach (array_chunk($groups, 200, true) as $chunk) {
            $payload = [];

            foreach ($chunk as $key => $group) {
                $induks = array_values(array_filter(
                    $group['rows'],
                    fn ($row) => $this->isInduk($row->induk)
                ));

                $status = 'resolved';
                $note = null;
                $pick = null;

                if ($induks === []) {
                    $status = 'no_induk';
                    $note = 'Tidak ada baris INDUK di grup ini.';
                } elseif (count($induks) === 1) {
                    $pick = $induks[0];
                } else {
                    $pick = $this->pickInduk($induks);
                    $status = $pick ? 'resolved' : 'multi_induk';
                    $note = $pick
                        ? count($induks).' baris INDUK, dipilih nama tanpa suffix cabang.'
                        : count($induks).' baris INDUK dan tidak ada yang jelas induknya.';
                    $pick ??= $induks[0];
                }

                if ($status === 'resolved' && ! $pick?->matched_site_id) {
                    $status = 'site_not_found';
                    $note = 'Baris INDUK ketemu tapi site-nya belum ke-match ke CAIS.';
                }

                $tally[$status]++;

                $payload[] = [
                    'import_batch' => $batch,
                    'group_key' => mb_substr((string) $key, 0, 191),
                    'group_by' => $group['by'],
                    'induk_row_number' => $pick?->row_number,
                    'induk_kode_site' => $pick?->kode_site,
                    'induk_nama_perusahaan' => $pick?->nama_perusahaan,
                    'induk_no_pks' => $pick?->no_pks,
                    'induk_service' => $pick?->service,
                    'matched_leads_id' => $pick?->matched_leads_id,
                    'matched_pks_id' => $pick?->matched_pks_id,
                    'matched_site_id' => $status === 'resolved' ? $pick?->matched_site_id : null,
                    'row_count' => count($group['rows']),
                    'induk_count' => count($induks),
                    'resolve_status' => $status,
                    'resolve_note' => $note,
                    'company_core' => mb_substr($this->companyCore($pick ?? $group['rows'][0]), 0, 191),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('crm_visit_anchor_map')->insert($payload);
        }

        $inherited = $this->inheritAnchors($batch);
        $tally['inherited'] = $inherited;
        $tally['no_induk'] -= $inherited;

        return $tally;
    }

    /**
     * Satu perusahaan bisa punya beberapa PKS dengan kebutuhan berbeda, dan
     * sheet hanya menandai INDUK sekali — di PKS yang lain semua barisnya FALSE.
     * Grup tanpa INDUK karena itu mewarisi anchor dari grup lain dengan nama
     * inti perusahaan yang sama, dan hanya kalau sumbernya cuma satu.
     */
    private function inheritAnchors(string $batch): int
    {
        $sources = DB::table('crm_visit_anchor_map')
            ->where('import_batch', $batch)
            ->where('resolve_status', 'resolved')
            ->whereNotNull('matched_site_id')
            ->where('company_core', '<>', '')
            ->select('company_core', 'matched_site_id', 'matched_pks_id', 'matched_leads_id', 'induk_kode_site', 'group_key')
            ->get()
            ->groupBy('company_core');

        $count = 0;

        DB::table('crm_visit_anchor_map')
            ->where('import_batch', $batch)
            ->where('resolve_status', 'no_induk')
            ->where('company_core', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($sources, &$count) {
                foreach ($rows as $row) {
                    $candidates = ($sources[$row->company_core] ?? collect())
                        ->unique('matched_site_id')
                        ->values();

                    if ($candidates->count() !== 1) {
                        continue;
                    }

                    $source = $candidates->first();

                    DB::table('crm_visit_anchor_map')->where('id', $row->id)->update([
                        'matched_site_id' => $source->matched_site_id,
                        'matched_pks_id' => $source->matched_pks_id,
                        'matched_leads_id' => $row->matched_leads_id ?: $source->matched_leads_id,
                        'resolve_status' => 'inherited',
                        'resolve_note' => 'Tidak ada INDUK di grup ini; anchor diwarisi dari grup '
                            .$source->group_key.' (kode site '.$source->induk_kode_site.') karena satu perusahaan.',
                        'updated_at' => now(),
                    ]);

                    $count++;
                }
            });

        return $count;
    }

    /**
     * Nama inti perusahaan: buang keterangan cabang / kebutuhan di belakang
     * nama, mis. "SUMBER CIPTA MULTINIAGA (GONUSA) - Security" dan
     * "SUMBER CIPTA MULTINIAGA (GONUSA) SURABAYA" jadi satu nama yang sama.
     */
    private function companyCore(object $row): string
    {
        $nama = (string) $row->nama_perusahaan;

        foreach (['(', ' - ', ' -'] as $cut) {
            $pos = strpos($nama, $cut);

            if ($pos !== false && $pos > 0) {
                $nama = substr($nama, 0, $pos);
            }
        }

        return $this->normalizeName($nama);
    }

    /**
     * Kunci perusahaan. leads_id paling dipercaya; kalau belum ke-match baru
     * pakai nomor PKS, dan terakhir nama perusahaan mentah.
     *
     * @return array{0: string, 1: string}
     */
    private function groupKey(object $row): array
    {
        if ($row->matched_leads_id) {
            return ['leads:'.$row->matched_leads_id, 'leads_id'];
        }

        $nomor = $this->normalizeNomor($row->no_pks);

        if ($nomor !== '') {
            return ['pks:'.$nomor, 'no_pks'];
        }

        return ['nama:'.$this->normalizeName($row->nama_perusahaan), 'nama_perusahaan'];
    }

    /**
     * Beberapa grup punya lebih dari satu INDUK=TRUE karena sheet belum
     * dirapikan (mis. baris kantor pusat dan baris cabang "- OSO" dua-duanya
     * TRUE). Yang dipakai adalah nama tanpa suffix cabang, dan itu hanya sah
     * kalau cuma ada satu kandidat seperti itu.
     *
     * @param  array<int, object>  $induks
     */
    private function pickInduk(array $induks): ?object
    {
        $plain = array_values(array_filter($induks, function ($row) {
            $nama = (string) $row->nama_perusahaan;

            return ! str_contains($nama, '(') && ! str_contains($nama, ' - ');
        }));

        if (count($plain) === 1) {
            return $plain[0];
        }

        $withSite = array_values(array_filter(
            $plain !== [] ? $plain : $induks,
            fn ($row) => (bool) $row->matched_site_id
        ));

        return count($withSite) === 1 ? $withSite[0] : null;
    }

    /**
     * Tahap 3 — tulis flag ke sl_site.
     *
     * Flag anchor dibaca per PKS oleh BranchResolutionService, jadi sisa flag
     * lama di PKS yang sama dibersihkan supaya tidak ada dua anchor.
     *
     * @return array{flagged: int, cleared: int}
     */
    private function applyAnchors(string $batch): array
    {
        // Grup yang mewarisi anchor menunjuk ke site yang sama dengan grup
        // sumbernya, jadi yang dihitung site unik, bukan jumlah update.
        $flaggedSites = [];
        $cleared = 0;
        $now = now();

        DB::table('crm_visit_anchor_map')
            ->where('import_batch', $batch)
            ->whereIn('resolve_status', ['resolved', 'inherited'])
            ->whereNotNull('matched_site_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$flaggedSites, &$cleared, $now) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$flaggedSites, &$cleared, $now) {
                        $site = DB::table('sl_site')
                            ->where('id', $row->matched_site_id)
                            ->select('id', 'pks_id')
                            ->first();

                        if (! $site) {
                            return;
                        }

                        if ($site->pks_id) {
                            $cleared += DB::table('sl_site')
                                ->where('pks_id', $site->pks_id)
                                ->where('id', '<>', $site->id)
                                ->where('is_visit_anchor', 1)
                                ->update(['is_visit_anchor' => 0]);
                        }

                        DB::table('sl_site')
                            ->where('id', $site->id)
                            ->update(['is_visit_anchor' => 1]);

                        $flaggedSites[(int) $site->id] = true;

                        DB::table('crm_visit_anchor_map')
                            ->where('id', $row->id)
                            ->update(['applied_at' => $now, 'updated_at' => $now]);
                    });
                }
            });

        return ['flagged' => count($flaggedSites), 'cleared' => $cleared];
    }

    /**
     * @return array<string, array<int, object>>
     */
    private function indexPksByNomor(): array
    {
        $index = [];

        DB::table('sl_pks')
            ->whereNull('deleted_at')
            ->select('id', 'leads_id', 'nomor')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$index) {
                foreach ($rows as $row) {
                    $key = $this->normalizeNomor($row->nomor);

                    if ($key !== '') {
                        $index[$key][] = $row;
                    }
                }
            });

        return $index;
    }

    /**
     * @return array<string, array<int, object>>
     */
    private function indexSitesByName(): array
    {
        $index = [];

        DB::table('sl_site')
            ->whereNull('deleted_at')
            ->select('id', 'pks_id', 'leads_id', 'nama_site')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$index) {
                foreach ($rows as $row) {
                    $key = $this->normalizeName($row->nama_site);

                    if ($key !== '') {
                        $index[$key][] = $row;
                    }
                }
            });

        return $index;
    }

    /**
     * @return array<string, array<int, object>>
     */
    private function indexLeadsByName(): array
    {
        $index = [];

        DB::table('sl_leads')
            ->whereNull('deleted_at')
            ->select('id', 'nama_perusahaan')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$index) {
                foreach ($rows as $row) {
                    $key = $this->normalizeName($row->nama_perusahaan);

                    if ($key !== '') {
                        $index[$key][] = $row;
                    }
                }
            });

        return $index;
    }

    /**
     * Nomor PKS di sheet ada yang kena typo slash ganda dan spasi liar, jadi
     * dirapikan dulu sebelum dibandingkan.
     */
    private function normalizeNomor(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '' || in_array($value, ['-', '#N/A', 'N/A'], true)) {
            return '';
        }

        $value = preg_replace('#\s*/\s*#', '/', $value) ?? $value;
        $value = preg_replace('#/{2,}#', '/', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * Nama dibandingkan tanpa tanda baca dan tanpa bentuk badan usaha, karena
     * sheet dan CAIS tidak konsisten menulis "PT", "PT.", atau tanpa keduanya.
     */
    private function normalizeName(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '' || in_array($value, ['-', '#N/A', 'N/A'], true)) {
            return '';
        }

        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        $value = preg_replace('/^(PT|CV|PD|UD|KOPERASI|YAYASAN)\s+/', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * Kolom Tanggal Terminate Site diisi bebas oleh CRM, jadi nilai sampah
     * seperti "-" dan "#N/A" tetap dianggap kosong.
     */
    private function isTerminated(?string $value): bool
    {
        $value = strtoupper(trim((string) $value));

        return $value !== '' && ! in_array($value, ['-', '#N/A', 'N/A', 'NA', 'BELUM', 'AKTIF'], true);
    }

    private function isInduk(?string $value): bool
    {
        return in_array(strtoupper(trim((string) $value)), self::INDUK_TRUE, true);
    }
}
