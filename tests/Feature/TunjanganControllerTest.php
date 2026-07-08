<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Kebutuhan;
use App\Models\Position;
use App\Models\TunjanganPosisi;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of TunjanganController
 * on the ApiResponser envelope. Happy-path envelopes (success/message/data +
 * status) are byte-identical to the pre-refactor controller; error paths use
 * the application standard (notFoundResponse 404 + BaseRequest 422).
 */
class TunjanganControllerTest extends TestCase
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

    private function seedKebutuhan(int $id = 1, string $nama = 'Kebersihan'): Kebutuhan
    {
        return Kebutuhan::query()->create(['id' => $id, 'nama' => $nama]);
    }

    private function seedPosition(string $name = 'Cleaning Service'): Position
    {
        return Position::query()->create(['name' => $name]);
    }

    private function seedTunjangan(array $overrides = []): TunjanganPosisi
    {
        return TunjanganPosisi::query()->create(array_merge([
            'kebutuhan_id' => 1,
            'position_id' => 1,
            'nama' => 'Tunjangan Makan',
            'nominal' => 500000,
        ], $overrides));
    }

    public function test_list_returns_success_data_envelope(): void
    {
        $this->seedKebutuhan();
        $this->seedPosition();
        $this->seedTunjangan(['nama' => 'Tunjangan Makan']);
        $this->seedTunjangan(['nama' => 'Tunjangan Transport']);

        $response = $this->getJson('/api/tunjangan/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data retrieved successfully',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [['id', 'nama', 'nominal', 'nama_kebutuhan', 'nama_jabatan', 'created_at', 'created_by']],
            ])
            ->assertJsonPath('data.0.nama_kebutuhan', 'Kebersihan')
            ->assertJsonPath('data.0.nama_jabatan', 'Cleaning Service');
    }

    public function test_list_search_filters_by_nama(): void
    {
        $this->seedKebutuhan();
        $this->seedPosition();
        $this->seedTunjangan(['nama' => 'Tunjangan Makan']);
        $this->seedTunjangan(['nama' => 'Tunjangan Transport']);

        $response = $this->getJson('/api/tunjangan/list?search=Transport');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Tunjangan Transport');
    }

    public function test_view_found_returns_success_data(): void
    {
        $this->seedKebutuhan();
        $this->seedPosition();
        $row = $this->seedTunjangan(['nama' => 'Tunjangan Jabatan']);

        $response = $this->getJson("/api/tunjangan/view/{$row->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data retrieved successfully',
            ])
            ->assertJsonPath('data.nama', 'Tunjangan Jabatan')
            ->assertJsonPath('data.kebutuhan.nama', 'Kebersihan')
            ->assertJsonPath('data.position.name', 'Cleaning Service');
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/tunjangan/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_add_creates_and_returns_201(): void
    {
        $this->seedKebutuhan();
        $this->seedPosition();

        $response = $this->postJson('/api/tunjangan/add', [
            'nama' => 'Tunjangan Jabatan',
            'nominal' => 1000000,
            'kebutuhan_id' => 1,
            'position_id' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tunjangan: Tunjangan Jabatan berhasil disimpan',
            ])
            ->assertJsonPath('data.nama', 'Tunjangan Jabatan');

        $this->assertDatabaseHas('m_tunjangan_posisi', ['nama' => 'Tunjangan Jabatan']);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/tunjangan/add', [
            'nama' => '',
            'nominal' => 'abc',
        ]);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'nominal', 'kebutuhan_id', 'position_id']]);
    }

    public function test_update_found_returns_success(): void
    {
        $this->seedKebutuhan();
        $this->seedPosition();
        $row = $this->seedTunjangan(['nama' => 'Lama']);

        $response = $this->putJson("/api/tunjangan/update/{$row->id}", [
            'nama' => 'Baru',
            'nominal' => 2000000,
            'kebutuhan_id' => 1,
            'position_id' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Tunjangan: Baru berhasil diupdate',
            ])
            ->assertJsonPath('data.nama', 'Baru');

        $this->assertDatabaseHas('m_tunjangan_posisi', ['id' => $row->id, 'nama' => 'Baru']);
    }

    public function test_update_not_found_returns_404(): void
    {
        $this->seedKebutuhan();
        $this->seedPosition();

        $response = $this->putJson('/api/tunjangan/update/999', [
            'nama' => 'X',
            'nominal' => 1000,
            'kebutuhan_id' => 1,
            'position_id' => 1,
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $this->seedKebutuhan();
        $this->seedPosition();
        $row = $this->seedTunjangan();

        $response = $this->deleteJson("/api/tunjangan/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Berhasil menghapus data',
            ]);

        $this->assertSoftDeleted('m_tunjangan_posisi', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/tunjangan/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_tunjangan_posisi');
        Schema::dropIfExists('m_kebutuhan');
        Schema::dropIfExists('m_position');
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

        Schema::create('m_position', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('m_tunjangan_posisi', function (Blueprint $table) {
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
    }
}
