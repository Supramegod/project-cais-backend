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
 * Characterization test: locks the JSON response shapes of SalesActivityController.
 *
 * The controller is being refactored onto the App\Traits\ApiResponser envelope +
 * a FormRequest. Every success/error envelope must stay byte-compatible with the
 * pre-refactor shape; the ONLY intentional change is the 422 validation body, which
 * moves to the app-standard BaseRequest shape ({ message: { field: [...] } }).
 *
 * setUp copies the 3-connection sqlite wiring from BentukUsahaControllerTest — the
 * controller/models touch the default, mysqlhris and mysql connections.
 */
class SalesActivityControllerTest extends TestCase
{
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
            'id' => 1,
            'username' => 'tester',
            'password' => bcrypt('secret'),
            'full_name' => 'Tester',
            'email' => 'tester@example.com',
            'cais_role_id' => 2, // superadmin — bypasses every role filter
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    /** Seed one lead + kebutuhan (+ optional activity) and return their ids. */
    private function seedLeadWithKebutuhan(string $namaPerusahaan = 'PT Contoh'): array
    {
        $leadId = DB::table('sl_leads')->insertGetId([
            'nama_perusahaan' => $namaPerusahaan,
            'pic' => 'John',
            'telp_perusahaan' => '021',
            'email' => 'a@b.com',
            'branch_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kebId = DB::table('m_kebutuhan')->insertGetId(['nama' => 'Security Guard']);

        $lkId = DB::table('sl_leads_kebutuhan')->insertGetId([
            'leads_id' => $leadId,
            'kebutuhan_id' => $kebId,
            'tim_sales_id' => null,
            'tim_sales_d_id' => null,
        ]);

        return ['lead' => $leadId, 'kebutuhan' => $kebId, 'leads_kebutuhan' => $lkId];
    }

    private function seedActivity(int $leadId, int $lkId, string $jenis = 'Meeting'): int
    {
        return DB::table('sl_activity_sales')->insertGetId([
            'leads_id' => $leadId,
            'leads_kebutuhan_id' => $lkId,
            'tgl_activity' => '2024-01-15',
            'jenis_activity' => $jenis,
            'notulen' => 'Notes',
            'created_by' => 'Tester',
            'created_by_user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_list_returns_success_data_envelope(): void
    {
        $ids = $this->seedLeadWithKebutuhan();
        $this->seedActivity($ids['lead'], $ids['leads_kebutuhan']);

        $response = $this->getJson('/api/sales-activity/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data sales activity berhasil diambil',
            ])
            // data is a paginator envelope
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_view_found_returns_formatted_data(): void
    {
        $ids = $this->seedLeadWithKebutuhan();
        $activityId = $this->seedActivity($ids['lead'], $ids['leads_kebutuhan']);

        $response = $this->getJson("/api/sales-activity/view/{$activityId}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Detail sales activity berhasil diambil',
            ])
            ->assertJsonPath('data.id', $activityId)
            ->assertJsonPath('data.jenis_activity', 'Meeting')
            ->assertJsonPath('data.tgl_activity', '2024-01-15')
            ->assertJsonStructure(['data' => ['id', 'jenis_activity', 'notulen', 'tgl_activity', 'activity_files']]);
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/sales-activity/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Sales activity tidak ditemukan',
            ]);
    }

    public function test_store_creates_and_returns_201(): void
    {
        $ids = $this->seedLeadWithKebutuhan();

        $tglActivity = now()->toDateString();

        $response = $this->postJson('/api/sales-activity/add', [
            'leads_id' => $ids['lead'],
            'leads_kebutuhan_id' => $ids['leads_kebutuhan'],
            'tgl_activity' => $tglActivity,
            'jenis_activity' => 'Meeting',
            'notulen' => 'Diskusi kebutuhan',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Sales activity berhasil ditambahkan',
            ])
            ->assertJsonPath('data.jenis_activity', 'Meeting');

        $this->assertDatabaseHas('sl_activity_sales', [
            'leads_id' => $ids['lead'],
            'notulen' => 'Diskusi kebutuhan',
            'created_by_user_id' => 1,
        ]);

        // tgl_leads side-effect update on the parent lead
        $this->assertDatabaseHas('sl_leads', [
            'id' => $ids['lead'],
            'tgl_leads' => $tglActivity,
        ]);
    }

    public function test_store_backdated_more_than_3_days_returns_422(): void
    {
        $ids = $this->seedLeadWithKebutuhan();

        $response = $this->postJson('/api/sales-activity/add', [
            'leads_id' => $ids['lead'],
            'leads_kebutuhan_id' => $ids['leads_kebutuhan'],
            'tgl_activity' => now()->subDays(4)->toDateString(),
            'jenis_activity' => 'Meeting',
            'notulen' => 'Diskusi kebutuhan',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['tgl_activity']]);
    }

    public function test_store_validation_error_returns_422_baserequest_shape(): void
    {
        // Intentional post-refactor contract change: 422 now uses the BaseRequest
        // shape { message: { field: [...] } } instead of { success:false, message:<bag> }.
        $response = $this->postJson('/api/sales-activity/add', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['leads_id', 'leads_kebutuhan_id', 'tgl_activity', 'jenis_activity', 'notulen']]);
    }

    public function test_store_kebutuhan_not_related_returns_422(): void
    {
        $a = $this->seedLeadWithKebutuhan('PT A');
        $b = $this->seedLeadWithKebutuhan('PT B');

        // valid ids (pass FormRequest exists) but kebutuhan belongs to lead B
        $response = $this->postJson('/api/sales-activity/add', [
            'leads_id' => $a['lead'],
            'leads_kebutuhan_id' => $b['leads_kebutuhan'],
            'tgl_activity' => now()->toDateString(),
            'jenis_activity' => 'Meeting',
            'notulen' => 'x',
        ]);

        $response->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Leads Kebutuhan tidak terkait dengan Leads yang dipilih',
            ]);
    }

    public function test_kebutuhan_by_leads_returns_success(): void
    {
        $ids = $this->seedLeadWithKebutuhan();

        $response = $this->getJson("/api/sales-activity/kebutuhan/{$ids['lead']}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data kebutuhan berhasil diambil',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Security Guard');
    }

    public function test_available_leads_returns_success(): void
    {
        $this->seedLeadWithKebutuhan();

        $response = $this->getJson('/api/sales-activity/available-leads');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data leads berhasil diambil',
            ])
            ->assertJsonCount(1, 'data.leads')
            ->assertJsonStructure(['data' => ['leads', 'pagination' => ['total', 'per_page', 'current_page', 'last_page']]]);
    }

    public function test_stats_skipped_on_sqlite(): void
    {
        // getStats() uses MySQL-only MONTH() / groupByRaw('MONTH(tgl_activity)'),
        // which SQLite cannot execute. Not characterizable against the sqlite test DB.
        $this->markTestSkipped('getStats uses MySQL MONTH() — not runnable on SQLite test connection.');
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('sl_activity_sales_file');
        Schema::dropIfExists('sl_activity_sales');
        Schema::dropIfExists('sl_leads_kebutuhan');
        Schema::dropIfExists('m_kebutuhan');
        Schema::dropIfExists('sl_leads');
        Schema::dropIfExists('m_user');

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

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->string('pic')->nullable();
            $table->string('telp_perusahaan')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->date('tgl_leads')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_leads_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_activity_sales', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('leads_kebutuhan_id')->nullable();
            $table->date('tgl_activity')->nullable();
            $table->string('start')->nullable();
            $table->string('end')->nullable();
            $table->string('durasi')->nullable();
            $table->string('penerima')->nullable();
            $table->string('jenis_visit')->nullable();
            $table->string('jenis_activity')->nullable();
            $table->text('notulen')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sl_activity_sales_file', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('activity_sales_id')->nullable();
            $table->string('nama_file')->nullable();
            $table->string('url_file')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
