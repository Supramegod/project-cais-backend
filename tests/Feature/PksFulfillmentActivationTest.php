<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use App\Services\Pks\VisitSchedulingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for PKS Activation Hook — snapshot targets & generate schedules.
 *
 * @group pks-fulfillment
 */
class PksFulfillmentActivationTest extends TestCase
{
    private int $pksId;
    private int $leadsId;
    private int $quotationId;
    private int $siteId;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = storage_path('framework/testing-' . Str::random(8) . '.sqlite');
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

        // Insert test user
        DB::connection('mysqlhris')->table('m_user')->insert([
            'id' => 1,
            'username' => 'admin',
            'password' => bcrypt('secret'),
            'full_name' => 'Admin User',
            'email' => 'admin@example.com',
            'cais_role_id' => 8,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert test branch on mysqlhris (needed by BranchResolutionService)
        DB::connection('mysqlhris')->table('m_branch')->insert([
            'id' => 1,
            'name' => 'Jakarta Branch',
            'city_id' => 1,
            'is_active' => 1,
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $this->seedBaseData();
    }

    private function seedBaseData(): void
    {
        // m_kebutuhan
        DB::table('m_kebutuhan')->insert([
            ['id' => 1, 'nama' => 'Security', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // m_kategori_sesuai_hc
        DB::table('m_kategori_sesuai_hc')->insert([
            ['id' => 1, 'nama' => 'Silver', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'nama' => 'Diamond', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // sl_leads
        $this->leadsId = DB::table('sl_leads')->insertGetId([
            'nomor' => 'LEAD-ACT-001',
            'nama_perusahaan' => 'PT Activation Test',
            'kebutuhan_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation
        $this->quotationId = DB::table('sl_quotation')->insertGetId([
            'leads_id' => $this->leadsId,
            'nomor' => 'Q-ACT-001',
            'is_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation_detail (total HC = 200 -> Diamond tier)
        DB::table('sl_quotation_detail')->insert([
            [
                'quotation_id' => $this->quotationId,
                'quotation_site_id' => null,
                'nama_site' => 'Site A',
                'position_id' => 1,
                'jabatan_kebutuhan' => 'Security',
                'jumlah_hc' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // m_pks_visit_target (master matrix)
        DB::table('m_pks_visit_target')->insert([
            [
                'kebutuhan_id' => 1,
                'hc_min' => 100,
                'hc_max' => 500,
                'kategori_sesuai_hc_id' => 4,
                'target_visit_per_tahun' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // sl_pks
        $this->pksId = DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'nomor' => 'PKS/ACT/001',
            'status_pks_id' => 7,
            'layanan_id' => 1,
            'kontrak_awal' => '2026-01-01',
            'kontrak_akhir' => '2027-12-31', // 2 years
            'is_aktif' => 1,
            'tipe_pks' => 'baru',
            'kategori_sesuai_hc_id' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_site
        $this->siteId = DB::table('sl_site')->insertGetId([
            'pks_id' => $this->pksId,
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'kota_id' => 1,
            'kota' => 'Jakarta',
            'nama_site' => 'Site Activation 1',
            'is_visit_anchor' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── Schema ────────────────────────────────────────────────────────

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_pks_visit_schedule',
            'sl_pks_visit_target',
            'm_pks_visit_target',
            'sl_quotation_detail',
            'sl_quotation',
            'sl_site',
            'sl_pks',
            'sl_leads',
            'm_kategori_sesuai_hc',
            'm_kebutuhan',
            'm_branch',
            'm_user',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('m_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('cais_role_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_kategori_sesuai_hc', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->boolean('is_aktif')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->unsignedInteger('position_id')->nullable();
            $table->string('jabatan_kebutuhan')->nullable();
            $table->unsignedInteger('jumlah_hc')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_induk_id')->nullable();
            $table->string('tipe_pks')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('kontrak_awal')->nullable();
            $table->date('kontrak_akhir')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->boolean('is_aktif')->nullable();
            $table->unsignedInteger('kategori_sesuai_hc_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
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

        Schema::create('m_pks_visit_target', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kebutuhan_id');
            $table->unsignedInteger('hc_min');
            $table->unsignedInteger('hc_max');
            $table->unsignedInteger('kategori_sesuai_hc_id');
            $table->unsignedInteger('target_visit_per_tahun');
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks_visit_target', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->string('role'); // operasional, crm
            $table->unsignedInteger('kategori_sesuai_hc_id');
            $table->unsignedInteger('target_total')->default(0);
            $table->unsignedInteger('target_terpakai')->default(0);
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sl_pks_visit_schedule', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('leads_id');
            $table->string('role'); // operasional, crm
            $table->unsignedBigInteger('pic_user_id');
            $table->date('tgl_jadwal');
            $table->date('tgl_jadwal_asli')->nullable();
            $table->text('alasan_reschedule')->nullable();
            $table->unsignedBigInteger('direschedule_oleh')->nullable();
            $table->string('status')->default('scheduled');
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    // ─── TEST 1: PKS activation trigger snapshot ─────────────────────
    /** @test */
    public function test_activation_snapshots_visit_targets(): void
    {
        $pks = \App\Models\Pks::find($this->pksId);

        $service = app(VisitSchedulingService::class);
        $service->snapshotTargets($pks);

        $this->assertDatabaseHas('sl_pks_visit_target', [
            'pks_id' => $this->pksId,
            'role' => 'operasional',
        ]);
        $this->assertDatabaseHas('sl_pks_visit_target', [
            'pks_id' => $this->pksId,
            'role' => 'crm',
        ]);
    }

    // ─── TEST 2: PKS activation trigger generate schedules ───────────
    /** @test */
    public function test_activation_generates_visit_schedules(): void
    {
        $pks = \App\Models\Pks::find($this->pksId);

        $service = app(VisitSchedulingService::class);
        $service->snapshotTargets($pks);
        $service->generateSchedules($pks);

        $count = DB::table('sl_pks_visit_schedule')
            ->where('pks_id', $this->pksId)
            ->count();

        $this->assertGreaterThan(0, $count, 'Should have generated schedule slots');
    }

    // ─── TEST 3: Snapshot total target = target_per_tahun × durasi ───
    /** @test */
    public function test_snapshot_total_target_equals_target_per_tahun_times_durasi(): void
    {
        $pks = \App\Models\Pks::find($this->pksId);

        $service = app(VisitSchedulingService::class);
        $service->snapshotTargets($pks);

        // kontrak: 2026-01-01 to 2027-12-31 = 2 tahun
        // target_per_tahun = 12, total should be 24
        $target = DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->first();

        $this->assertEquals(24, $target->target_total);
    }

    // ─── TEST 4: Target disimpan per role (operasional + crm) ────────
    /** @test */
    public function test_activation_creates_targets_per_role(): void
    {
        $pks = \App\Models\Pks::find($this->pksId);

        $service = app(VisitSchedulingService::class);
        $service->snapshotTargets($pks);

        $targets = DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->get();

        $this->assertCount(2, $targets);

        $roles = $targets->pluck('role')->toArray();
        $this->assertContains('operasional', $roles);
        $this->assertContains('crm', $roles);
    }
}
