<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks the optional `jenis_activity` query filter on:
 *  - GET /api/sales-report/activity-detail/{user_id}
 *  - GET /api/sales-report/activity-detail/tele/{user_id}
 * Filtering happens in the query, so `nomor` restarts at 1 for the filtered set.
 */
class ReportActivityDetailTest extends TestCase
{
    private int $salesUserId = 7001;

    private int $teleUserId = 7002;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
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

        DB::table('m_user')->insert([
            [
                'id' => 1, 'username' => 'admin', 'password' => bcrypt('x'),
                'full_name' => 'Admin', 'email' => 'admin@example.com',
                'cais_role_id' => 2, 'branch_id' => 1, 'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => $this->salesUserId, 'username' => 'sales', 'password' => bcrypt('x'),
                'full_name' => 'Sales Regular', 'email' => 'sales@example.com',
                'cais_role_id' => 2, 'branch_id' => 1, 'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => $this->teleUserId, 'username' => 'tele', 'password' => bcrypt('x'),
                'full_name' => 'Tele Sales', 'email' => 'tele@example.com',
                'cais_role_id' => 30, 'branch_id' => 1, 'is_active' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        DB::table('m_branch')->insert(['id' => 1, 'name' => 'HO', 'is_active' => 1]);
        DB::table('sl_leads')->insert(['id' => 10, 'nama_perusahaan' => 'PT Target']);
        DB::table('m_tim_sales_d')->insert(['id' => 1, 'user_id' => $this->salesUserId]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    // ── activityDetail (sales regular) ───────────────────────────────────

    public function test_detail_without_filter_returns_every_activity_type(): void
    {
        $this->seedSalesActivities();

        $response = $this->getJson("/api/sales-report/activity-detail/{$this->salesUserId}?month=7&year=2026");

        $response->assertOk()->assertJsonPath('success', true);
        $tipes = collect($response->json('data'))->pluck('tipe')->all();

        $this->assertEqualsCanonicalizing(['Leads', 'Visit', 'Visit', 'Quotation'], $tipes);
    }

    public function test_detail_filters_by_jenis_activity_and_renumbers_rows(): void
    {
        $this->seedSalesActivities();

        $response = $this->getJson("/api/sales-report/activity-detail/{$this->salesUserId}?month=7&year=2026&jenis_activity=Visit");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertSame(['Visit', 'Visit'], collect($data)->pluck('tipe')->all());
        $this->assertSame([1, 2], collect($data)->pluck('nomor')->all());
    }

    public function test_detail_appointment_filter_keeps_team_assigned_appointments(): void
    {
        // Appointment created by someone else, but the leads belongs to this sales' team.
        DB::table('sl_leads_kebutuhan')->insert(['id' => 1, 'leads_id' => 10, 'tim_sales_d_id' => 1]);
        DB::table('sl_activity_sales')->insert([
            [
                'leads_id' => 10, 'created_by_user_id' => 99999, 'jenis_activity' => 'Appointment',
                'tgl_activity' => '2026-07-08', 'notulen' => 'team appt', 'created_by' => 'Orang Lain',
                'created_at' => '2026-07-08 09:00:00',
            ],
            [
                'leads_id' => 10, 'created_by_user_id' => $this->salesUserId, 'jenis_activity' => 'Visit',
                'tgl_activity' => '2026-07-09', 'notulen' => 'visit note', 'created_by' => 'Sales Regular',
                'created_at' => '2026-07-09 09:00:00',
            ],
        ]);

        $response = $this->getJson("/api/sales-report/activity-detail/{$this->salesUserId}?month=7&year=2026&jenis_activity=Appointment");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Appointment', $data[0]['tipe']);
        $this->assertSame('team appt', $data[0]['notes']);
    }

    public function test_detail_rejects_unknown_jenis_activity_with_422(): void
    {
        $response = $this->getJson("/api/sales-report/activity-detail/{$this->salesUserId}?month=7&year=2026&jenis_activity=Ngawur");

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['jenis_activity']]);
    }

    public function test_detail_filter_still_returns_404_for_unknown_user(): void
    {
        $response = $this->getJson('/api/sales-report/activity-detail/999999?month=7&year=2026&jenis_activity=Visit');

        $response->assertStatus(404)->assertJsonPath('success', false);
    }

    // ── activityDetailTele ───────────────────────────────────────────────

    public function test_tele_filter_leads_returns_only_customer_activity(): void
    {
        $this->seedTeleActivities();

        $response = $this->getJson("/api/sales-report/activity-detail/tele/{$this->teleUserId}?month=7&year=2026&jenis_activity=Leads");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Leads', $data[0]['tipe']);
        $this->assertSame('leads note', $data[0]['notes']);
    }

    public function test_tele_filter_appointment_returns_only_activity_sales(): void
    {
        $this->seedTeleActivities();

        $response = $this->getJson("/api/sales-report/activity-detail/tele/{$this->teleUserId}?month=7&year=2026&jenis_activity=Appointment");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Appointment', $data[0]['tipe']);
        $this->assertSame('real appt', $data[0]['notes']);
    }

    public function test_tele_without_filter_still_merges_both_sources(): void
    {
        $this->seedTeleActivities();

        $response = $this->getJson("/api/sales-report/activity-detail/tele/{$this->teleUserId}?month=7&year=2026");

        $response->assertOk();
        $tipes = collect($response->json('data'))->pluck('tipe')->all();

        $this->assertEqualsCanonicalizing(['Leads', 'Assignment', 'Appointment'], $tipes);
    }

    public function test_tele_rejects_jenis_activity_outside_telesales_enum(): void
    {
        // Visit valid untuk sales regular, tapi bukan aktivitas telesales.
        $response = $this->getJson("/api/sales-report/activity-detail/tele/{$this->teleUserId}?month=7&year=2026&jenis_activity=Visit");

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['jenis_activity']]);
    }

    public function test_tele_accepts_assignment_which_regular_detail_rejects(): void
    {
        $this->seedTeleActivities();

        $this->getJson("/api/sales-report/activity-detail/tele/{$this->teleUserId}?month=7&year=2026&jenis_activity=Assignment")
            ->assertOk()
            ->assertJsonPath('data.0.tipe', 'Assignment');

        $this->getJson("/api/sales-report/activity-detail/{$this->salesUserId}?month=7&year=2026&jenis_activity=Assignment")
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['jenis_activity']]);
    }

    // ── keterbacaan data ─────────────────────────────────────────────────

    public function test_tele_falls_back_to_notes_when_notulen_is_empty(): void
    {
        // Penulis customer activity mengisi `notes`, bukan `notulen`.
        DB::table('sl_customer_activity')->insert([
            [
                'leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Leads',
                'tgl_activity' => '2026-07-02', 'notes' => 'dari kolom notes', 'notulen' => null,
                'created_by' => 'Tele Sales', 'created_at' => '2026-07-02 09:00:00',
            ],
            [
                'leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Assignment',
                'tgl_activity' => '2026-07-03', 'notes' => 'notes diabaikan', 'notulen' => 'notulen menang',
                'created_by' => 'Tele Sales', 'created_at' => '2026-07-03 09:00:00',
            ],
        ]);

        $response = $this->getJson("/api/sales-report/activity-detail/tele/{$this->teleUserId}?month=7&year=2026");

        $response->assertOk();
        $notes = collect($response->json('data'))->pluck('notes', 'tipe')->all();

        $this->assertSame('dari kolom notes', $notes['Leads']);
        $this->assertSame('notulen menang', $notes['Assignment']);
    }

    public function test_tele_excludes_soft_deleted_customer_activity(): void
    {
        DB::table('sl_customer_activity')->insert([
            [
                'leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Leads',
                'tgl_activity' => '2026-07-02', 'notulen' => 'masih hidup', 'created_by' => 'Tele Sales',
                'created_at' => '2026-07-02 09:00:00', 'deleted_at' => null,
            ],
            [
                'leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Assignment',
                'tgl_activity' => '2026-07-03', 'notulen' => 'sudah dihapus', 'created_by' => 'Tele Sales',
                'created_at' => '2026-07-03 09:00:00', 'deleted_at' => '2026-07-04 10:00:00',
            ],
        ]);

        $response = $this->getJson("/api/sales-report/activity-detail/tele/{$this->teleUserId}?month=7&year=2026");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('masih hidup', $data[0]['notes']);
    }

    public function test_detail_resolves_aksi_for_lowercase_spk_activity(): void
    {
        // Penulis SPK lama menyimpan 'spk' huruf kecil; laporan tetap harus menautkannya.
        DB::table('sl_activity_sales')->insert([
            [
                'leads_id' => 10, 'created_by_user_id' => $this->salesUserId, 'jenis_activity' => 'spk',
                'spk_id' => 77, 'tgl_activity' => '2026-07-06', 'notulen' => 'spk lowercase',
                'created_by' => 'Sales Regular', 'created_at' => '2026-07-06 09:00:00',
            ],
            [
                'leads_id' => 10, 'created_by_user_id' => $this->salesUserId, 'jenis_activity' => 'SPK',
                'spk_id' => 78, 'tgl_activity' => '2026-07-07', 'notulen' => 'spk uppercase',
                'created_by' => 'Sales Regular', 'created_at' => '2026-07-07 09:00:00',
            ],
        ]);

        $response = $this->getJson("/api/sales-report/activity-detail/{$this->salesUserId}?month=7&year=2026");

        $response->assertOk();
        $aksi = collect($response->json('data'))->pluck('aksi', 'notes')->all();

        $this->assertSame(77, $aksi['spk lowercase']);
        $this->assertSame(78, $aksi['spk uppercase']);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function seedSalesActivities(): void
    {
        DB::table('sl_quotation')->insert([
            'id' => 55, 'tipe_quotation' => 'baru',
        ]);

        DB::table('sl_activity_sales')->insert([
            [
                'leads_id' => 10, 'created_by_user_id' => $this->salesUserId, 'jenis_activity' => 'Leads',
                'quotation_id' => null, 'tgl_activity' => '2026-07-02', 'notulen' => 'leads note', 'created_by' => 'Sales Regular',
                'created_at' => '2026-07-02 09:00:00',
            ],
            [
                'leads_id' => 10, 'created_by_user_id' => $this->salesUserId, 'jenis_activity' => 'Visit',
                'quotation_id' => null, 'tgl_activity' => '2026-07-03', 'notulen' => 'visit 1', 'created_by' => 'Sales Regular',
                'created_at' => '2026-07-03 09:00:00',
            ],
            [
                'leads_id' => 10, 'created_by_user_id' => $this->salesUserId, 'jenis_activity' => 'Visit',
                'quotation_id' => null, 'tgl_activity' => '2026-07-04', 'notulen' => 'visit 2', 'created_by' => 'Sales Regular',
                'created_at' => '2026-07-04 09:00:00',
            ],
            [
                'leads_id' => 10, 'created_by_user_id' => $this->salesUserId, 'jenis_activity' => 'Quotation',
                'quotation_id' => 55, 'tgl_activity' => '2026-07-05', 'notulen' => 'quotation note',
                'created_by' => 'Sales Regular', 'created_at' => '2026-07-05 09:00:00',
            ],
            // Outside the requested period — must never show up.
            [
                'leads_id' => 10, 'created_by_user_id' => $this->salesUserId, 'jenis_activity' => 'Visit',
                'quotation_id' => null, 'tgl_activity' => '2026-08-01', 'notulen' => 'next month', 'created_by' => 'Sales Regular',
                'created_at' => '2026-08-01 09:00:00',
            ],
        ]);
    }

    private function seedTeleActivities(): void
    {
        DB::table('sl_customer_activity')->insert([
            [
                'leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Leads',
                'tgl_activity' => '2026-07-02', 'notulen' => 'leads note', 'created_by' => 'Tele Sales',
                'created_at' => '2026-07-02 09:00:00',
            ],
            [
                'leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Assignment',
                'tgl_activity' => '2026-07-03', 'notulen' => 'assign note', 'created_by' => 'Tele Sales',
                'created_at' => '2026-07-03 09:00:00',
            ],
        ]);

        DB::table('sl_activity_sales')->insert([
            'leads_id' => 10, 'created_by_user_id' => $this->teleUserId, 'jenis_activity' => 'Appointment',
            'tgl_activity' => '2026-07-05', 'notulen' => 'real appt', 'created_by' => 'Tele Sales',
            'created_at' => '2026-07-05 09:00:00',
        ]);
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'm_branch', 'sl_leads', 'sl_customer_activity', 'sl_activity_sales',
            'm_tim_sales_d', 'sl_leads_kebutuhan', 'sl_quotation',
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_tim_sales_d', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_leads_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipe_quotation')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_customer_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('tipe')->nullable();
            $table->date('tgl_activity')->nullable();
            $table->text('notes')->nullable();
            $table->text('notulen')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_activity_sales', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('jenis_activity')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('spk_id')->nullable();
            $table->unsignedInteger('pks_id')->nullable();
            $table->date('tgl_activity')->nullable();
            $table->text('notulen')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
}
