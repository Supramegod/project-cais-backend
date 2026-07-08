<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\ManagementFee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of
 * ManagementFeeController onto the ApiResponser envelope + BaseRequest.
 * Success/data/status keys must stay identical to the pre-refactor contract;
 * 422 follows the approved BaseRequest shape { message: { field: [..] } }.
 */
class ManagementFeeControllerTest extends TestCase
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

    public function test_list_returns_success_data_envelope(): void
    {
        ManagementFee::query()->create(['nama' => 'MF A']);
        ManagementFee::query()->create(['nama' => 'MF B']);

        $response = $this->getJson('/api/management-fee/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_list_all_returns_success_data_id_nama(): void
    {
        ManagementFee::query()->create(['nama' => 'MF A']);

        $response = $this->getJson('/api/management-fee/list-all');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'MF A');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = ManagementFee::query()->create(['nama' => 'MF View']);

        $response = $this->getJson("/api/management-fee/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama', 'MF View');
        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/management-fee/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Management Fee tidak ditemukan',
            ]);
    }

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/management-fee/add', ['nama' => 'MF Baru']);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Management Fee berhasil dibuat',
            ])
            ->assertJsonPath('data.nama', 'MF Baru');

        $this->assertDatabaseHas('m_management_fee', ['nama' => 'MF Baru']);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/management-fee/add', ['nama' => '']);

        // BaseRequest shape: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama']]);
    }

    public function test_add_duplicate_nama_returns_422(): void
    {
        ManagementFee::query()->create(['nama' => 'Dup']);

        $response = $this->postJson('/api/management-fee/add', ['nama' => 'Dup']);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama']]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = ManagementFee::query()->create(['nama' => 'Lama']);

        $response = $this->putJson("/api/management-fee/update/{$row->id}", ['nama' => 'Baru']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Management Fee berhasil diupdate',
            ])
            ->assertJsonPath('data.nama', 'Baru');
    }

    public function test_update_keeps_same_nama_is_valid(): void
    {
        $row = ManagementFee::query()->create(['nama' => 'Tetap']);

        $response = $this->putJson("/api/management-fee/update/{$row->id}", ['nama' => 'Tetap']);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/management-fee/update/999', ['nama' => 'X']);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Management Fee tidak ditemukan',
            ]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = ManagementFee::query()->create(['nama' => 'Hapus']);

        $response = $this->deleteJson("/api/management-fee/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Management Fee berhasil dihapus',
            ]);

        $this->assertSoftDeleted('m_management_fee', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/management-fee/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Management Fee tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_management_fee');
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

        Schema::create('m_management_fee', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
