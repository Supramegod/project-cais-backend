<?php

namespace Tests\Unit\Services\Pks\Fulfillment;

use App\Models\Branch;
use App\Models\Pks;
use App\Models\Site;
use App\Services\Pks\Fulfillment\BranchResolutionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for BranchResolutionService.
 *
 * @group pks-fulfillment
 */
class BranchResolutionServiceTest extends TestCase
{
    private BranchResolutionService $service;
    private int $pksId;
    private int $leadsId;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = $this->tempDbPath = storage_path('framework/testing-' . Str::random(8) . '.sqlite');
        touch($databasePath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.connections.mysqlhris', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));

        DB::purge('sqlite');
        DB::purge('mysqlhris');
        DB::purge('mysql');
        DB::reconnect('sqlite');
        DB::reconnect('mysqlhris');
        DB::reconnect('mysql');

        $this->rebuildSchema();

        // Insert branches on mysqlhris
        DB::connection('mysqlhris')->table('m_branch')->insert([
            ['id' => 1, 'name' => 'Jakarta Pusat', 'city_id' => 1, 'is_active' => 1],
            ['id' => 2, 'name' => 'Bandung', 'city_id' => 2, 'is_active' => 1],
            ['id' => 3, 'name' => 'Surabaya', 'city_id' => 3, 'is_active' => 1],
        ]);

        $this->service = new BranchResolutionService();

        $this->seedBaseData();
    }

    private function seedBaseData(): void
    {
        // sl_leads
        $this->leadsId = DB::table('sl_leads')->insertGetId([
            'nomor' => 'LEAD-BRANCH-001',
            'nama_perusahaan' => 'PT Branch Resolution',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_pks
        $this->pksId = DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId,
            'quotation_id' => null,
            'nomor' => 'PKS/BRANCH/001',
            'status_pks_id' => 7,
            'layanan_id' => 1,
            'kontrak_awal' => '2026-01-01',
            'kontrak_akhir' => '2027-12-31',
            'is_aktif' => 1,
            'tipe_pks' => 'baru',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedSites(array $sites): void
    {
        foreach ($sites as $site) {
            DB::table('sl_site')->insert(array_merge([
                'pks_id' => $this->pksId,
                'leads_id' => $this->leadsId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $site));
        }
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_site',
            'sl_pks',
            'sl_leads',
            'm_branch',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_induk_id')->nullable();
            $table->string('tipe_pks')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('kontrak_awal')->nullable();
            $table->date('kontrak_akhir')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->boolean('is_aktif')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('pks_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->string('kota')->nullable();
            $table->boolean('is_visit_anchor')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    // ─── TEST 1: resolveSiteAnchor flag manual ───────────────────────
    /** @test */
    public function test_resolve_site_anchor_returns_manual_flagged_site(): void
    {
        $this->seedSites([
            ['kota_id' => 1, 'nama_site' => 'Site A', 'is_visit_anchor' => 0],
            ['kota_id' => 2, 'nama_site' => 'Site B - Anchor', 'is_visit_anchor' => 1],
            ['kota_id' => 3, 'nama_site' => 'Site C', 'is_visit_anchor' => 0],
        ]);

        $pks = Pks::find($this->pksId);
        $anchor = $this->service->resolveSiteAnchor($pks);

        $this->assertNotNull($anchor);
        $this->assertEquals('Site B - Anchor', $anchor->nama_site);
        $this->assertEquals(1, $anchor->is_visit_anchor);
    }

    // ─── TEST 2: resolveSiteAnchor auto-derive match 1 ───────────────
    /** @test */
    public function test_resolve_site_anchor_auto_derives_exact_one_match(): void
    {
        // Create 3 sites: only kota_id=1 matches a branch
        $this->seedSites([
            ['kota_id' => 1, 'nama_site' => 'Site Jakarta', 'is_visit_anchor' => 0],
            ['kota_id' => 99, 'nama_site' => 'Site Outer', 'is_visit_anchor' => 0],
            ['kota_id' => 100, 'nama_site' => 'Site Remote', 'is_visit_anchor' => 0],
        ]);

        $pks = Pks::find($this->pksId);
        $anchor = $this->service->resolveSiteAnchor($pks);

        $this->assertNotNull($anchor);
        $this->assertEquals('Site Jakarta', $anchor->nama_site);
    }

    // ─── TEST 3: resolveSiteAnchor auto-derive match >1 ──────────────
    /** @test */
    public function test_resolve_site_anchor_with_multiple_matches_returns_first_or_flagged(): void
    {
        // Create 3 sites: kota_id 1 and 2 both match branches
        $this->seedSites([
            ['kota_id' => 1, 'nama_site' => 'Site JKT A', 'is_visit_anchor' => 0],
            ['kota_id' => 2, 'nama_site' => 'Site BDG', 'is_visit_anchor' => 0],
            ['kota_id' => 1, 'nama_site' => 'Site JKT B', 'is_visit_anchor' => 0],
        ]);

        $pks = Pks::find($this->pksId);
        $anchor = $this->service->resolveSiteAnchor($pks);

        $this->assertNotNull($anchor);
        $this->assertContains($anchor->nama_site, ['Site JKT A', 'Site BDG', 'Site JKT B']);
    }

    /** @test */
    public function test_resolve_site_anchor_multiple_matches_prefers_flagged(): void
    {
        // Multiple matches, one is flagged
        $this->seedSites([
            ['kota_id' => 1, 'nama_site' => 'Site JKT A', 'is_visit_anchor' => 0],
            ['kota_id' => 2, 'nama_site' => 'Site BDG Flagged', 'is_visit_anchor' => 1],
            ['kota_id' => 1, 'nama_site' => 'Site JKT B', 'is_visit_anchor' => 0],
        ]);

        $pks = Pks::find($this->pksId);
        $anchor = $this->service->resolveSiteAnchor($pks);

        $this->assertEquals('Site BDG Flagged', $anchor->nama_site);
    }

    // ─── TEST 4: resolveSiteAnchor fallback ──────────────────────────
    /** @test */
    public function test_resolve_site_anchor_falls_back_to_first_site_when_no_match(): void
    {
        // No sites match any branch
        $this->seedSites([
            ['kota_id' => 99, 'nama_site' => 'Site Alpha', 'is_visit_anchor' => 0],
            ['kota_id' => 100, 'nama_site' => 'Site Beta', 'is_visit_anchor' => 0],
        ]);

        $pks = Pks::find($this->pksId);
        $anchor = $this->service->resolveSiteAnchor($pks);

        $this->assertNotNull($anchor);
        $this->assertEquals('Site Alpha', $anchor->nama_site);
    }

    /** @test */
    public function test_resolve_site_anchor_no_match_but_has_flagged_returns_flagged(): void
    {
        // No branch match, but one site is flagged
        $this->seedSites([
            ['kota_id' => 99, 'nama_site' => 'Site Alpha', 'is_visit_anchor' => 0],
            ['kota_id' => 100, 'nama_site' => 'Site Beta Flagged', 'is_visit_anchor' => 1],
        ]);

        $pks = Pks::find($this->pksId);
        $anchor = $this->service->resolveSiteAnchor($pks);

        $this->assertEquals('Site Beta Flagged', $anchor->nama_site);
    }

    /** @test */
    public function test_resolve_site_anchor_returns_null_when_no_sites(): void
    {
        $pks = Pks::find($this->pksId);
        $anchor = $this->service->resolveSiteAnchor($pks);

        $this->assertNull($anchor);
    }

    protected ?string $tempDbPath = null;

    protected function tearDown(): void
    {
        foreach (['sqlite', 'mysql', 'mysqlhris'] as $connection) {
            try {
                DB::purge($connection);
            } catch (\Throwable $e) {
                // koneksi mungkin tidak terdaftar — abaikan
            }
        }

        if ($this->tempDbPath && file_exists($this->tempDbPath)) {
            @unlink($this->tempDbPath);
        }

        parent::tearDown();
    }
}
