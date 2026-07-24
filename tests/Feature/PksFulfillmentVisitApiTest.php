<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for PKS Visit Scheduling & Fulfillment API endpoints.
 *
 * @group pks-fulfillment
 */
class PksFulfillmentVisitApiTest extends TestCase
{
    private int $pksId;
    private int $siteId;
    private int $leadsId;
    private int $scheduleId;

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

        // Insert test users on mysqlhris
        DB::connection('mysqlhris')->table('m_user')->insert([
            [
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
            ],
            [
                'id' => 2,
                'username' => 'crm_user',
                'password' => bcrypt('secret'),
                'full_name' => 'CRM User',
                'email' => 'crm@example.com',
                'cais_role_id' => 54,
                'branch_id' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
            'nomor' => 'LEAD-VISIT-001',
            'nama_perusahaan' => 'PT Visit Test',
            'kebutuhan_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_pks
        $this->pksId = DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId,
            'quotation_id' => null,
            'nomor' => 'PKS/VISIT/001',
            'status_pks_id' => 7,
            'layanan_id' => 1,
            'kontrak_awal' => '2026-01-01',
            'kontrak_akhir' => '2027-12-31',
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
            'quotation_id' => null,
            'kota_id' => 1,
            'kota' => 'Jakarta',
            'nama_site' => 'Site Visit 1',
            'is_visit_anchor' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_pks_visit_target (pre-populated targets)
        DB::table('sl_pks_visit_target')->insert([
            [
                'pks_id' => $this->pksId,
                'role' => 'operasional',
                'kategori_sesuai_hc_id' => 4,
                'target_total' => 12,
                'target_terpakai' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pks_id' => $this->pksId,
                'role' => 'crm',
                'kategori_sesuai_hc_id' => 4,
                'target_total' => 8,
                'target_terpakai' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Pre-create one schedule entry
        $this->scheduleId = DB::table('sl_pks_visit_schedule')->insertGetId([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'pic_user_id' => 1,
            'tgl_jadwal' => '2026-06-15',
            'status' => 'scheduled',
            'created_by' => 'Admin User',
            'created_by_user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── Schema ────────────────────────────────────────────────────────

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_pks_fulfillment_log',
            'sl_pks_visit_record_foto',
            'sl_pks_visit_record',
            'sl_pks_visit_schedule',
            'sl_pks_visit_target',
            'sl_quotation_detail',
            'sl_quotation',
            'sl_site',
            'sl_pks',
            'sl_leads',
            'm_kategori_sesuai_hc',
            'm_kebutuhan',
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
            $table->string('status')->default('scheduled'); // scheduled, rescheduled, done, missed
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sl_pks_visit_record', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('leads_id');
            $table->string('role'); // operasional, crm
            $table->unsignedBigInteger('user_id');
            $table->date('tgl_visit_aktual');
            $table->string('hasil_visit'); // selesai, ada_kendala, ditunda
            $table->text('catatan');
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks_visit_record_foto', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('visit_record_id');
            $table->string('url_file', 500);
            $table->string('nama_file', 255);
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sl_pks_fulfillment_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('pks_id');
            $table->string('jenis', 32);
            $table->unsignedBigInteger('reference_id');
            $table->string('aksi', 32);
            $table->text('catatan')->nullable();
            $table->json('meta')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    // ─── TEST 1: GET visit schedule ──────────────────────────────────
    /** @test */
    public function test_get_visit_schedule_returns_list(): void
    {
        $response = $this->getJson("/api/pks-fulfillment/{$this->pksId}/visit-schedule");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Visit schedule retrieved successfully.')
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function test_get_visit_schedule_filtered_by_role(): void
    {
        $response = $this->getJson("/api/pks-fulfillment/{$this->pksId}/visit-schedule?role=operasional");

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        // crm should return empty since schedule is operasional
        $responseCrm = $this->getJson("/api/pks-fulfillment/{$this->pksId}/visit-schedule?role=crm");
        $responseCrm->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ─── TEST 2: POST manual schedule success ────────────────────────
    /** @test */
    public function test_store_manual_schedule_creates_jadwal(): void
    {
        Storage::fake('visit-photo');

        $response = $this->postJson('/api/pks-fulfillment/visit-schedule', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'crm',
            'pic_user_id' => 1,
            'tgl_jadwal' => '2026-08-15',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Jadwal manual berhasil dibuat.');

        $exists = DB::table('sl_pks_visit_schedule')
            ->where('pks_id', $this->pksId)
            ->where('role', 'crm')
            ->where('status', 'scheduled')
            ->where('tgl_jadwal', 'LIKE', '2026-08-15%')
            ->exists();
        $this->assertTrue($exists, 'Schedule should exist');
    }

    // ─── TEST 3: POST manual schedule duplikat ───────────────────────
    /** @test */
    public function test_store_manual_schedule_duplicate_returns_422(): void
    {
        // First schedule already exists for operasional on 2026-06-15
        // Try to create another with same leads_id + tanggal + role
        $response = $this->postJson('/api/pks-fulfillment/visit-schedule', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'pic_user_id' => 1,
            'tgl_jadwal' => '2026-06-15', // same date as existing
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    // ─── TEST 4: POST manual schedule di luar rentang ────────────────
    /** @test */
    public function test_store_manual_schedule_outside_contract_range_returns_422(): void
    {
        $response = $this->postJson('/api/pks-fulfillment/visit-schedule', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'crm',
            'pic_user_id' => 1,
            'tgl_jadwal' => '2025-01-01', // before kontrak_awal 2026-01-01
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    // ─── TEST 5: PATCH reschedule success ────────────────────────────
    /** @test */
    public function test_reschedule_changes_tgl_jadwal(): void
    {
        $originalDate = '2026-06-15';

        $response = $this->patchJson(
            "/api/pks-fulfillment/visit-schedule/{$this->scheduleId}/reschedule",
            [
                'tgl_jadwal' => '2026-09-20',
                'alasan' => 'Customer minta reschedule karena ada event',
            ]
        );

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Jadwal berhasil di-reschedule.');

        $schedule = DB::table('sl_pks_visit_schedule')->find($this->scheduleId);
        $this->assertStringContainsString('2026-09-20', $schedule->tgl_jadwal);
        $this->assertEquals('rescheduled', $schedule->status);
    }

    // ─── TEST 6: PATCH reschedule simpan tgl_jadwal_asli ─────────────
    /** @test */
    public function test_reschedule_saves_original_date(): void
    {
        $this->patchJson(
            "/api/pks-fulfillment/visit-schedule/{$this->scheduleId}/reschedule",
            [
                'tgl_jadwal' => '2026-10-01',
                'alasan' => 'Reschedule pertama untuk testing',
            ]
        );

        $schedule = DB::table('sl_pks_visit_schedule')->find($this->scheduleId);
        $this->assertStringContainsString('2026-10-01', $schedule->tgl_jadwal);
        $this->assertStringContainsString('2026-06-15', $schedule->tgl_jadwal_asli);
        $this->assertEquals('rescheduled', $schedule->status);
    }

    // ─── TEST 7: GET visit target ────────────────────────────────────
    /** @test */
    public function test_get_visit_target_returns_remaining_targets(): void
    {
        $response = $this->getJson("/api/pks-fulfillment/{$this->pksId}/visit-target");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Visit target retrieved successfully.')
            ->assertJsonCount(2, 'data'); // operasional + crm
    }

    /** @test */
    public function test_get_visit_target_filtered_by_role(): void
    {
        $response = $this->getJson("/api/pks-fulfillment/{$this->pksId}/visit-target?role=operasional");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', 'operasional');
    }

    // ─── TEST 8: POST visit record success (dengan foto) ─────────────
    /** @test */
    public function test_store_visit_record_creates_with_photos(): void
    {
        Storage::fake('visit-photo');

        $response = $this->postJson('/api/pks-fulfillment/visit-record', [
            'schedule_id' => $this->scheduleId,
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'tgl_visit_aktual' => '2026-06-15',
            'hasil_visit' => 'selesai',
            'catatan' => 'Visit berjalan lancar tanpa kendala',
            'fotos' => [
                UploadedFile::fake()->image('foto1.jpg'),
            ],
        ]);

        // VisitPhotoService will try to encode via Intervention — Storage::fake should bypass
        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Hasil visit berhasil disimpan.');

        $this->assertDatabaseHas('sl_pks_visit_record', [
            'pks_id' => $this->pksId,
            'role' => 'operasional',
            'hasil_visit' => 'selesai',
        ]);
    }

    // ─── TEST 9: POST visit record tanpa foto ────────────────────────
    /** @test */
    public function test_store_visit_record_without_photos_returns_422(): void
    {
        $response = $this->postJson('/api/pks-fulfillment/visit-record', [
            'schedule_id' => $this->scheduleId,
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'tgl_visit_aktual' => '2026-06-15',
            'hasil_visit' => 'selesai',
            'catatan' => 'Visit berjalan lancar tanpa kendala',
            // 'fotos' => missing
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['fotos']]);
    }

    // ─── TEST 10: POST visit record foto > 3 file ────────────────────
    /** @test */
    public function test_store_visit_record_more_than_3_photos_returns_422(): void
    {
        $response = $this->postJson('/api/pks-fulfillment/visit-record', [
            'schedule_id' => $this->scheduleId,
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'tgl_visit_aktual' => '2026-06-15',
            'hasil_visit' => 'selesai',
            'catatan' => 'Visit berjalan lancar tanpa kendala',
            'fotos' => [
                UploadedFile::fake()->image('foto1.jpg'),
                UploadedFile::fake()->image('foto2.jpg'),
                UploadedFile::fake()->image('foto3.jpg'),
                UploadedFile::fake()->image('foto4.jpg'),
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['fotos']]);
    }

    // ─── TEST 11: POST visit record target habis ─────────────────────
    /** @test */
    public function test_store_visit_record_blocked_when_target_exhausted(): void
    {
        // Set target_terpakai = target_total for operasional role
        DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->update(['target_terpakai' => 12]); // exhaust all 12

        Storage::fake('visit-photo');

        $response = $this->postJson('/api/pks-fulfillment/visit-record', [
            'schedule_id' => $this->scheduleId,
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'tgl_visit_aktual' => '2026-06-15',
            'hasil_visit' => 'selesai',
            'catatan' => 'Visit berjalan lancar tanpa kendala',
            'fotos' => [
                UploadedFile::fake()->image('foto1.jpg'),
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    // ─── TEST 12: GET visit history ──────────────────────────────────
    /** @test */
    public function test_get_visit_history_returns_records(): void
    {
        // Create a visit record first
        Storage::fake('visit-photo');

        $this->postJson('/api/pks-fulfillment/visit-record', [
            'schedule_id' => $this->scheduleId,
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'tgl_visit_aktual' => '2026-06-15',
            'hasil_visit' => 'selesai',
            'catatan' => 'Visit pertama berjalan lancar',
            'fotos' => [
                UploadedFile::fake()->image('foto1.jpg'),
            ],
        ]);

        $response = $this->getJson("/api/pks-fulfillment/{$this->pksId}/visit-record");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Visit history retrieved successfully.')
            ->assertJsonCount(1, 'data');
    }

    // ─── TEST 13: PATCH reschedule bentrok ───────────────────────────
    /** @test */
    public function test_reschedule_conflict_with_another_schedule_returns_422(): void
    {
        // Create second schedule on 2026-09-01 for operasional
        DB::table('sl_pks_visit_schedule')->insert([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'pic_user_id' => 1,
            'tgl_jadwal' => '2026-09-01',
            'status' => 'scheduled',
            'created_by' => 'Admin User',
            'created_by_user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to reschedule the first schedule to same date as second
        $response = $this->patchJson(
            "/api/pks-fulfillment/visit-schedule/{$this->scheduleId}/reschedule",
            [
                'tgl_jadwal' => '2026-09-01',
                'alasan' => 'Mencoba reschedule tapi bentrok',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
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
