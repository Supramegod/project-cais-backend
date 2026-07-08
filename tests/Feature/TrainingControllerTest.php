<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Training;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the exact JSON response shape of
 * TrainingController BEFORE refactoring it onto the ApiResponser trait +
 * FormRequest. The success/data/status envelope must stay identical; the
 * 422 validation shape adopts the approved BaseRequest contract
 * ({ message: { field: [..] } }).
 */
class TrainingControllerTest extends TestCase
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
        Training::query()->create(['nama' => 'Training A', 'jenis' => 'Programming', 'jp' => 8, 'menit' => 60, 'total' => 480]);
        Training::query()->create(['nama' => 'Training B', 'jenis' => 'Design', 'jp' => 4, 'menit' => 30, 'total' => 120]);

        $response = $this->getJson('/api/training/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data retrieved successfully',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = Training::query()->create(['nama' => 'Training A', 'jenis' => 'Programming', 'jp' => 8, 'menit' => 60, 'total' => 480]);

        $response = $this->getJson("/api/training/view/{$row->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data retrieved successfully',
            ])
            ->assertJsonPath('data.nama', 'Training A')
            ->assertJsonPath('data.total', 480);
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/training/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Training not found',
            ]);
    }

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/training/add', [
            'nama' => 'Training Laravel',
            'jenis' => 'Programming',
            'jp' => 8,
            'menit' => 60,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Training created successfully',
            ])
            ->assertJsonPath('data.nama', 'Training Laravel')
            ->assertJsonPath('data.total', 480);

        $this->assertDatabaseHas('m_training', ['nama' => 'Training Laravel', 'total' => 480]);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/training/add', ['nama' => '']);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'jenis', 'jp', 'menit']]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = Training::query()->create(['nama' => 'Lama', 'jenis' => 'Programming', 'jp' => 8, 'menit' => 60, 'total' => 480]);

        $response = $this->putJson("/api/training/update/{$row->id}", [
            'nama' => 'Baru',
            'jenis' => 'Advanced',
            'jp' => 12,
            'menit' => 45,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Training updated successfully',
            ])
            ->assertJsonPath('data.nama', 'Baru')
            ->assertJsonPath('data.total', 540);
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/training/update/999', [
            'nama' => 'X',
            'jenis' => 'Y',
            'jp' => 1,
            'menit' => 1,
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Training not found',
            ]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = Training::query()->create(['nama' => 'Hapus', 'jenis' => 'Programming', 'jp' => 8, 'menit' => 60, 'total' => 480]);

        $response = $this->deleteJson("/api/training/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Training deleted successfully',
            ]);

        $this->assertSoftDeleted('m_training', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/training/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Training not found',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_training');
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

        Schema::create('m_training', function (Blueprint $table) {
            $table->increments('id');
            $table->string('jenis')->nullable();
            $table->string('nama')->nullable();
            $table->integer('jp')->nullable();
            $table->integer('menit')->nullable();
            $table->integer('total')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
