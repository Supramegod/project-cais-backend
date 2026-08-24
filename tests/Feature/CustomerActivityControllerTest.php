<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization + refactor-lock test for CustomerActivityController.
 *
 * Locks the ApiResponser envelope (keys present, status codes) plus the
 * BaseRequest 422 shape ({ message: { field: [...] } }) for the seedable,
 * HTTP-routed endpoints. Heavy / unrouted endpoints are skipped with reasons.
 *
 * NOTE: only 8 endpoints are registered in routes/api.php
 * (list, view, send-email, add, update, delete, leads/{id}/track, available).
 * The 7 "contract" endpoints (getTimSalesMembers, addContractActivity,
 * getContractActivities, assignRO, assignCRM, updateContractStatus,
 * getContractIssues) have NO route and therefore cannot be exercised via HTTP.
 */
class CustomerActivityControllerTest extends TestCase
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
            'cais_role_id' => 2, // superadmin -> filterByUserRole passes through
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    /** Seed a leads row and return its id. */
    private function seedLeads(array $overrides = []): int
    {
        DB::table('m_branch')->insertOrIgnore(['id' => 1, 'name' => 'Jakarta']);

        $id = DB::table('sl_leads')->insertGetId(array_merge([
            'nama_perusahaan' => 'PT Contoh',
            'branch_id' => 1,
            'kebutuhan_id' => 2,
            'nomor' => 'LS001',
            'status_leads_id' => null,
            'customer_id' => null,
            'tgl_leads' => now()->toDateString(),
            'pic' => 'Budi',
            'no_telp' => '0812',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    private function seedActivity(int $leadsId, array $overrides = []): int
    {
        return DB::table('sl_customer_activity')->insertGetId(array_merge([
            'nomor' => 'CAT/LS/LS001-'.now()->format('mY').'-00001',
            'leads_id' => $leadsId,
            'branch_id' => 1,
            'tgl_activity' => now()->toDateString(),
            'tipe' => 'Telepon',
            'notes' => 'catatan',
            'is_activity' => 1,
            'user_id' => 1,
            'created_by' => 'Tester',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ---- list ----

    public function test_list_returns_success_envelope_with_pagination(): void
    {
        $leadsId = $this->seedLeads();
        $this->seedActivity($leadsId);
        $this->seedActivity($leadsId, ['tipe' => 'Visit']);

        $response = $this->getJson('/api/customer-activities/list');

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Data aktivitas berhasil diambil'])
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination' => ['current_page', 'last_page', 'total', 'per_page'],
                'meta' => ['tgl_dari', 'tgl_sampai', 'filtered_types'],
            ]);

        $this->assertSame(2, $response->json('pagination.total'));
    }

    public function test_list_invalid_date_range_returns_422_string_message(): void
    {
        $response = $this->getJson('/api/customer-activities/list?tgl_dari=2024-12-31&tgl_sampai=2024-01-01');

        $response->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Tanggal dari tidak boleh melebihi tanggal sampai.',
            ]);
    }

    // ---- view ----

    public function test_view_found_returns_success_data_without_message(): void
    {
        $leadsId = $this->seedLeads();
        $id = $this->seedActivity($leadsId);

        $response = $this->getJson("/api/customer-activities/view/{$id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $id);

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/customer-activities/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ]);
    }

    // ---- add ----

    public function test_add_creates_and_returns_201(): void
    {
        $leadsId = $this->seedLeads();

        $response = $this->postJson('/api/customer-activities/add', [
            'leads_id' => $leadsId,
            'tgl_activity' => now()->toDateString(),
            'tipe' => 'Telepon',
            'notes' => 'Follow up',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.tipe', 'Telepon');

        $this->assertStringContainsString('Customer Activity berhasil dibuat', $response->json('message'));
        $this->assertDatabaseHas('sl_customer_activity', ['leads_id' => $leadsId, 'tipe' => 'Telepon', 'notes' => 'Follow up']);
    }

    public function test_add_validation_error_returns_422_baserequest_shape(): void
    {
        $response = $this->postJson('/api/customer-activities/add', []);

        // BaseRequest shape: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['leads_id', 'tgl_activity', 'tipe']]);

        $this->assertArrayNotHasKey('success', $response->json());
    }

    public function test_add_unknown_leads_returns_404(): void
    {
        // leads_id passes 'exists' only if present; use a valid-but-soft-deleted leads
        $leadsId = $this->seedLeads(['deleted_at' => now()]);

        $response = $this->postJson('/api/customer-activities/add', [
            'leads_id' => $leadsId,
            'tgl_activity' => now()->toDateString(),
            'tipe' => 'Telepon',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Leads tidak ditemukan atau sudah dihapus.',
            ]);
    }

    // ---- update ----

    public function test_update_found_returns_success(): void
    {
        $leadsId = $this->seedLeads();
        $id = $this->seedActivity($leadsId);

        $response = $this->putJson("/api/customer-activities/update/{$id}", [
            'notes' => 'Diperbarui',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Customer Activity berhasil diupdate',
            ])
            ->assertJsonPath('data.id', $id);

        $this->assertDatabaseHas('sl_customer_activity', ['id' => $id, 'notes' => 'Diperbarui']);
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/customer-activities/update/999', [
            'notes' => 'x',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ]);
    }

    public function test_update_validation_error_returns_422_baserequest_shape(): void
    {
        $leadsId = $this->seedLeads();
        $id = $this->seedActivity($leadsId);

        // invalid 'tipe' value triggers the in-rule
        $response = $this->putJson("/api/customer-activities/update/{$id}", [
            'tipe' => 'NotAValidType',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['tipe']]);
    }

    // ---- delete ----

    public function test_delete_found_returns_message_only(): void
    {
        $leadsId = $this->seedLeads();
        $id = $this->seedActivity($leadsId);

        $response = $this->deleteJson("/api/customer-activities/delete/{$id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Customer Activity berhasil dihapus',
            ]);

        $this->assertSoftDeleted('sl_customer_activity', ['id' => $id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/customer-activities/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ]);
    }

    // ---- availableLeads ----

    public function test_available_leads_returns_success_with_message(): void
    {
        $this->seedLeads(['nama_perusahaan' => 'PT Tersedia']);

        $response = $this->getJson('/api/customer-activities/available');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data leads tersedia berhasil diambil',
            ])
            ->assertJsonPath('data.0.nama_perusahaan', 'PT Tersedia');
    }

    // ---- skipped / not exercised ----

    public function test_send_email_skipped(): void
    {
        $this->markTestSkipped('sendEmail drives DynamicMailerService SMTP + Mail::send; sensitive, left untouched except envelope. Not exercised here.');
    }

    public function test_track_activity_skipped(): void
    {
        $this->markTestSkipped('trackActivity eager-loads the full leads+kebutuhan+branch graph; excluded per scope.');
    }

    public function test_contract_endpoints_have_no_routes(): void
    {
        $this->markTestSkipped('getTimSalesMembers/addContractActivity/getContractActivities/assignRO/assignCRM/updateContractStatus/getContractIssues are not registered in routes/api.php; cannot be exercised via HTTP.');
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_customer_activity_file',
            'sl_customer_activity',
            'sl_leads_kebutuhan',
            'sl_leads',
            'm_kebutuhan',
            'm_status_leads',
            'm_tim_sales_d',
            'm_branch',
            'm_user',
        ] as $t) {
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
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_tim_sales_d', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_status_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->date('tgl_leads')->nullable();
            $table->string('pic')->nullable();
            $table->string('no_telp')->nullable();
            $table->unsignedInteger('ro_id')->nullable();
            $table->string('ro')->nullable();
            $table->unsignedInteger('crm_id')->nullable();
            $table->string('crm')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_leads_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_customer_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('spk_id')->nullable();
            $table->unsignedInteger('pks_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->date('tgl_activity')->nullable();
            $table->string('tipe')->nullable();
            $table->text('notes')->nullable();
            $table->text('notes_tipe')->nullable();
            $table->string('start')->nullable();
            $table->string('end')->nullable();
            $table->integer('durasi')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->string('jam_realisasi')->nullable();
            $table->string('penerima')->nullable();
            $table->string('link_bukti_foto')->nullable();
            $table->text('notulen')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('ro_id')->nullable();
            $table->string('ro')->nullable();
            $table->unsignedInteger('crm_id')->nullable();
            $table->string('crm')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->string('jenis_visit')->nullable();
            $table->unsignedInteger('jenis_visit_id')->nullable();
            $table->tinyInteger('is_activity')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_customer_activity_file', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_activity_id')->nullable();
            $table->string('nama_file')->nullable();
            $table->string('url_file')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
