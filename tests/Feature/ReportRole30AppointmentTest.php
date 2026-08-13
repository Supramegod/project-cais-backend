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
 * Locks the telesales (cais_role_id = 30) appointment sourcing fix:
 * Leads/Assignment come from sl_customer_activity, but Appointment comes from
 * sl_activity_sales (jenis_activity='Appointment', created_by_user_id) — same
 * source as the regular activityDetail. Covers activityDetailTele + monthlyRole30.
 * weeklyRole30 uses MySQL DAY() (unrunnable on SQLite) and is not covered here.
 */
class ReportRole30AppointmentTest extends TestCase
{
    private int $teleUserId = 5001;

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

        // Acting admin user + the telesales (role 30) subject.
        DB::table('m_user')->insert([
            'id' => 1, 'username' => 'admin', 'password' => bcrypt('x'),
            'full_name' => 'Admin', 'email' => 'admin@example.com',
            'cais_role_id' => 2, 'branch_id' => 1, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('m_user')->insert([
            'id' => $this->teleUserId, 'username' => 'tele', 'password' => bcrypt('x'),
            'full_name' => 'Tele Sales', 'email' => 'tele@example.com',
            'cais_role_id' => 30, 'branch_id' => 1, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('m_branch')->insert(['id' => 1, 'name' => 'HO', 'is_active' => 1]);

        DB::table('sl_leads')->insert(['id' => 10, 'nama_perusahaan' => 'PT Target']);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    public function test_tele_detail_merges_customer_activity_and_activity_sales_appointment(): void
    {
        // Leads + Assignment in sl_customer_activity (filtered by user_id).
        DB::table('sl_customer_activity')->insert([
            ['leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Leads', 'tgl_activity' => '2026-07-02', 'notulen' => 'leads note', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-02 09:00:00'],
            ['leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Assignment', 'tgl_activity' => '2026-07-03', 'notulen' => 'assign note', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-03 09:00:00'],
            // An Appointment in customer_activity must be IGNORED now (source moved).
            ['leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Appointment', 'tgl_activity' => '2026-07-04', 'notulen' => 'OLD appt (ignored)', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-04 09:00:00'],
        ]);
        // Appointment in sl_activity_sales (filtered by created_by_user_id) — the real source.
        DB::table('sl_activity_sales')->insert([
            'leads_id' => 10, 'created_by_user_id' => $this->teleUserId, 'jenis_activity' => 'Appointment',
            'tgl_activity' => '2026-07-05', 'notulen' => 'REAL appt', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-05 09:00:00',
        ]);

        $response = $this->getJson("/api/sales-report/activity-detail/tele/{$this->teleUserId}?month=7&year=2026");

        $response->assertOk()->assertJsonPath('success', true);
        $tipes = collect($response->json('data'))->pluck('tipe')->all();

        $this->assertContains('Leads', $tipes);
        $this->assertContains('Assignment', $tipes);
        // Appointment present (from activity_sales), and only once (the customer_activity one is ignored).
        $this->assertSame(1, collect($tipes)->filter(fn ($t) => $t === 'Appointment')->count());
        $notes = collect($response->json('data'))->pluck('notes')->all();
        $this->assertContains('REAL appt', $notes);
        $this->assertNotContains('OLD appt (ignored)', $notes);
    }

    public function test_monthly_role30_counts_appointment_from_activity_sales(): void
    {
        DB::table('sl_customer_activity')->insert([
            ['leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Leads', 'tgl_activity' => '2026-07-02', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-02 09:00:00'],
            ['leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Assignment', 'tgl_activity' => '2026-07-03', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-03 09:00:00'],
            // Appointment here must NOT be counted anymore.
            ['leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Appointment', 'tgl_activity' => '2026-07-04', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-04 09:00:00'],
        ]);
        // Two appointments in activity_sales → jumlah_appointment should be 2.
        DB::table('sl_activity_sales')->insert([
            ['leads_id' => 10, 'created_by_user_id' => $this->teleUserId, 'jenis_activity' => 'Appointment', 'tgl_activity' => '2026-07-05', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-05 09:00:00'],
            ['leads_id' => 10, 'created_by_user_id' => $this->teleUserId, 'jenis_activity' => 'Appointment', 'tgl_activity' => '2026-07-06', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-06 09:00:00'],
        ]);

        $response = $this->getJson('/api/sales-report/monthly/tele?month=7&year=2026');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('user_id', $this->teleUserId);
        $this->assertNotNull($row);
        $this->assertSame(2, $row['aggregat']['jumlah_appointment']);
        $this->assertSame(1, $row['aggregat']['jumlah_leads']);
        $this->assertSame(1, $row['aggregat']['jumlah_assignment']);
    }

    public function test_monthly_role30_ignores_soft_deleted_customer_activity(): void
    {
        DB::table('sl_customer_activity')->insert([
            ['leads_id' => 10, 'user_id' => $this->teleUserId, 'tipe' => 'Leads', 'tgl_activity' => '2026-07-02', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-02 09:00:00', 'deleted_at' => null],
            ['leads_id' => 11, 'user_id' => $this->teleUserId, 'tipe' => 'Leads', 'tgl_activity' => '2026-07-03', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-03 09:00:00', 'deleted_at' => '2026-07-10 08:00:00'],
            ['leads_id' => 12, 'user_id' => $this->teleUserId, 'tipe' => 'Assignment', 'tgl_activity' => '2026-07-04', 'created_by' => 'Tele Sales', 'created_at' => '2026-07-04 09:00:00', 'deleted_at' => '2026-07-10 08:00:00'],
        ]);

        $response = $this->getJson('/api/sales-report/monthly/tele?month=7&year=2026');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('user_id', $this->teleUserId);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['aggregat']['jumlah_leads']);
        $this->assertSame(0, $row['aggregat']['jumlah_assignment']);
    }

    public function test_monthly_tele_invalid_month_returns_422_baserequest_shape(): void
    {
        // Validation now via ReportPeriodRequiredRequest -> BaseRequest 422 shape.
        $response = $this->getJson('/api/sales-report/monthly/tele?month=99&year=2026');

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['month']]);
    }

    public function test_inactive_telesales_is_excluded(): void
    {
        // Role-30 user but is_active = 0 -> must not appear in reports.
        $inactiveId = 5002;
        DB::table('m_user')->insert([
            'id' => $inactiveId, 'username' => 'tele_off', 'password' => bcrypt('x'),
            'full_name' => 'Tele Nonaktif', 'email' => 'off@example.com',
            'cais_role_id' => 30, 'branch_id' => 1, 'is_active' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sl_activity_sales')->insert([
            'leads_id' => 10, 'created_by_user_id' => $inactiveId, 'jenis_activity' => 'Appointment',
            'tgl_activity' => '2026-07-05', 'created_by' => 'Tele Nonaktif', 'created_at' => '2026-07-05 09:00:00',
        ]);

        // monthly: inactive user absent
        $monthly = $this->getJson('/api/sales-report/monthly/tele?month=7&year=2026');
        $monthly->assertOk();
        $this->assertNull(collect($monthly->json('data'))->firstWhere('user_id', $inactiveId));

        // detail: inactive user -> 404
        $detail = $this->getJson("/api/sales-report/activity-detail/tele/{$inactiveId}?month=7&year=2026");
        $detail->assertStatus(404)->assertJsonPath('success', false);
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'm_branch', 'sl_leads', 'sl_customer_activity', 'sl_activity_sales'] as $t) {
            Schema::dropIfExists($t);
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
            $table->date('tgl_activity')->nullable();
            $table->text('notulen')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
}
