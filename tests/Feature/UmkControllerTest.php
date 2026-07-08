<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Umk;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the exact JSON response shape of
 * UmkController BEFORE refactoring it onto the ApiResponser trait.
 * The envelope (keys present, status codes, success/data payload) must stay
 * identical; only the validation-422 shape moves to the BaseRequest standard.
 */
class UmkControllerTest extends TestCase
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

    private function seedUmk(array $overrides = []): Umk
    {
        return Umk::query()->create(array_merge([
            'city_id' => 1,
            'city_name' => 'Kota Bandung',
            'umk' => 3500000,
            'tgl_berlaku' => '2024-01-01',
            'sumber' => 'https://example.com/sumber',
            'is_aktif' => 1,
        ], $overrides));
    }

    public function test_list_returns_success_data_envelope(): void
    {
        $this->seedUmk(['city_id' => 1]);
        $this->seedUmk(['city_id' => 2, 'is_aktif' => 0]);

        $response = $this->getJson('/api/umk/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            // getActive() only returns is_aktif = 1
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city_id', 1);

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $this->seedUmk(['city_id' => 7, 'city_name' => 'Kota Depok']);

        $response = $this->getJson('/api/umk/view/7');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.city_id', 7)
            ->assertJsonPath('data.city_name', 'Kota Depok');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/umk/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data UMK tidak ditemukan',
            ]);
    }

    public function test_list_by_city_returns_success_data(): void
    {
        $this->seedUmk(['city_id' => 5]);
        $this->seedUmk(['city_id' => 5, 'is_aktif' => 0]);
        $this->seedUmk(['city_id' => 6]);

        $response = $this->getJson('/api/umk/city/5');

        $response->assertOk()
            ->assertJson(['success' => true])
            // getByCity returns all (active + inactive) non-deleted for the city
            ->assertJsonCount(2, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_add_creates_and_returns_success(): void
    {
        // Existing active row for the same city should be deactivated
        $old = $this->seedUmk(['city_id' => 9, 'is_aktif' => 1]);

        $response = $this->postJson('/api/umk/add', [
            'city_id' => 9,
            'city_name' => 'Kota Baru',
            'umk' => 4000000,
            'tgl_berlaku' => '2025-01-01',
            'sumber' => 'https://example.com/baru',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data UMK berhasil ditambahkan',
            ])
            ->assertJsonPath('data.city_name', 'Kota Baru');

        $this->assertDatabaseHas('m_umk', ['city_name' => 'Kota Baru', 'is_aktif' => 1]);
        $this->assertDatabaseHas('m_umk', ['id' => $old->id, 'is_aktif' => 0]);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/umk/add', [
            'city_name' => '',
            'umk' => 'abc',
        ]);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['city_id', 'city_name', 'umk', 'tgl_berlaku', 'sumber']]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_umk');
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

        Schema::create('m_umk', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->decimal('umk', 15, 2)->nullable();
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
