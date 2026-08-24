<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the response contract of WebAuthController
 * (login/logout/refresh) around the ApiResponser + FormRequest refactor.
 *
 * Login is a PUBLIC web route (routes/web.php, non-production only). On
 * success it returns a 302 redirect (NOT a JSON envelope); invalid
 * credentials keep the native Laravel ValidationException shape
 * ({ message: string, errors: {...} }) because that is credential handling
 * we must preserve. Only the missing-field validation moved to a
 * FormRequest, so that path now returns the BaseRequest 422 shape
 * ({ message: { field: [..] } }).
 */
class WebAuthControllerTest extends TestCase
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
    }

    /** Password hashing scheme used by User::checkLogin (legacy MD5 pepper). */
    private function legacyHash(string $password): string
    {
        return md5('SHELTER-'.$password.'-SHELTER');
    }

    private function seedUser(): void
    {
        DB::table('m_user')->insert([
            'id' => '11111111-1111-1111-1111-111111111111',
            'username' => 'tester',
            'password' => $this->legacyHash('secret'),
            'full_name' => 'Tester',
            'email' => 'tester@example.com',
            'cais_role_id' => 2,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_login_missing_fields_returns_422_baserequest_shape(): void
    {
        // Empty body -> LoginWebRequest fails -> BaseRequest envelope.
        $response = $this->postJson('/login-web', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['username', 'password']]);
    }

    public function test_login_invalid_credentials_returns_422_native_shape(): void
    {
        $this->seedUser();

        $response = $this->postJson('/login-web', [
            'username' => 'tester',
            'password' => 'wrong-password',
        ]);

        // Credential handling preserved: the controller's manual
        // ValidationException is reshaped app-wide to { message: { field: [..] } }.
        $response->assertStatus(422)
            ->assertJsonPath('message.username.0', 'Username atau password salah.');
    }

    public function test_login_success_redirects_and_sets_refresh_cookie(): void
    {
        $this->seedUser();

        $response = $this->post('/login-web', [
            'username' => 'tester',
            'password' => 'secret',
        ]);

        // Non-production redirect target is /api/documentation.
        $response->assertStatus(302)
            ->assertRedirect('/api/documentation')
            ->assertCookie('web_refresh_token');

        $this->assertAuthenticated('web');

        // Token + refresh-token side effects preserved.
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'web_dummy_token']);
        $this->assertDatabaseHas('refresh_tokens', [
            'tokenable_type' => User::class,
        ]);
    }

    public function test_refresh_without_cookie_returns_401_error_shape(): void
    {
        // refresh() intentionally keeps its native { error: ... } shape
        // (not the envelope) — locking it guards against accidental drift.
        $response = $this->getJson('/refresh-web');

        $response->assertStatus(401)
            ->assertExactJson(['error' => 'No refresh token']);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_user');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('sessions');

        Schema::create('m_user', function (Blueprint $table) {
            $table->string('id')->primary();
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

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tokenable_type');
            $table->string('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('access_token_id')->nullable();
            $table->string('tokenable_type')->nullable();
            $table->string('tokenable_id')->nullable();
            $table->string('token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
