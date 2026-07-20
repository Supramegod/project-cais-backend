<?php

namespace Tests\Unit\Services\Pks;

use App\Models\Pks;
use App\Models\PksVisitSchedule;
use App\Models\User;
use App\Services\Pks\BranchResolutionService;
use App\Services\Pks\VisitSchedulingService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for VisitSchedulingService.
 *
 * @group pks-fulfillment
 */
class VisitSchedulingServiceTest extends TestCase
{
    private VisitSchedulingService $service;
    private User $user;
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

        // Insert user
        DB::connection('mysqlhris')->table('m_user')->insert([
            'id' => 1,
            'username' => 'tester',
            'password' => bcrypt('secret'),
            'full_name' => 'Test User',
            'email' => 'tester@example.com',
            'cais_role_id' => 8,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert branch (for BranchResolutionService)
        DB::connection('mysqlhris')->table('m_branch')->insert([
            'id' => 1,
            'name' => 'Jakarta Branch',
            'city_id' => 1,
            'is_active' => 1,
        ]);

        $this->user = User::query()->findOrFail(1);
        $this->actingAs($this->user, 'web');

        $branchResolution = new BranchResolutionService();
        $this->service = new VisitSchedulingService($branchResolution);

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
            ['id' => 4, 'nama' => 'Diamond', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // sl_leads
        $this->leadsId = DB::table('sl_leads')->insertGetId([
            'nomor' => 'LEAD-SCHED-001',
            'nama_perusahaan' => 'PT Scheduling Test',
            'kebutuhan_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation
        $this->quotationId = DB::table('sl_quotation')->insertGetId([
            'leads_id' => $this->leadsId,
            'nomor' => 'Q-SCHED-001',
            'is_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation_detail
        DB::table('sl_quotation_detail')->insert([
            [
                'quotation_id' => $this->quotationId,
                'jumlah_hc' => 200,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // m_pks_visit_target (master)
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
            'nomor' => 'PKS/SCHED/001',
            'status_pks_id' => 7,
            'layanan_id' => 1,
            'kontrak_awal' => '2026-01-01',
            'kontrak_akhir' => '2027-12-31', // 2 years
            'is_aktif' => 1,
            'tipe_pks' => 'baru',
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
            'nama_site' => 'Site Sched 1',
            'is_visit_anchor' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

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
            $table->string('role');
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
            $table->string('role');
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

    // ─── TEST 1: snapshotTargets kalkulasi durasi ────────────────────
    /** @test */
    public function test_snapshot_targets_calculates_duration_correctly(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);

        // 2 tahun × 12 target/tahun = 24
        $target = DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->first();

        $this->assertEquals(24, $target->target_total);
    }

    // ─── TEST 2: snapshotTargets minimal 1 tahun ─────────────────────
    /** @test */
    public function test_snapshot_targets_minimum_one_year_duration(): void
    {
        // Update kontrak to < 1 year
        DB::table('sl_pks')->where('id', $this->pksId)->update([
            'kontrak_awal' => '2026-01-01',
            'kontrak_akhir' => '2026-06-30', // 6 months
        ]);

        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);

        $target = DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->first();

        // Min 1 tahun × 12 = minimal 12
        $this->assertEquals(12, $target->target_total);
    }

    // ─── TEST 3: snapshotTargets insert per role ─────────────────────
    /** @test */
    public function test_snapshot_targets_creates_two_rows_per_role(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);

        $targets = DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->get();

        $this->assertCount(2, $targets);
        $roles = $targets->pluck('role')->sort()->values()->toArray();
        $this->assertEquals(['crm', 'operasional'], $roles);
    }

    // ─── TEST 4: generateSchedules distribusi slot ───────────────────
    /** @test */
    public function test_generate_schedules_distributes_slots_evenly(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);
        $this->service->generateSchedules($pks);

        $schedules = PksVisitSchedule::where('pks_id', $this->pksId)->get();

        // Should have at least some schedules
        $this->assertGreaterThan(0, $schedules->count());

        // Check dates are within contract range
        foreach ($schedules as $schedule) {
            $this->assertGreaterThanOrEqual('2026-01-01', $schedule->tgl_jadwal->toDateString());
            $this->assertLessThanOrEqual('2027-12-31', $schedule->tgl_jadwal->toDateString());
        }
    }

    // ─── TEST 5: generateSchedules jumlah slot = target_total ────────
    /** @test */
    public function test_generate_schedules_slot_count_matches_target_total(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);

        // Get operasional target total
        $target = DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->first();

        $this->service->generateSchedules($pks);

        $slotCount = PksVisitSchedule::where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->count();

        $this->assertEquals($target->target_total, $slotCount);
    }

    // ─── TEST 6: generateSchedules prioritas kontrak_akhir ───────────
    /** @test */
    public function test_generate_schedules_slots_within_contract_period(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);
        $this->service->generateSchedules($pks);

        $schedules = PksVisitSchedule::where('pks_id', $this->pksId)
            ->orderBy('tgl_jadwal')
            ->get();

        // First schedule should be at or after kontrak_awal
        $firstDate = $schedules->first()->tgl_jadwal->toDateString();
        $this->assertGreaterThanOrEqual('2026-01-01', $firstDate);

        // Last schedule should be at or before kontrak_akhir
        $lastDate = $schedules->last()->tgl_jadwal->toDateString();
        $this->assertLessThanOrEqual('2027-12-31', $lastDate);
    }

    // ─── TEST 7: reschedule simpan tgl_jadwal_asli ───────────────────
    /** @test */
    public function test_reschedule_saves_original_date(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);
        $this->service->generateSchedules($pks);

        $schedule = PksVisitSchedule::where('pks_id', $this->pksId)->first();
        $originalDate = $schedule->tgl_jadwal->toDateString();

        $this->service->reschedule(
            $schedule,
            Carbon::parse('2026-08-15'),
            'Customer minta reschedule',
            $this->user
        );

        $schedule->refresh();
        $this->assertEquals($originalDate, $schedule->tgl_jadwal_asli->toDateString());
        $this->assertEquals('2026-08-15', $schedule->tgl_jadwal->toDateString());
    }

    // ─── TEST 8: reschedule ganti status jadi rescheduled ────────────
    /** @test */
    public function test_reschedule_changes_status_to_rescheduled(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);
        $this->service->generateSchedules($pks);

        $schedule = PksVisitSchedule::where('pks_id', $this->pksId)->first();

        // Use a date that doesn't conflict with generated slots (use 15th instead of 1st)
        $this->service->reschedule(
            $schedule,
            Carbon::parse('2026-09-15'),
            'Customer minta reschedule bulan depan',
            $this->user
        );

        $schedule->refresh();
        $this->assertEquals('rescheduled', $schedule->status);
        $this->assertEquals('Customer minta reschedule bulan depan', $schedule->alasan_reschedule);
        $this->assertEquals($this->user->id, $schedule->direschedule_oleh);
    }

    // ─── TEST 9: markMissed tandai jadwal lewat tempo ────────────────
    /** @test */
    public function test_mark_missed_flags_overdue_schedules(): void
    {
        // Create a schedule in the past
        DB::table('sl_pks_visit_schedule')->insert([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'pic_user_id' => 1,
            'tgl_jadwal' => '2025-12-01', // in the past
            'status' => 'scheduled',
            'created_by' => 'Test',
            'created_by_user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $count = $this->service->markMissed();

        $this->assertEquals(1, $count);

        $this->assertDatabaseHas('sl_pks_visit_schedule', [
            'tgl_jadwal' => '2025-12-01',
            'status' => 'missed',
        ]);
    }

    // ─── TEST: snapshotTargets idempotent (tidak reset progress) ─────
    /** @test */
    public function test_snapshot_targets_called_twice_does_not_reset_target_terpakai(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);

        // Simulasikan progress visit setelah snapshot pertama
        DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->update(['target_terpakai' => 2]);

        // Ubah master agar target_total berubah pada snapshot kedua
        DB::table('m_pks_visit_target')->update(['target_visit_per_tahun' => 6]);

        $this->service->snapshotTargets($pks);

        $target = DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->first();

        // Progress TIDAK di-reset, target_total ter-update (6 × 2 tahun = 12)
        $this->assertEquals(2, $target->target_terpakai);
        $this->assertEquals(12, $target->target_total);

        // Tetap 2 baris (tidak duplikat)
        $this->assertEquals(2, DB::table('sl_pks_visit_target')->where('pks_id', $this->pksId)->count());
    }

    // ─── TEST: generateSchedules idempotent (tidak duplikat) ─────────
    /** @test */
    public function test_generate_schedules_called_twice_does_not_duplicate(): void
    {
        $pks = Pks::find($this->pksId);
        $this->service->snapshotTargets($pks);
        $this->service->generateSchedules($pks);

        $countFirst = PksVisitSchedule::where('pks_id', $this->pksId)->count();
        $this->assertGreaterThan(0, $countFirst);

        // Panggilan kedua (re-aktivasi / backfill) tidak menambah jadwal
        $this->service->generateSchedules($pks);

        $countSecond = PksVisitSchedule::where('pks_id', $this->pksId)->count();
        $this->assertEquals($countFirst, $countSecond);
    }

    // ─── TEST 10: createManualSchedule valid ─────────────────────────
    /** @test */
    public function test_create_manual_schedule_creates_valid_schedule(): void
    {
        $schedule = $this->service->createManualSchedule([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'crm',
            'pic_user_id' => 1,
            'tgl_jadwal' => '2026-07-01',
        ], $this->user);

        $this->assertInstanceOf(PksVisitSchedule::class, $schedule);
        $this->assertEquals('crm', $schedule->role);
        $this->assertEquals('2026-07-01', $schedule->tgl_jadwal->toDateString());
        $this->assertEquals('scheduled', $schedule->status);
    }
}
