<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the current JSON response shapes of
 * UpahController BEFORE/DURING refactor onto the ApiResponser trait +
 * FormRequests. Success payloads (keys, data, status) are asserted tightly;
 * error envelopes are asserted partially so the shape may normalise to the
 * trait's standard error envelope without breaking the lock.
 */
class UpahControllerTest extends TestCase
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

    // ── Level 1: listProvinsi ────────────────────────────────────────────────

    public function test_list_provinsi_returns_success_pagination_envelope(): void
    {
        DB::table('m_province')->insert(['id' => 35, 'name' => 'Jawa Timur', 'is_active' => 1]);
        DB::table('m_ump')->insert([
            'id' => 1, 'province_id' => 35, 'province_name' => 'Jawa Timur',
            'ump' => 2000000, 'tgl_berlaku' => '2024-01-01', 'sumber' => 'https://jatim.go.id',
            'is_aktif' => 1, 'created_by' => 'Admin', 'created_by_user_id' => 1, 'updated_by' => 'Admin',
        ]);

        $response = $this->getJson('/api/upah/provinsi');

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Data provinsi berhasil diambil'])
            ->assertJsonPath('data.0.nama', 'Jawa Timur')
            ->assertJsonPath('data.0.ump.nilai', 2000000)
            ->assertJsonPath('pagination.total', 1);
    }

    // ── Level 2: getProvinceDetail ───────────────────────────────────────────

    public function test_province_detail_cities_returns_success(): void
    {
        DB::table('m_province')->insert(['id' => 35, 'name' => 'Jawa Timur', 'is_active' => 1]);
        DB::table('m_city')->insert(['id' => 3578, 'province_id' => 35, 'name' => 'Kota Surabaya', 'kode' => '3578', 'is_active' => 1]);

        $response = $this->getJson('/api/upah/provinsi/35?type=cities');

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Data kota/kabupaten berhasil diambil'])
            ->assertJsonPath('data.0.nama', 'Kota Surabaya')
            ->assertJsonStructure(['success', 'data', 'umpdata', 'pagination', 'message']);
    }

    public function test_province_detail_invalid_type_returns_400(): void
    {
        DB::table('m_province')->insert(['id' => 35, 'name' => 'Jawa Timur', 'is_active' => 1]);

        $response = $this->getJson('/api/upah/provinsi/35?type=bogus');

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_province_detail_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/upah/provinsi/999?type=cities');

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // ── Level 3: detailKota ──────────────────────────────────────────────────

    public function test_detail_kota_returns_success_data(): void
    {
        DB::table('m_province')->insert(['id' => 35, 'name' => 'Jawa Timur', 'is_active' => 1]);
        DB::table('m_city')->insert(['id' => 3578, 'province_id' => 35, 'name' => 'Kota Surabaya', 'kode' => '3578', 'is_active' => 1]);

        $response = $this->getJson('/api/upah/kota/3578');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.kota.id', 3578)
            ->assertJsonPath('data.kota.nama', 'Kota Surabaya');
    }

    public function test_detail_kota_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/upah/kota/999');

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // ── showUmsp / showUmsk ──────────────────────────────────────────────────

    public function test_show_umsp_returns_success(): void
    {
        DB::table('m_umsp')->insert([
            'id' => 10, 'province_id' => 35, 'province_name' => 'Jawa Timur', 'sektor' => 'Tekstil',
            'umsp' => 2350000, 'tgl_berlaku' => '2024-01-01', 'sumber' => 'https://jatim.go.id',
            'is_aktif' => 1, 'created_by' => 'Admin', 'updated_by' => 'Admin',
        ]);

        $response = $this->getJson('/api/upah/umsp/10');

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Data UMSP berhasil diambil'])
            ->assertJsonPath('data.id', 10)
            ->assertJsonPath('data.sektor', 'Tekstil');
    }

    public function test_show_umsp_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/upah/umsp/999');

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_show_umsk_returns_success(): void
    {
        DB::table('m_umsk')->insert([
            'id' => 5, 'city_id' => 3578, 'city_name' => 'Kota Surabaya', 'sektor' => 'Otomotif',
            'umsk' => 4900000, 'tgl_berlaku' => '2024-01-01', 'sumber' => 'https://jatim.go.id',
            'is_aktif' => 1, 'created_by' => 'Admin', 'updated_by' => 'Admin',
        ]);

        $response = $this->getJson('/api/upah/umsk/5');

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Data UMSK berhasil diambil'])
            ->assertJsonPath('data.id', 5)
            ->assertJsonPath('data.sektor', 'Otomotif');
    }

    public function test_show_umsk_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/upah/umsk/999');

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // ── store* (201 + validation) ────────────────────────────────────────────

    public function test_store_ump_returns_201(): void
    {
        $response = $this->postJson('/api/upah/ump', [
            'province_id' => 35, 'province_name' => 'Jawa Timur', 'ump' => 2000000,
            'tgl_berlaku' => '2024-01-01', 'sumber' => 'https://jatim.go.id/ump2024',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Data UMP berhasil ditambahkan'])
            ->assertJsonPath('data.province_id', 35);

        $this->assertDatabaseHas('m_ump', ['province_id' => 35, 'is_aktif' => 1]);
    }

    public function test_store_ump_validation_returns_422(): void
    {
        $response = $this->postJson('/api/upah/ump', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['province_id']]);
    }

    public function test_store_umsp_returns_201(): void
    {
        $response = $this->postJson('/api/upah/umsp', [
            'province_id' => 35, 'province_name' => 'Jawa Timur', 'sektor' => 'Tekstil', 'umsp' => 2350000,
            'tgl_berlaku' => '2024-01-01', 'sumber' => 'https://jatim.go.id/umsp2024',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Data UMSP berhasil ditambahkan'])
            ->assertJsonPath('data.sektor', 'Tekstil');

        $this->assertDatabaseHas('m_umsp', ['province_id' => 35, 'sektor' => 'Tekstil']);
    }

    public function test_store_umk_returns_201(): void
    {
        $response = $this->postJson('/api/upah/umk', [
            'city_id' => 3578, 'city_name' => 'Kota Surabaya', 'umk' => 4725479,
            'tgl_berlaku' => '2024-01-01', 'sumber' => 'https://jatim.go.id/umk2024',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Data UMK berhasil ditambahkan'])
            ->assertJsonPath('data.city_id', 3578);

        $this->assertDatabaseHas('m_umk', ['city_id' => 3578]);
    }

    public function test_store_umsk_returns_201(): void
    {
        $response = $this->postJson('/api/upah/umsk', [
            'city_id' => 3578, 'city_name' => 'Kota Surabaya', 'sektor' => 'Otomotif', 'umsk' => 4900000,
            'tgl_berlaku' => '2024-01-01', 'sumber' => 'https://jatim.go.id/umsk2024',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Data UMSK berhasil ditambahkan'])
            ->assertJsonPath('data.sektor', 'Otomotif');

        $this->assertDatabaseHas('m_umsk', ['city_id' => 3578, 'sektor' => 'Otomotif']);
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'm_province', 'm_city', 'm_ump', 'm_umsp', 'm_umk', 'm_umsk'] as $t) {
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

        Schema::create('m_province', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_city', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id')->nullable();
            $table->string('name')->nullable();
            $table->string('kode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_ump', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id')->nullable();
            $table->string('province_name')->nullable();
            $table->decimal('ump', 15, 2)->default(0);
            $table->date('tgl_berlaku')->nullable();
            $table->string('sumber')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_umsp', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id')->nullable();
            $table->string('province_name')->nullable();
            $table->string('sektor')->nullable();
            $table->decimal('umsp', 15, 2)->default(0);
            $table->date('tgl_berlaku')->nullable();
            $table->string('sumber')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_umk', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->decimal('umk', 15, 2)->default(0);
            $table->date('tgl_berlaku')->nullable();
            $table->string('sumber')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_umsk', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->string('sektor')->nullable();
            $table->decimal('umsk', 15, 2)->default(0);
            $table->date('tgl_berlaku')->nullable();
            $table->string('sumber')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
