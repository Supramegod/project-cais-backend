<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Menegakkan bahwa permission `sysmenu_role` benar-benar dicek di backend,
 * bukan hanya dipakai frontend untuk menyembunyikan tombol.
 *
 * Jalur "diizinkan" sudah tertutup SpkControllerTest (role 2 di-seed dengan
 * permission penuh). File ini fokus ke jalur penolakan.
 */
class SpkMenuPermissionTest extends TestCase
{
    private const ROLE_ID = 2;

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
            'cais_role_id' => self::ROLE_ID,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    public function test_role_tanpa_baris_permission_ditolak(): void
    {
        // Sengaja tidak menyisipkan apa pun ke sysmenu_role: fail-closed.
        $this->getJson('/api/spk/list')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki akses',
            ]);
    }

    public function test_is_view_nonaktif_menolak_pembacaan(): void
    {
        $this->grant(['is_view' => false, 'is_add' => true, 'is_edit' => true, 'is_delete' => true]);

        $this->getJson('/api/spk/list')->assertStatus(403);
        $this->getJson('/api/spk/view/1')->assertStatus(403);
        $this->getJson('/api/spk/cetak/1')->assertStatus(403);
    }

    public function test_is_add_nonaktif_menolak_pembuatan(): void
    {
        $this->grant(['is_view' => true, 'is_add' => false]);

        $this->postJson('/api/spk/add', [])->assertStatus(403);
    }

    public function test_is_edit_nonaktif_menolak_perubahan(): void
    {
        $this->grant(['is_view' => true, 'is_edit' => false]);

        $this->putJson('/api/spk/delete-site/1', [])->assertStatus(403);
        $this->postJson('/api/spk/upload/1', [])->assertStatus(403);
        $this->postJson('/api/spk/ajukan-ulang/1', [])->assertStatus(403);
        $this->postJson('/api/spk/1/submit-checklist', [])->assertStatus(403);
    }

    public function test_is_delete_nonaktif_menolak_penghapusan(): void
    {
        $this->grant(['is_view' => true, 'is_delete' => false]);

        $this->deleteJson('/api/spk/delete/1')->assertStatus(403);
    }

    public function test_permission_menu_lain_tidak_memberi_akses_ke_spk(): void
    {
        $this->grant(['is_view' => true, 'is_add' => true], 999);

        $this->getJson('/api/spk/list')->assertStatus(403);
    }

    public function test_user_tanpa_role_ditolak(): void
    {
        $this->grant(['is_view' => true]);

        DB::table('m_user')->where('id', 1)->update(['cais_role_id' => null]);
        $this->actingAs(User::query()->findOrFail(1), 'web');

        $this->getJson('/api/spk/list')->assertStatus(403);
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function grant(array $flags, ?int $sysmenuId = null): void
    {
        DB::table('sysmenu_role')->insert(array_merge([
            'sysmenu_id' => $sysmenuId ?? (int) config('menu_permissions.spk'),
            'role_id' => self::ROLE_ID,
            'is_view' => false,
            'is_add' => false,
            'is_edit' => false,
            'is_delete' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $flags));

        Cache::flush();
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'sysmenu_role', 'sl_spk', 'sl_leads', 'm_status_spk'] as $table) {
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

        Schema::create('sysmenu_role', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('sysmenu_id');
            $table->unsignedInteger('role_id');
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
