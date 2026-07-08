<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\JenisPerusahaan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of
 * JenisPerusahaanController while refactoring it onto the ApiResponser trait
 * + a BaseRequest FormRequest. The success envelope (keys present, status
 * codes) stays identical; the 422 shape follows the BaseRequest contract
 * ({ message: { field: [..] } }).
 */
class JenisPerusahaanControllerTest extends TestCase
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
        JenisPerusahaan::query()->create(['nama' => 'PT', 'resiko' => 'Rendah']);
        JenisPerusahaan::query()->create(['nama' => 'CV', 'resiko' => 'Tinggi']);

        $response = $this->getJson('/api/jenis-perusahaan/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = JenisPerusahaan::query()->create(['nama' => 'Firma', 'resiko' => 'Sedang']);

        $response = $this->getJson("/api/jenis-perusahaan/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama', 'Firma')
            ->assertJsonPath('data.resiko', 'Sedang');
        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404(): void
    {
        // Current impl uses findOrFail() -> ModelNotFoundException -> 404.
        $response = $this->getJson('/api/jenis-perusahaan/view/999');

        $response->assertStatus(404);
    }

    public function test_save_creates_and_returns_200(): void
    {
        $response = $this->postJson('/api/jenis-perusahaan/save', [
            'nama' => 'Koperasi',
            'resiko' => 'Rendah',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil ditambahkan',
            ])
            ->assertJsonPath('data.nama', 'Koperasi')
            ->assertJsonPath('data.resiko', 'Rendah');

        $this->assertDatabaseHas('m_jenis_perusahaan', ['nama' => 'Koperasi', 'resiko' => 'Rendah']);
    }

    public function test_save_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/jenis-perusahaan/save', ['nama' => '']);

        // BaseRequest shape: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'resiko']]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = JenisPerusahaan::query()->create(['nama' => 'Lama', 'resiko' => 'Rendah']);

        $response = $this->putJson("/api/jenis-perusahaan/update/{$row->id}", [
            'nama' => 'Baru',
            'resiko' => 'Tinggi',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil diperbarui',
            ])
            ->assertJsonPath('data.nama', 'Baru')
            ->assertJsonPath('data.resiko', 'Tinggi');
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/jenis-perusahaan/update/999', [
            'nama' => 'X',
            'resiko' => 'Y',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = JenisPerusahaan::query()->create(['nama' => 'Hapus', 'resiko' => 'Rendah']);

        $response = $this->deleteJson("/api/jenis-perusahaan/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Berhasil dihapus',
            ]);

        $this->assertSoftDeleted('m_jenis_perusahaan', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/jenis-perusahaan/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_jenis_perusahaan');
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

        Schema::create('m_jenis_perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('resiko')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
