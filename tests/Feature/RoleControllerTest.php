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
 * Characterization test: locks the exact JSON response shape of
 * RoleController BEFORE refactoring it onto the ApiResponser trait +
 * FormRequest. The envelope (keys present, status codes) must stay identical.
 */
class RoleControllerTest extends TestCase
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

    private function seedRole(int $id, string $name, int $active = 1): void
    {
        DB::connection('mysqlhris')->table('m_role')->insert([
            'id' => $id,
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    private function seedMenu(int $id, string $nama, ?int $parentId = null, ?int $groupId = 1): void
    {
        DB::table('sysmenu')->insert([
            'id' => $id,
            'nama' => $nama,
            'kode' => 'K'.$id,
            'parent_id' => $parentId,
            'url' => '/menu-'.$id,
            'icon' => 'fa-x',
            'status' => 1,
            'group_id' => $groupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_returns_success_data_envelope(): void
    {
        $this->seedRole(1, 'Admin');
        $this->seedRole(2, 'User');
        $this->seedRole(3, 'Disabled', 0);

        $response = $this->getJson('/api/roles/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            // scopeActive filters out is_active = 0
            ->assertJsonCount(2, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_show_found_returns_role_with_menus(): void
    {
        $this->seedRole(2, 'Admin');
        DB::table('sysmenu_group')->insert(['id' => 1, 'nama' => 'Main', 'sort_order' => 1]);
        $this->seedMenu(1, 'Dashboard');
        DB::table('sysmenu_role')->insert([
            'id' => 1,
            'sysmenu_id' => 1,
            'role_id' => 2,
            'is_view' => 1,
            'is_add' => 0,
            'is_edit' => 0,
            'is_delete' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/roles/view/2');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', 2)
            ->assertJsonPath('data.name', 'Admin')
            ->assertJsonPath('data.menus.0.id', 1)
            ->assertJsonPath('data.menus.0.is_view', true);

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_show_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/roles/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Role not found',
            ]);
    }

    public function test_menu_permissions_returns_grouped_structure(): void
    {
        DB::table('sysmenu_group')->insert(['id' => 1, 'nama' => 'Main', 'sort_order' => 1]);
        $this->seedMenu(1, 'Dashboard');
        DB::table('sysmenu_role')->insert([
            'id' => 1,
            'sysmenu_id' => 1,
            'role_id' => 2,
            'is_view' => 1,
            'is_add' => 0,
            'is_edit' => 0,
            'is_delete' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/roles/permissions');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['ungrouped', 'grouped']]);
    }

    public function test_menu_permissions_empty_returns_empty_structure(): void
    {
        $response = $this->getJson('/api/roles/permissions');

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'ungrouped' => [],
                    'grouped' => [],
                ],
            ]);
    }

    public function test_menu_permissions_without_role_returns_403(): void
    {
        DB::table('m_user')->insert([
            'id' => 2,
            'username' => 'norole',
            'password' => bcrypt('secret'),
            'full_name' => 'No Role',
            'email' => 'norole@example.com',
            'cais_role_id' => null,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs(User::query()->findOrFail(2), 'web');

        $response = $this->getJson('/api/roles/permissions');

        $response->assertStatus(403)
            ->assertExactJson([
                'success' => false,
                'message' => 'User role not found',
            ]);
    }

    public function test_update_permissions_returns_success_message(): void
    {
        $this->seedMenu(1, 'Dashboard');

        $response = $this->postJson('/api/roles/2/update-permissions', [
            'akses' => [
                ['sysmenu_id' => 1, 'field' => 'is_view', 'value' => true],
            ],
        ]);

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Permissions updated successfully',
            ]);

        $this->assertDatabaseHas('sysmenu_role', [
            'role_id' => 2,
            'sysmenu_id' => 1,
            'is_view' => 1,
        ]);
    }

    public function test_update_permissions_validation_error_returns_422(): void
    {
        // Missing required sysmenu_id in the akses item.
        $response = $this->postJson('/api/roles/2/update-permissions', [
            'akses' => [
                ['field' => 'is_view', 'value' => true],
            ],
        ]);

        // BaseRequest shape: { message: { field: [..] } } (standar aplikasi)
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['akses.0.sysmenu_id']]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_user');
        Schema::dropIfExists('m_role');
        Schema::dropIfExists('sysmenu');
        Schema::dropIfExists('sysmenu_group');
        Schema::dropIfExists('sysmenu_role');

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

        Schema::create('sysmenu', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('kode')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->integer('status')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sysmenu_group', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->integer('sort_order')->nullable();
        });

        Schema::create('sysmenu_role', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sysmenu_id')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->boolean('is_view')->default(false);
            $table->boolean('is_add')->default(false);
            $table->boolean('is_edit')->default(false);
            $table->boolean('is_delete')->default(false);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }
}
