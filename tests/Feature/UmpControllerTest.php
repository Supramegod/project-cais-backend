<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Ump;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the exact JSON response shape of
 * UmpController BEFORE refactoring it onto the ApiResponser trait.
 * The success/data/status shape must stay byte-identical.
 */
class UmpControllerTest extends TestCase
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

    private function seedUmp(array $overrides = []): Ump
    {
        return Ump::query()->create(array_merge([
            'province_id' => 1,
            'province_name' => 'Jawa Barat',
            'ump' => 3500000,
            'tgl_berlaku' => '2024-01-01',
            'sumber' => 'https://example.com/sumber-ump',
            'is_aktif' => 1,
            'created_by' => 'Tester',
        ], $overrides));
    }

    public function test_index_returns_active_only_success_data_envelope(): void
    {
        $this->seedUmp(['province_id' => 1, 'is_aktif' => 1]);
        $this->seedUmp(['province_id' => 2, 'is_aktif' => 0]);

        $response = $this->getJson('/api/ump/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.province_id', 1);

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $this->seedUmp(['province_id' => 5, 'province_name' => 'Bali']);

        $response = $this->getJson('/api/ump/view/5');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.province_name', 'Bali');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/ump/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data UMP tidak ditemukan',
            ]);
    }

    public function test_list_by_province_returns_success_data(): void
    {
        $this->seedUmp(['province_id' => 7]);
        $this->seedUmp(['province_id' => 7, 'is_aktif' => 0]);
        $this->seedUmp(['province_id' => 8]);

        $response = $this->getJson('/api/ump/province/7');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_add_creates_and_returns_success_message_data(): void
    {
        $this->seedUmp(['province_id' => 3, 'is_aktif' => 1]);

        $payload = [
            'province_id' => 3,
            'province_name' => 'DKI Jakarta',
            'ump' => 5000000,
            'tgl_berlaku' => '2024-01-01',
            'sumber' => 'https://example.com/dki',
        ];

        $response = $this->postJson('/api/ump/add', $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data UMP berhasil ditambahkan',
            ])
            ->assertJsonPath('data.province_name', 'DKI Jakarta');

        // new row is active
        $this->assertDatabaseHas('m_ump', ['province_name' => 'DKI Jakarta', 'is_aktif' => 1]);
        // old active row for same province deactivated
        $this->assertDatabaseHas('m_ump', ['province_id' => 3, 'province_name' => 'Jawa Barat', 'is_aktif' => 0]);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/ump/add', [
            'province_id' => '',
            'province_name' => '',
            'ump' => '',
            'tgl_berlaku' => '',
            'sumber' => '',
        ]);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message' => ['province_id', 'province_name', 'ump', 'tgl_berlaku', 'sumber'],
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_ump');
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

        Schema::create('m_ump', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id')->nullable();
            $table->string('province_name')->nullable();
            $table->decimal('ump', 15, 2)->nullable();
            $table->date('tgl_berlaku')->nullable();
            $table->string('sumber', 500)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
