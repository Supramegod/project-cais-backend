<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\BentukUsaha;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the exact JSON response shape of
 * BentukUsahaController BEFORE refactoring it onto the ApiResponser trait.
 * The envelope (keys present, status codes) must stay identical.
 */
class BentukUsahaControllerTest extends TestCase
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
        BentukUsaha::query()->create(['nama' => 'PT']);
        BentukUsaha::query()->create(['nama' => 'CV']);

        $response = $this->getJson('/api/bentuk-usaha/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data')
            // 'nama' ordered ascending → CV before PT
            ->assertJsonPath('data.0.nama', 'CV')
            ->assertJsonPath('data.1.nama', 'PT');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = BentukUsaha::query()->create(['nama' => 'Firma']);

        $response = $this->getJson("/api/bentuk-usaha/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama', 'Firma');
        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/bentuk-usaha/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_save_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/bentuk-usaha/save', ['nama' => 'Koperasi']);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil ditambahkan',
            ])
            ->assertJsonPath('data.nama', 'Koperasi');

        $this->assertDatabaseHas('m_bentuk_usaha', ['nama' => 'Koperasi']);
    }

    public function test_save_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/bentuk-usaha/save', ['nama' => '']);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama']]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = BentukUsaha::query()->create(['nama' => 'Lama']);

        $response = $this->putJson("/api/bentuk-usaha/update/{$row->id}", ['nama' => 'Baru']);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Berhasil diperbarui',
            ])
            ->assertJsonPath('data.nama', 'Baru');
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/bentuk-usaha/update/999', ['nama' => 'X']);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = BentukUsaha::query()->create(['nama' => 'Hapus']);

        $response = $this->deleteJson("/api/bentuk-usaha/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Berhasil dihapus',
            ]);

        $this->assertSoftDeleted('m_bentuk_usaha', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/bentuk-usaha/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_bentuk_usaha');
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

        Schema::create('m_bentuk_usaha', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
