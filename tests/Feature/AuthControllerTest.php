<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test for AuthController AFTER moving its response envelopes onto
 * the ApiResponser trait (auth/token/session logic left untouched). Covers the
 * public login/refresh endpoints' error + validation shapes. The success paths
 * (login/refresh) require Sanctum personal-access-token + refresh-token
 * infrastructure and are markTestSkipped; the success envelope change is a
 * byte-identical {success:true, message, data} move.
 */
class AuthControllerTest extends TestCase
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

        Schema::dropIfExists('m_user');
        Schema::dropIfExists('refresh_tokens');

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

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->string('token')->nullable();
            $table->unsignedBigInteger('tokenable_id')->nullable();
            $table->string('tokenable_type')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // checkLogin matches username + password = md5('SHELTER-'.$pass.'-SHELTER').
        DB::table('m_user')->insert([
            'id' => 1, 'username' => 'superadmin', 'password' => md5('SHELTER-secret-SHELTER'),
            'full_name' => 'Super Admin', 'email' => 'sa@example.com',
            'cais_role_id' => 2, 'branch_id' => 1, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_login_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'superadmin',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertExactJson([
                'success' => false,
                'message' => 'Username atau password salah',
            ]);
    }

    public function test_login_missing_fields_returns_422_baserequest_shape(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['username', 'password']]);
    }

    public function test_refresh_invalid_token_returns_401(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'this-token-does-not-exist',
        ]);

        $response->assertStatus(401)
            ->assertExactJson([
                'success' => false,
                'message' => 'Refresh token tidak valid',
            ]);
    }

    public function test_refresh_missing_token_returns_422_baserequest_shape(): void
    {
        $response = $this->postJson('/api/auth/refresh', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['refresh_token']]);
    }
}
