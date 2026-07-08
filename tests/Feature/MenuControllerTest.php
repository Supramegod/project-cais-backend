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
 * Characterization test: locks the exact JSON response shapes of
 * MenuController (menu list/add/view/update/delete + group list/add/assign)
 * around the refactor onto the ApiResponser trait + FormRequests.
 *
 * The success envelope (keys present, status codes, data shape) stays
 * byte-identical; only the 422 validation envelope moves to the app-standard
 * BaseRequest shape `{ message: { field: [..] } }`.
 */
class MenuControllerTest extends TestCase
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

    private function seedMenu(array $overrides = []): int
    {
        return DB::table('sysmenu')->insertGetId(array_merge([
            'nama' => 'Menu',
            'parent_id' => null,
            'url' => '/menu',
            'icon' => null,
            'status' => null,
            'group_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ---------------------------------------------------------------- list

    public function test_list_returns_success_tree(): void
    {
        $parentId = $this->seedMenu(['nama' => 'Parent', 'url' => '/parent']);
        $this->seedMenu(['nama' => 'Child', 'url' => '/child', 'parent_id' => $parentId]);

        $response = $this->getJson('/api/menu/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Parent')
            ->assertJsonPath('data.0.children.0.nama', 'Child');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    // ---------------------------------------------------------------- view

    public function test_view_found_returns_success_data(): void
    {
        $id = $this->seedMenu(['nama' => 'ViewMe']);

        $response = $this->getJson("/api/menu/view/{$id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.nama', 'ViewMe');
        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/menu/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Menu not found',
            ]);
    }

    // ---------------------------------------------------------------- add

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/menu/add', [
            'nama' => 'Dashboard',
            'url' => '/dashboard',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data Berhasil Disimpan',
            ])
            ->assertJsonPath('data.nama', 'Dashboard');

        $this->assertDatabaseHas('sysmenu', ['nama' => 'Dashboard', 'url' => '/dashboard']);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/menu/add', ['nama' => '']);

        // BaseRequest shape: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'url']]);
    }

    // ---------------------------------------------------------------- update

    public function test_update_found_returns_success(): void
    {
        $id = $this->seedMenu(['nama' => 'Old', 'url' => '/old']);

        $response = $this->putJson("/api/menu/update/{$id}", [
            'nama' => 'New',
            'url' => '/new',
        ]);

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Data Berhasil Disimpan',
            ]);

        $this->assertDatabaseHas('sysmenu', ['id' => $id, 'nama' => 'New']);
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/menu/update/999', [
            'nama' => 'X',
            'url' => '/x',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Menu not found',
            ]);
    }

    public function test_update_validation_error_returns_422(): void
    {
        $id = $this->seedMenu();

        $response = $this->putJson("/api/menu/update/{$id}", ['nama' => '']);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'url']]);
    }

    // ---------------------------------------------------------------- delete

    public function test_delete_found_returns_message_only(): void
    {
        $id = $this->seedMenu(['nama' => 'Hapus']);

        $response = $this->deleteJson("/api/menu/delete/{$id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Data Berhasil Dihapus',
            ]);

        $this->assertSoftDeleted('sysmenu', ['id' => $id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/menu/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Menu not found',
            ]);
    }

    // ---------------------------------------------------------------- group/list

    public function test_group_list_returns_success_with_menus(): void
    {
        $groupId = DB::table('sysmenu_group')->insertGetId([
            'nama' => 'Master Data',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedMenu(['nama' => 'M1', 'url' => '/m1', 'group_id' => $groupId]);

        $response = $this->getJson('/api/menu/group/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Master Data')
            ->assertJsonPath('data.0.total_menu', 1)
            ->assertJsonPath('data.0.menus.0.nama', 'M1');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    // ---------------------------------------------------------------- group/add

    public function test_group_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/menu/group/add', ['nama' => 'Laporan']);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Grup Berhasil Dibuat',
            ])
            ->assertJsonPath('data.nama', 'Laporan');

        $this->assertDatabaseHas('sysmenu_group', ['nama' => 'Laporan']);
    }

    public function test_group_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/menu/group/add', ['nama' => '']);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama']]);
    }

    // ---------------------------------------------------------------- group/assign

    public function test_group_assign_moves_menus_and_returns_success(): void
    {
        $groupId = DB::table('sysmenu_group')->insertGetId([
            'nama' => 'Target',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $m1 = $this->seedMenu(['nama' => 'A', 'url' => '/a']);
        $m2 = $this->seedMenu(['nama' => 'B', 'url' => '/b']);

        $response = $this->postJson('/api/menu/group/assign', [
            'group_id' => $groupId,
            'menu_ids' => [$m1, $m2],
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.group_id', $groupId)
            ->assertJsonPath('data.group_nama', 'Target')
            ->assertJsonPath('data.assigned_count', 2)
            ->assertJsonPath('data.not_found_ids', []);

        $this->assertDatabaseHas('sysmenu', ['id' => $m1, 'group_id' => $groupId]);
        $this->assertDatabaseHas('sysmenu', ['id' => $m2, 'group_id' => $groupId]);
    }

    public function test_group_assign_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/menu/group/assign', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['group_id', 'menu_ids']]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('sysmenu');
        Schema::dropIfExists('sysmenu_group');
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

        Schema::create('sysmenu_group', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->timestamps();
        });

        Schema::create('sysmenu', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('kode')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
