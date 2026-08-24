<?php

namespace Tests\Unit\Services\Pks\Fulfillment;

use App\Models\Pks;
use App\Models\PksFulfillmentLog;
use App\Models\PksVisitRecord;
use App\Models\User;
use App\Services\Pks\Fulfillment\VisitFulfillmentService;
use App\Services\Pks\Fulfillment\VisitPhotoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for VisitFulfillmentService.
 *
 * @group pks-fulfillment
 */
class VisitFulfillmentServiceTest extends TestCase
{
    private VisitFulfillmentService $service;
    private User $user;
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

        // Insert user
        DB::connection('mysqlhris')->table('m_user')->insert([
            'id' => 1,
            'username' => 'tester',
            'password' => bcrypt('secret'),
            'full_name' => 'Test User',
            'email' => 'tester@example.com',
            'cais_role_id' => 54,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = User::query()->findOrFail(1);

        $photoService = $this->createMockVisitPhotoService();
        $this->service = new VisitFulfillmentService($photoService);

        $this->seedBaseData();
    }

    private function createMockVisitPhotoService(): VisitPhotoService
    {
        // Create a mock that returns fake data without real image processing
        $mock = $this->getMockBuilder(VisitPhotoService::class)
            ->onlyMethods(['uploadAndCompress'])
            ->getMock();

        $mock->method('uploadAndCompress')
            ->willReturnCallback(function (UploadedFile $file) {
                return [
                    'url_file' => 'https://test-storage.example.com/photos/fake-' . rand(1000, 9999) . '.jpg',
                    'nama_file' => 'fake-' . date('YmdHis') . '.jpg',
                ];
            });

        return $mock;
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
            'nomor' => 'LEAD-FULFILL-001',
            'nama_perusahaan' => 'PT Fulfill Test',
            'kebutuhan_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_pks
        $this->pksId = DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId,
            'quotation_id' => null,
            'nomor' => 'PKS/FULFILL/001',
            'status_pks_id' => 7,
            'layanan_id' => 1,
            'kontrak_awal' => '2026-01-01',
            'kontrak_akhir' => '2027-12-31',
            'is_aktif' => 1,
            'tipe_pks' => 'baru',
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
            'nama_site' => 'Site Fulfill 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Visit target
        DB::table('sl_pks_visit_target')->insert([
            [
                'pks_id' => $this->pksId,
                'role' => 'operasional',
                'kategori_sesuai_hc_id' => 4,
                'target_total' => 5,
                'target_terpakai' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pks_id' => $this->pksId,
                'role' => 'crm',
                'kategori_sesuai_hc_id' => 4,
                'target_total' => 3,
                'target_terpakai' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Visit schedule
        $this->scheduleId = DB::table('sl_pks_visit_schedule')->insertGetId([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'role' => 'operasional',
            'pic_user_id' => 1,
            'tgl_jadwal' => '2026-06-15',
            'status' => 'scheduled',
            'created_by' => 'Test User',
            'created_by_user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_pks_fulfillment_log',
            'sl_pks_visit_record_foto',
            'sl_pks_visit_record',
            'sl_pks_visit_schedule',
            'sl_pks_visit_target',
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
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nomor')->nullable();
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

        Schema::create('sl_pks_visit_record', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('leads_id');
            $table->string('role');
            $table->unsignedBigInteger('user_id');
            $table->date('tgl_visit_aktual');
            $table->string('hasil_visit');
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
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('jenis', 32);
            $table->unsignedBigInteger('reference_id');
            $table->uuid('batch_id')->nullable();
            $table->unsignedInteger('batch_ke')->nullable();
            $table->string('aksi', 32);
            $table->text('catatan')->nullable();
            $table->json('meta')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    // ─── TEST 1: createVisitRecord blocked jika target habis ─────────
    /** @test */
    public function test_create_visit_record_blocked_when_target_exhausted(): void
    {
        // Exhaust the operasional target
        DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->update(['target_terpakai' => 5]); // equal to target_total

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Target visit operasional untuk PKS ini sudah terpenuhi.');

        $this->service->createVisitRecord(
            [
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'role' => 'operasional',
                'tgl_visit_aktual' => '2026-06-15',
                'hasil_visit' => 'selesai',
                'catatan' => 'Visit test',
            ],
            [UploadedFile::fake()->image('foto1.jpg')],
            $this->user
        );
    }

    // ─── TEST: createVisitRecord throw saat target habis (atomic) ────
    /** @test */
    public function test_create_visit_record_throws_and_not_saved_when_target_exhausted_atomically(): void
    {
        Storage::fake('visit-photo');

        // Target sudah habis: total 1, terpakai 1
        DB::table('sl_pks_visit_target')
            ->where('pks_id', $this->pksId)
            ->where('role', 'operasional')
            ->update(['target_total' => 1, 'target_terpakai' => 1]);

        try {
            $this->service->createVisitRecord(
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'leads_id' => $this->leadsId,
                    'role' => 'operasional',
                    'tgl_visit_aktual' => '2026-06-15',
                    'hasil_visit' => 'selesai',
                    'catatan' => 'Visit saat target habis',
                ],
                [UploadedFile::fake()->image('foto1.jpg')],
                $this->user
            );

            $this->fail('RuntimeException tidak dilempar padahal target sudah habis.');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Target visit operasional untuk PKS ini sudah terpenuhi.', $e->getMessage());
        }

        // Record TIDAK tersimpan (transaksi rollback)
        $this->assertEquals(0, DB::table('sl_pks_visit_record')->where('pks_id', $this->pksId)->count());
        $this->assertEquals(0, DB::table('sl_pks_visit_record_foto')->count());

        // target_terpakai tidak berubah
        $this->assertDatabaseHas('sl_pks_visit_target', [
            'pks_id' => $this->pksId,
            'role' => 'operasional',
            'target_terpakai' => 1,
        ]);
    }

    // ─── TEST 2: createVisitRecord increment target_terpakai ─────────
    /** @test */
    public function test_create_visit_record_increments_target_terpakai(): void
    {
        $this->service->createVisitRecord(
            [
                'schedule_id' => $this->scheduleId,
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'role' => 'operasional',
                'tgl_visit_aktual' => '2026-06-15',
                'hasil_visit' => 'selesai',
                'catatan' => 'Visit test untuk increment',
            ],
            [UploadedFile::fake()->image('foto1.jpg')],
            $this->user
        );

        $this->assertDatabaseHas('sl_pks_visit_target', [
            'pks_id' => $this->pksId,
            'role' => 'operasional',
            'target_terpakai' => 1,
        ]);
    }

    // ─── TEST 2b: createVisitRecord menulis log jenis visit ──────────
    /** @test */
    public function test_create_visit_record_writes_fulfillment_log(): void
    {
        $record = $this->service->createVisitRecord(
            [
                'schedule_id' => $this->scheduleId,
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'role' => 'operasional',
                'tgl_visit_aktual' => '2026-06-15',
                'hasil_visit' => 'selesai',
                'catatan' => 'Visit operasional selesai',
            ],
            [UploadedFile::fake()->image('foto1.jpg')],
            $this->user
        );

        $log = PksFulfillmentLog::forRef(PksFulfillmentLog::JENIS_VISIT, $record->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->pksId, $log->pks_id);
        $this->assertEquals($this->siteId, $log->site_id);
        $this->assertSame('Visit operasional selesai', $log->catatan);
        $this->assertSame('operasional', $log->meta['role']);
        $this->assertSame('selesai', $log->meta['hasil_visit']);
        $this->assertSame(1, $log->meta['jumlah_foto']);
    }

    // ─── TEST 3: createVisitRecord update jadwal ke done ─────────────
    /** @test */
    public function test_create_visit_record_updates_schedule_to_done(): void
    {
        $this->service->createVisitRecord(
            [
                'schedule_id' => $this->scheduleId,
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'role' => 'operasional',
                'tgl_visit_aktual' => '2026-06-15',
                'hasil_visit' => 'selesai',
                'catatan' => 'Visit completed',
            ],
            [UploadedFile::fake()->image('foto1.jpg')],
            $this->user
        );

        $this->assertDatabaseHas('sl_pks_visit_schedule', [
            'id' => $this->scheduleId,
            'status' => 'done',
        ]);
    }

    // ─── TEST 4: createVisitRecord upload foto ───────────────────────
    /** @test */
    public function test_create_visit_record_stores_photos(): void
    {
        $record = $this->service->createVisitRecord(
            [
                'schedule_id' => $this->scheduleId,
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'role' => 'operasional',
                'tgl_visit_aktual' => '2026-06-15',
                'hasil_visit' => 'selesai',
                'catatan' => 'Visit with 2 photos',
            ],
            [
                UploadedFile::fake()->image('foto1.jpg'),
                UploadedFile::fake()->image('foto2.jpg'),
            ],
            $this->user
        );

        $this->assertDatabaseHas('sl_pks_visit_record_foto', [
            'visit_record_id' => $record->id,
        ]);

        $fotoCount = DB::table('sl_pks_visit_record_foto')
            ->where('visit_record_id', $record->id)
            ->count();
        $this->assertEquals(2, $fotoCount);
    }

    // ─── TEST 5: getVisitHistory lintas role ─────────────────────────
    /** @test */
    public function test_get_visit_history_returns_all_role_records(): void
    {
        // Create operasional visit
        $this->service->createVisitRecord(
            [
                'schedule_id' => $this->scheduleId,
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'role' => 'operasional',
                'tgl_visit_aktual' => '2026-06-15',
                'hasil_visit' => 'selesai',
                'catatan' => 'Visit operasional',
            ],
            [UploadedFile::fake()->image('foto1.jpg')],
            $this->user
        );

        // Create CRM visit (without schedule)
        $this->service->createVisitRecord(
            [
                'schedule_id' => null,
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'role' => 'crm',
                'tgl_visit_aktual' => '2026-07-20',
                'hasil_visit' => 'ada_kendala',
                'catatan' => 'Visit CRM dengan kendala',
            ],
            [UploadedFile::fake()->image('foto_crm.jpg')],
            $this->user
        );

        $pks = Pks::find($this->pksId);
        $history = $this->service->getVisitHistory($pks);

        $this->assertCount(2, $history);

        $roles = $history->pluck('role')->toArray();
        $this->assertContains('operasional', $roles);
        $this->assertContains('crm', $roles);
    }

    // ─── TEST 6: getScheduleByPks ────────────────────────────────────
    /** @test */
    public function test_get_schedule_by_pks_returns_related_schedules(): void
    {
        $pks = Pks::find($this->pksId);
        $schedules = $this->service->getScheduleByPks($pks);

        $this->assertCount(1, $schedules);
        $this->assertEquals($this->scheduleId, $schedules->first()->id);
    }

    // ─── TEST 7: getScheduleByPks filtered by role ──────────────────
    /** @test */
    public function test_get_schedule_by_pks_filtered_by_role(): void
    {
        $pks = Pks::find($this->pksId);

        $operasional = $this->service->getScheduleByPks($pks, 'operasional');
        $this->assertCount(1, $operasional);

        $crm = $this->service->getScheduleByPks($pks, 'crm');
        $this->assertCount(0, $crm, 'No CRM schedules exist');
    }

    // ─── TEST 8: getVisitTarget ──────────────────────────────────────
    /** @test */
    public function test_get_visit_target_returns_correct_data(): void
    {
        $pks = Pks::find($this->pksId);
        $targets = $this->service->getVisitTarget($pks);

        $this->assertCount(2, $targets);
        $this->assertEquals(5, $targets[0]['target_total']);
        $this->assertEquals(0, $targets[0]['target_terpakai']);
        $this->assertEquals(5, $targets[0]['sisa']);
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
