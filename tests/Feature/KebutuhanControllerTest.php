<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Kebutuhan;
use App\Models\KebutuhanDetail;
use App\Models\KebutuhanDetailRequirement;
use App\Models\KebutuhanDetailTunjangan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of KebutuhanController.
 * Success/data/status envelopes must stay byte-identical across the refactor
 * onto the ApiResponser trait; only the 422 validation envelope migrates to the
 * standard BaseRequest shape ({ message: { field: [...] } }).
 */
class KebutuhanControllerTest extends TestCase
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

    public function test_list_returns_success_message_data_envelope(): void
    {
        Kebutuhan::query()->create(['nama' => 'Kebutuhan IT']);
        Kebutuhan::query()->create(['nama' => 'Kebutuhan HR']);

        $response = $this->getJson('/api/kebutuhan/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Daftar kebutuhan',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_list_detail_returns_success_data(): void
    {
        $kebutuhan = Kebutuhan::query()->create(['nama' => 'Kebutuhan IT']);
        KebutuhanDetail::query()->create([
            'kebutuhan_id' => $kebutuhan->id,
            'nama' => 'Detail A',
            'position_id' => 1,
        ]);

        $response = $this->getJson("/api/kebutuhan/list-detail/{$kebutuhan->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Daftar detail kebutuhan',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kebutuhan_id', $kebutuhan->id);
    }

    public function test_list_detail_tunjangan_filters_by_position(): void
    {
        $kebutuhan = Kebutuhan::query()->create(['nama' => 'Kebutuhan IT']);
        KebutuhanDetailTunjangan::query()->create([
            'kebutuhan_id' => $kebutuhan->id,
            'position_id' => 1,
            'nama' => 'Transport',
            'nominal' => 500000,
        ]);
        KebutuhanDetailTunjangan::query()->create([
            'kebutuhan_id' => $kebutuhan->id,
            'position_id' => 2,
            'nama' => 'Makan',
            'nominal' => 300000,
        ]);

        $all = $this->getJson("/api/kebutuhan/list-detail-tunjangan/{$kebutuhan->id}");
        $all->assertOk()
            ->assertJson(['success' => true, 'message' => 'Daftar detail tunjangan'])
            ->assertJsonCount(2, 'data');

        $filtered = $this->getJson("/api/kebutuhan/list-detail-tunjangan/{$kebutuhan->id}?position_id=1");
        $filtered->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Transport');
    }

    public function test_add_detail_tunjangan_creates_and_strips_comma(): void
    {
        $kebutuhan = Kebutuhan::query()->create(['nama' => 'Kebutuhan IT']);

        $response = $this->postJson('/api/kebutuhan/add-detail-tunjangan', [
            'kebutuhan_id' => $kebutuhan->id,
            'position_id' => 1,
            'nama' => 'Transport',
            'nominal' => '500,000',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data berhasil ditambahkan',
            ])
            ->assertJsonPath('data.nama', 'Transport');

        $this->assertDatabaseHas('m_kebutuhan_detail_tunjangan', [
            'kebutuhan_id' => $kebutuhan->id,
            'nama' => 'Transport',
            'nominal' => 500000,
        ]);
    }

    public function test_add_detail_tunjangan_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/kebutuhan/add-detail-tunjangan', [
            'nama' => 'Transport',
        ]);

        // BaseRequest shape: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['kebutuhan_id', 'nominal']]);
    }

    public function test_delete_detail_tunjangan_found_returns_success(): void
    {
        $kebutuhan = Kebutuhan::query()->create(['nama' => 'Kebutuhan IT']);
        $row = KebutuhanDetailTunjangan::query()->create([
            'kebutuhan_id' => $kebutuhan->id,
            'position_id' => 1,
            'nama' => 'Transport',
            'nominal' => 500000,
        ]);

        $response = $this->deleteJson("/api/kebutuhan/delete-detail-tunjangan/{$row->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil menghapus data',
                'data' => [],
            ]);

        $this->assertSoftDeleted('m_kebutuhan_detail_tunjangan', ['id' => $row->id]);
    }

    public function test_delete_detail_tunjangan_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/kebutuhan/delete-detail-tunjangan/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_list_detail_requirement_filters_by_position(): void
    {
        $kebutuhan = Kebutuhan::query()->create(['nama' => 'Kebutuhan IT']);
        KebutuhanDetailRequirement::query()->create([
            'kebutuhan_id' => $kebutuhan->id,
            'position_id' => 1,
            'requirement' => 'Min S1',
        ]);
        KebutuhanDetailRequirement::query()->create([
            'kebutuhan_id' => $kebutuhan->id,
            'position_id' => 2,
            'requirement' => 'Min D3',
        ]);

        $all = $this->getJson("/api/kebutuhan/list-detail-requirement/{$kebutuhan->id}");
        $all->assertOk()
            ->assertJson(['success' => true, 'message' => 'Daftar detail requirement'])
            ->assertJsonCount(2, 'data');

        $filtered = $this->getJson("/api/kebutuhan/list-detail-requirement/{$kebutuhan->id}?position_id=1");
        $filtered->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.requirement', 'Min S1');
    }

    public function test_add_detail_requirement_creates_and_returns_201(): void
    {
        $kebutuhan = Kebutuhan::query()->create(['nama' => 'Kebutuhan IT']);

        $response = $this->postJson('/api/kebutuhan/add-detail-requirement', [
            'kebutuhan_id' => $kebutuhan->id,
            'position_id' => 1,
            'requirement' => 'Min S1 Teknik',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data berhasil ditambahkan',
            ])
            ->assertJsonPath('data.requirement', 'Min S1 Teknik');

        $this->assertDatabaseHas('m_kebutuhan_detail_requirement', [
            'kebutuhan_id' => $kebutuhan->id,
            'requirement' => 'Min S1 Teknik',
        ]);
    }

    public function test_add_detail_requirement_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/kebutuhan/add-detail-requirement', [
            'position_id' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['kebutuhan_id', 'requirement']]);
    }

    public function test_delete_detail_requirement_found_returns_success(): void
    {
        $kebutuhan = Kebutuhan::query()->create(['nama' => 'Kebutuhan IT']);
        $row = KebutuhanDetailRequirement::query()->create([
            'kebutuhan_id' => $kebutuhan->id,
            'position_id' => 1,
            'requirement' => 'Min S1',
        ]);

        $response = $this->deleteJson("/api/kebutuhan/delete-detail-requirement/{$row->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil menghapus data',
                'data' => [],
            ]);

        $this->assertSoftDeleted('m_kebutuhan_detail_requirement', ['id' => $row->id]);
    }

    public function test_delete_detail_requirement_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/kebutuhan/delete-detail-requirement/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_user');
        Schema::dropIfExists('m_kebutuhan');
        Schema::dropIfExists('m_kebutuhan_detail');
        Schema::dropIfExists('m_kebutuhan_detail_tunjangan');
        Schema::dropIfExists('m_kebutuhan_detail_requirement');

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
            $table->string('icon')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_kebutuhan_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('nama')->nullable();
            $table->unsignedInteger('position_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_kebutuhan_detail_tunjangan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('position_id')->nullable();
            $table->string('nama')->nullable();
            $table->decimal('nominal', 15, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_kebutuhan_detail_requirement', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('position_id')->nullable();
            $table->text('requirement')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
