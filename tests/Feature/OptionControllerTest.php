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
 * Characterization test: locks the exact JSON response shapes of a
 * representative subset of OptionController's reference/lookup endpoints
 * BEFORE (and after) refactoring it onto the ApiResponser trait.
 *
 * OptionController is ~50 read-only dropdown/lookup endpoints. We cover a
 * representative subset:
 *   - plain {success,message,data} envelopes (getPlatforms, getBidangPerusahaan,
 *     getProvinsi, getKota, getListJenisVisit)
 *   - the bespoke {success,message,data,total} envelope (listEntitas)
 *   - the bespoke 404-with-empty-data shape (getBranchesByProvince)
 *   - the only endpoint with inline validation (getUsers → 422)
 *
 * Endpoints touching heavy cross-connection joins beyond these are skipped.
 */
class OptionControllerTest extends TestCase
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

    public function test_platforms_returns_success_message_data_envelope(): void
    {
        DB::table('m_platform')->insert([
            ['nama' => 'Website', 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Referral', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/options/platforms');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data platform berhasil diambil',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_bidang_perusahaan_returns_ordered_data(): void
    {
        DB::table('m_bidang_perusahaan')->insert([
            ['nama' => 'Trading', 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Manufacturing', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/options/bidang-perusahaan');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data bidang perusahaan berhasil diambil',
            ])
            // ordered by nama asc → Manufacturing before Trading
            ->assertJsonPath('data.0.nama', 'Manufacturing')
            ->assertJsonPath('data.1.nama', 'Trading');
    }

    public function test_provinsi_returns_success_data(): void
    {
        DB::table('m_province')->insert([
            ['id' => 11, 'name' => 'ACEH', 'is_active' => 1],
            ['id' => 31, 'name' => 'DKI JAKARTA', 'is_active' => 1],
        ]);

        $response = $this->getJson('/api/options/provinsi');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data provinsi berhasil diambil',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_kota_filtered_by_province(): void
    {
        DB::table('m_city')->insert([
            ['id' => 3171, 'province_id' => 31, 'name' => 'JAKARTA PUSAT', 'is_active' => 1],
            ['id' => 1101, 'province_id' => 11, 'name' => 'KAB SIMEULUE', 'is_active' => 1],
        ]);

        $response = $this->getJson('/api/options/kota/31');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data kota berhasil diambil',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'JAKARTA PUSAT');
    }

    public function test_list_jenis_visit_returns_selected_columns(): void
    {
        DB::table('m_jenis_visit')->insert([
            ['nama' => 'Survey', 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Follow Up', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/options/list-jenis-visit');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Daftar jenis visit berhasil diambil',
            ])
            // ordered by nama asc → Follow Up before Survey
            ->assertJsonPath('data.0.nama', 'Follow Up');
    }

    public function test_list_entitas_returns_bespoke_total_envelope(): void
    {
        DB::table('m_company')->insert([
            ['id' => 1, 'name' => 'PT Alpha', 'code' => 'ALP', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'PT Beta', 'code' => 'BET', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'PT Gamma', 'code' => 'GAM', 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/options/entitas');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Company options retrieved successfully',
                'total' => 2,
            ])
            ->assertJsonCount(2, 'data')
            // bespoke shape carries a top-level `total`
            ->assertJsonPath('total', 2);
    }

    public function test_branches_by_province_found(): void
    {
        DB::table('m_city')->insert([
            ['id' => 3171, 'province_id' => 31, 'name' => 'JAKARTA PUSAT', 'is_active' => 1],
        ]);
        DB::table('m_branch')->insert([
            ['id' => 2, 'name' => 'Jakarta Pusat', 'description' => 'JKT', 'city_id' => 3171, 'is_active' => 1],
        ]);

        $response = $this->getJson('/api/options/branches/31');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Daftar branch berhasil diambil',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jakarta Pusat');
    }

    public function test_branches_by_province_not_found_returns_404_with_empty_data(): void
    {
        $response = $this->getJson('/api/options/branches/99');

        // bespoke 404: carries data => [] alongside the error envelope
        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Tidak ada branch untuk provinsi ini',
                'data' => [],
            ]);
    }

    public function test_users_validation_error_returns_422(): void
    {
        $response = $this->getJson('/api/options/users');

        $response->assertStatus(422);
    }

    public function test_users_returns_filtered_by_branch_and_role(): void
    {
        DB::table('m_user')->insert([
            ['id' => 10, 'username' => 'sales1', 'full_name' => 'Sales One', 'email' => 's1@e.com', 'cais_role_id' => 29, 'branch_id' => 2, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'username' => 'sales2', 'full_name' => 'Sales Two', 'email' => 's2@e.com', 'cais_role_id' => 99, 'branch_id' => 2, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'username' => 'sales3', 'full_name' => 'Sales Three', 'email' => 's3@e.com', 'cais_role_id' => 31, 'branch_id' => 5, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/options/users?branch_id=2');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Daftar user berhasil diambil',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 10);
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'm_platform', 'm_bidang_perusahaan', 'm_province', 'm_city', 'm_company', 'm_branch', 'm_jenis_visit'] as $t) {
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

        Schema::create('m_platform', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_bidang_perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_province', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('m_city', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id')->nullable();
            $table->string('name')->nullable();
            $table->string('kode')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('m_company', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('m_jenis_visit', function (Blueprint $table) {
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
