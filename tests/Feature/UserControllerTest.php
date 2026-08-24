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

class UserControllerTest extends TestCase
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

        $this->seedRole(2, 'Admin');
        $this->seedBranch(1, 'Pusat');

        $this->seedUser(1, 'tester', 'Tester', 2, 1);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    private function seedRole(int $id, string $name): void
    {
        DB::connection('mysqlhris')->table('m_role')->insert([
            'id' => $id,
            'name' => $name,
            'is_active' => 1,
        ]);
    }

    private function seedBranch(int $id, string $name): void
    {
        DB::connection('mysqlhris')->table('m_branch')->insert([
            'id' => $id,
            'name' => $name,
            'is_active' => 1,
        ]);
    }

    private function seedUser(
        int $id,
        string $username,
        string $fullName,
        ?int $roleId = 2,
        ?int $branchId = 1,
        int $isActive = 1,
        ?string $email = null,
    ): void {
        DB::table('m_user')->insert([
            'id' => $id,
            'username' => $username,
            'password' => bcrypt('secret'),
            'full_name' => $fullName,
            'email' => $email ?? $username.'@example.com',
            'cais_role_id' => $roleId,
            'branch_id' => $branchId,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_list_hanya_mengembalikan_user_aktif_secara_default(): void
    {
        $this->seedUser(2, 'aktif', 'Andi Aktif');
        $this->seedUser(3, 'nonaktif', 'Budi Nonaktif', 2, 1, 0);

        $response = $this->getJson('/api/users/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $usernames = array_column($response->json('data'), 'username');
        $this->assertEqualsCanonicalizing(['aktif', 'tester'], $usernames);
    }

    public function test_list_is_active_nol_hanya_mengembalikan_user_nonaktif(): void
    {
        $this->seedUser(2, 'aktif', 'Andi Aktif');
        $this->seedUser(3, 'nonaktif', 'Budi Nonaktif', 2, 1, 0);

        $response = $this->getJson('/api/users/list?is_active=0');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('nonaktif', $response->json('data.0.username'));
        $this->assertFalse($response->json('data.0.is_active'));
    }

    public function test_list_is_active_all_mengembalikan_keduanya(): void
    {
        $this->seedUser(2, 'aktif', 'Andi Aktif');
        $this->seedUser(3, 'nonaktif', 'Budi Nonaktif', 2, 1, 0);

        $this->getJson('/api/users/list?is_active=all')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_payload_memuat_is_active_role_dan_branch(): void
    {
        $response = $this->getJson('/api/users/list');

        $response->assertOk()
            ->assertJsonPath('data.0.id', 1)
            ->assertJsonPath('data.0.full_name', 'Tester')
            ->assertJsonPath('data.0.username', 'tester')
            ->assertJsonPath('data.0.email', 'tester@example.com')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.0.role.id', 2)
            ->assertJsonPath('data.0.role.name', 'Admin')
            ->assertJsonPath('data.0.branch.id', 1)
            ->assertJsonPath('data.0.branch.name', 'Pusat');
    }

    public function test_list_menyertakan_meta_paginasi(): void
    {
        $this->getJson('/api/users/list?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('meta.per_page', 1);
    }

    public function test_list_filter_role_id(): void
    {
        $this->seedRole(5, 'Sales');
        $this->seedUser(2, 'sales', 'Sinta Sales', 5, 1);

        $response = $this->getJson('/api/users/list?role_id=5');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('sales', $response->json('data.0.username'));
    }

    public function test_list_filter_branch_id(): void
    {
        $this->seedBranch(2, 'Cabang');
        $this->seedUser(2, 'cabang', 'Cici Cabang', 2, 2);

        $response = $this->getJson('/api/users/list?branch_id=2');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('cabang', $response->json('data.0.username'));
    }

    public function test_search_mencocokkan_full_name_username_dan_email(): void
    {
        $this->seedUser(2, 'zulfikar', 'Zulfikar', 2, 1, 1, 'zul@example.com');
        $this->seedUser(3, 'lain', 'Orang Lain', 2, 1, 1, 'lain@example.com');

        $this->getJson('/api/users/list?search=zulfikar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'zulfikar');

        $this->getJson('/api/users/list?search=Zulfi')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/users/list?search=zul@example.com')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_search_meng_escape_wildcard_persen(): void
    {
        $this->seedUser(2, 'diskon', 'Diskon 100%', 2, 1);

        $this->getJson('/api/users/list?search=100%25')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'diskon');

        $this->getJson('/api/users/list?search=%25')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_by_role_hanya_mengembalikan_user_role_itu(): void
    {
        $this->seedRole(5, 'Sales');
        $this->seedUser(2, 'sales', 'Sinta Sales', 5, 1);
        $this->seedUser(3, 'sales2', 'Sari Sales', 5, 1);

        $response = $this->getJson('/api/users/by-role/5');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            ['sales', 'sales2'],
            array_column($response->json('data'), 'username')
        );
    }

    public function test_by_role_mengecualikan_user_nonaktif_secara_default(): void
    {
        $this->seedRole(5, 'Sales');
        $this->seedUser(2, 'aktif', 'Sinta Sales', 5, 1);
        $this->seedUser(3, 'nonaktif', 'Sari Sales', 5, 1, 0);

        $this->getJson('/api/users/by-role/5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'aktif');

        $this->getJson('/api/users/by-role/5?is_active=all')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_by_role_query_role_id_tidak_menimpa_route_param(): void
    {
        $this->seedRole(5, 'Sales');
        $this->seedUser(2, 'sales', 'Sinta Sales', 5, 1);

        $this->getJson('/api/users/by-role/5?role_id=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'sales');
    }

    public function test_by_branch_hanya_mengembalikan_user_branch_itu(): void
    {
        $this->seedBranch(2, 'Cabang');
        $this->seedUser(2, 'cabang', 'Cici Cabang', 2, 2);

        $this->getJson('/api/users/by-branch/2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'cabang');
    }

    public function test_view_mengembalikan_satu_user(): void
    {
        $this->getJson('/api/users/view/1')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.username', 'tester')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.role.name', 'Admin');
    }

    public function test_view_mengembalikan_user_nonaktif_juga(): void
    {
        $this->seedUser(2, 'nonaktif', 'Budi Nonaktif', 2, 1, 0);

        $this->getJson('/api/users/view/2')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_view_tidak_ditemukan_mengembalikan_404(): void
    {
        $this->getJson('/api/users/view/999')
            ->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'User not found',
            ]);
    }

    public function test_view_id_non_numerik_tidak_cocok_route(): void
    {
        $this->getJson('/api/users/view/abc')->assertStatus(404);
    }

    public function test_user_tanpa_role_atau_branch_mengembalikan_null(): void
    {
        $this->seedUser(2, 'yatim', 'Tanpa Relasi', null, null);

        $this->getJson('/api/users/view/2')
            ->assertOk()
            ->assertJsonPath('data.role.id', null)
            ->assertJsonPath('data.role.name', null)
            ->assertJsonPath('data.branch.id', null)
            ->assertJsonPath('data.branch.name', null);
    }

    public function test_is_active_di_luar_whitelist_ditolak(): void
    {
        $this->getJson('/api/users/list?is_active=maybe')
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['is_active']]);
    }

    public function test_per_page_di_luar_rentang_ditolak(): void
    {
        $this->getJson('/api/users/list?per_page=0')
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['per_page']]);

        $this->getJson('/api/users/list?per_page=500')
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['per_page']]);
    }

    public function test_role_id_tidak_dikenal_ditolak(): void
    {
        $this->getJson('/api/users/list?role_id=9999')
            ->assertStatus(422)
            ->assertJsonStructure(['message' => ['role_id']]);
    }

    public function test_list_tidak_menimbulkan_n_plus_1(): void
    {
        $this->seedRole(5, 'Sales');
        $this->seedBranch(2, 'Cabang');

        foreach (range(2, 11) as $id) {
            $this->seedUser($id, 'user'.$id, 'User '.$id, $id % 2 === 0 ? 2 : 5, $id % 2 === 0 ? 1 : 2);
        }

        DB::enableQueryLog();
        $this->getJson('/api/users/list?per_page=100')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, $queryCount, 'Eager loading role/branch seharusnya konstan, bukan per baris.');
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'm_role', 'm_branch'] as $table) {
            Schema::dropIfExists($table);
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

        Schema::create('m_role', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->integer('is_active')->default(1);
        });

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }
}
