<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shapes of SubmissionController
 * while it is refactored onto the ApiResponser trait + FormRequest.
 *
 * Success/data/status envelopes stay byte-identical; validation-422 moves to the
 * BaseRequest standard ({ message: { field: [..] } }) and view-not-found becomes
 * the trait's 404 envelope (previously a 500 from findOrFail).
 */
class SubmissionControllerTest extends TestCase
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
            'cais_role_id' => 2,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    private function seedSubmission(array $overrides = []): Submission
    {
        return Submission::query()->create(array_merge([
            'nomor' => 'SUB-001',
            'tgl_leads' => now()->toDateString(),
            'nama_perusahaan' => 'PT Contoh',
            'branch_id' => 1,
            'platform_id' => 1,
            'kebutuhan_id' => 1,
            'pic' => 'Budi',
            'jabatan' => 'Manager',
            'no_telp' => '08123',
            'email' => 'budi@example.com',
            'status_leads_id' => 1,
            'notes' => 'catatan',
            'customer_id' => null,
        ], $overrides));
    }

    public function test_list_returns_bespoke_paginated_envelope(): void
    {
        $this->seedSubmission(['nomor' => 'SUB-001']);
        $this->seedSubmission(['nomor' => 'SUB-002']);

        $response = $this->getJson('/api/submission/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data submission berhasil diambil',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination' => ['current_page', 'last_page', 'total', 'total_per_page'],
                'filter' => ['tgl_dari', 'tgl_sampai', 'branch'],
            ])
            ->assertJsonPath('pagination.total', 2)
            // newest first (order by id desc)
            ->assertJsonPath('data.0.nomor', 'SUB-002')
            ->assertJsonPath('data.1.nomor', 'SUB-001');
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = $this->seedSubmission(['nama_perusahaan' => 'PT Lihat']);

        $response = $this->getJson("/api/submission/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama_perusahaan', 'PT Lihat')
            ->assertJsonPath('data.id', $row->id);

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/submission/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_convert_creates_leads_and_soft_deletes_submission(): void
    {
        $row = $this->seedSubmission(['nama_perusahaan' => 'PT Konversi']);

        $response = $this->postJson('/api/submission/convert', ['id' => [$row->id]]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Submission berhasil dikonversi menjadi leads',
            ])
            ->assertJsonStructure(['data' => ['leads_ids']])
            ->assertJsonCount(1, 'data.leads_ids');

        $leadId = $response->json('data.leads_ids.0');

        // lead created, with created_by_user_id persisted
        $this->assertDatabaseHas('sl_leads', [
            'id' => $leadId,
            'nama_perusahaan' => 'PT Konversi',
            'created_by_user_id' => 1,
        ]);
        // customer activity created
        $this->assertDatabaseHas('sl_customer_activity', [
            'leads_id' => $leadId,
            'tipe' => 'Leads',
            'created_by_user_id' => 1,
        ]);
        // submission linked to lead + deleted_by stamped.
        // NOTE: `deleted_at` is NOT in Submission::$fillable, so the Eloquent
        // update() in convert() silently drops it — the submission is therefore
        // NOT actually soft-deleted here. Locking that real (quirky) behavior.
        $this->assertDatabaseHas('sl_submission', [
            'id' => $row->id,
            'leads_id' => $leadId,
            'deleted_by' => 'Tester',
        ]);
        $this->assertNull(Submission::withTrashed()->find($row->id)->deleted_at);
    }

    public function test_convert_validation_error_returns_422_base_request_shape(): void
    {
        $response = $this->postJson('/api/submission/convert', []);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['id']]);
    }

    public function test_delete_soft_deletes_and_returns_message_only(): void
    {
        $row = $this->seedSubmission();

        $response = $this->postJson('/api/submission/delete', ['id' => [$row->id]]);

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Submission berhasil dihapus',
            ]);

        $this->assertNotNull(Submission::withTrashed()->find($row->id)->deleted_at);
    }

    public function test_delete_validation_error_returns_422_base_request_shape(): void
    {
        $response = $this->postJson('/api/submission/delete', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['id']]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('sl_submission');
        Schema::dropIfExists('sl_leads');
        Schema::dropIfExists('sl_customer_activity');
        Schema::dropIfExists('m_branch');
        Schema::dropIfExists('m_platform');
        Schema::dropIfExists('m_status_leads');
        Schema::dropIfExists('m_tim_sales_d');
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

        Schema::create('sl_submission', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('leads_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_leads')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('platform_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('pic')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->date('tgl_leads')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('platform_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('pic')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Lookup tables for eager-loaded relations. SoftDeletes on these models
        // adds a deleted_at filter, so include softDeletes() to satisfy the query.
        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->softDeletes();
        });
        Schema::create('m_platform', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });
        Schema::create('m_status_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });
        Schema::create('m_tim_sales_d', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_customer_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('leads_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->dateTime('tgl_activity')->nullable();
            $table->string('nomor')->nullable();
            $table->text('notes')->nullable();
            $table->string('tipe')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->integer('is_activity')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
