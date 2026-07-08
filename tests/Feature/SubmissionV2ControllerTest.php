<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\SubmissionV2;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shapes of
 * SubmissionV2Controller around its refactor onto the ApiResponser trait
 * + FormRequest. Success/data/status envelopes must stay identical; the
 * validation (422) envelope and the view-not-found (404) path move to the
 * application-standard shapes as part of the refactor.
 */
class SubmissionV2ControllerTest extends TestCase
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

    private function makeSubmission(array $overrides = []): SubmissionV2
    {
        return SubmissionV2::query()->create(array_merge([
            'source_row_no' => 2,
            'nomor' => 'AAAAA',
            'tgl_leads' => now()->toDateString(),
            'nama_perusahaan' => 'PT Contoh',
            'pic' => 'Budi',
            'jabatan' => 'Manager',
            'no_telp' => '08123',
            'email' => 'budi@example.com',
            'status_leads_id' => 1,
            'wilayah_text' => 'Jakarta',
            'platform_text' => 'Web',
            'kebutuhan_text' => 'Security',
            'status_text' => 'Baru',
            'notes' => 'note',
            'synced_at' => now(),
        ], $overrides));
    }

    public function test_controller_class_loads(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\SubmissionV2Controller::class));
    }

    public function test_list_returns_expected_envelope(): void
    {
        $this->makeSubmission();

        $response = $this->getJson('/api/submission-v2/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data submission v2 berhasil diambil',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_perusahaan', 'PT Contoh')
            ->assertJsonPath('data.0.wilayah', 'Jakarta')
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination' => ['current_page', 'last_page', 'total', 'total_per_page'],
                'filter' => ['tgl_dari', 'tgl_sampai', 'branch'],
            ]);
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = $this->makeSubmission();

        $response = $this->getJson("/api/submission-v2/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $row->id)
            ->assertJsonPath('data.nama_perusahaan', 'PT Contoh');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/submission-v2/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_convert_creates_leads(): void
    {
        $row = $this->makeSubmission();

        $response = $this->postJson('/api/submission-v2/convert', ['id' => [$row->id]]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Submission V2 berhasil dikonversi menjadi leads',
            ])
            ->assertJsonCount(1, 'data.leads_ids');

        $this->assertDatabaseHas('sl_leads', ['nama_perusahaan' => 'PT Contoh']);
        $this->assertDatabaseHas('sl_customer_activity', ['tipe' => 'Leads']);
        $this->assertSoftDeleted('sl_submission_v2', ['id' => $row->id]);
    }

    public function test_convert_validation_error_returns_422(): void
    {
        // Application-standard FormRequest shape: { message: { field: [..] } }
        $response = $this->postJson('/api/submission-v2/convert', ['id' => []]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['id']]);
    }

    public function test_delete_soft_deletes(): void
    {
        $row = $this->makeSubmission();

        $response = $this->postJson('/api/submission-v2/delete', ['id' => [$row->id]]);

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Submission V2 berhasil dihapus',
            ]);

        $this->assertSoftDeleted('sl_submission_v2', ['id' => $row->id]);
    }

    public function test_delete_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/submission-v2/delete', ['id' => []]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['id']]);
    }

    public function test_sync_skipped(): void
    {
        $this->markTestSkipped(
            'sync() pulls a CSV from an external Google Sheet over HTTP and upserts '.
            'against a multi-connection lookup graph (mysqlhris m_branch etc); not '.
            'deterministically seedable in a characterization test.'
        );
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'sl_submission_v2', 'sl_leads', 'sl_customer_activity',
            'm_branch', 'm_platform', 'm_status_leads', 'm_kebutuhan',
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

        Schema::create('sl_submission_v2', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('source_row_no')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_leads')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('pic')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('platform_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('hc')->nullable();
            $table->string('total_invoice')->nullable();
            $table->text('notes')->nullable();
            $table->string('wilayah_text')->nullable();
            $table->string('platform_text')->nullable();
            $table->string('kebutuhan_text')->nullable();
            $table->string('status_text')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('created_by')->nullable();
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
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_customer_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->dateTime('tgl_activity')->nullable();
            $table->string('nomor')->nullable();
            $table->text('notes')->nullable();
            $table->string('tipe')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->tinyInteger('is_activity')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
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

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });
    }
}
