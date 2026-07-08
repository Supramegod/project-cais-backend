<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Top;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of TopController
 * (Terms of Payment) before/after refactoring it onto the ApiResponser
 * trait + FormRequest. The success envelope and status codes stay identical.
 */
class TopControllerTest extends TestCase
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
        Top::query()->create(['nama' => 'TOP 30', 'persentase' => 30]);
        Top::query()->create(['nama' => 'TOP 60', 'persentase' => 60]);

        $response = $this->getJson('/api/top/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = Top::query()->create(['nama' => 'TOP 30', 'persentase' => 30]);

        $response = $this->getJson("/api/top/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama', 'TOP 30');
        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/top/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'TOP tidak ditemukan',
            ]);
    }

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/top/add', ['nama' => 'TOP 90', 'persentase' => 90]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'TOP berhasil dibuat',
            ])
            ->assertJsonPath('data.nama', 'TOP 90');

        $this->assertDatabaseHas('m_top', ['nama' => 'TOP 90']);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/top/add', ['nama' => '', 'persentase' => 'abc']);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'persentase']]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = Top::query()->create(['nama' => 'Lama', 'persentase' => 10]);

        $response = $this->putJson("/api/top/update/{$row->id}", ['nama' => 'Baru', 'persentase' => 20]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'TOP berhasil diupdate',
            ])
            ->assertJsonPath('data.nama', 'Baru');
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/top/update/999', ['nama' => 'X', 'persentase' => 5]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'TOP tidak ditemukan',
            ]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = Top::query()->create(['nama' => 'Hapus', 'persentase' => 5]);

        $response = $this->deleteJson("/api/top/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'TOP berhasil dihapus',
            ]);

        $this->assertSoftDeleted('m_top', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/top/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'TOP tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_top');
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

        Schema::create('m_top', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->decimal('persentase', 8, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
