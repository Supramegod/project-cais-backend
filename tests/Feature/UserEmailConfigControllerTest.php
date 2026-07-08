<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use App\Models\UserEmailConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shapes of
 * UserEmailConfigController while it is refactored onto the ApiResponser
 * trait + FormRequest. Success/data/status envelopes stay byte-identical to
 * the legacy controller; validation errors move to the BaseRequest 422 shape
 * ({ message: { field: [..] } }) as a deliberate consequence of adopting the
 * FormRequest.
 */
class UserEmailConfigControllerTest extends TestCase
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

    public function test_get_config_without_config_returns_null_data_with_message(): void
    {
        $response = $this->getJson('/api/user/list');

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => null,
                'message' => 'No email configuration found',
            ]);
    }

    public function test_get_config_with_config_returns_success_data_without_password(): void
    {
        UserEmailConfig::query()->create([
            'user_id' => 1,
            'email_host' => 'smtp.gmail.com',
            'email_port' => 587,
            'email_username' => 'tester@example.com',
            'email_password' => 'supersecret',
            'email_encryption' => 'tls',
            'email_from_address' => 'tester@example.com',
            'email_from_name' => 'Tester',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/user/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.email_host', 'smtp.gmail.com')
            ->assertJsonPath('data.email_username', 'tester@example.com');

        // Secret must never leak in the response.
        $this->assertArrayNotHasKey('email_password', $response->json('data'));
        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_save_config_creates_and_returns_success_without_password(): void
    {
        $response = $this->postJson('/api/user/add', [
            'email_host' => 'smtp.gmail.com',
            'email_port' => 587,
            'email_username' => 'tester@example.com',
            'email_password' => 'supersecret',
            'email_encryption' => 'tls',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Email configuration saved successfully',
            ])
            ->assertJsonPath('data.email_host', 'smtp.gmail.com')
            ->assertJsonPath('data.user_id', 1);

        $this->assertArrayNotHasKey('email_password', $response->json('data'));
        $this->assertDatabaseHas('sl_user_email_configs', [
            'user_id' => 1,
            'email_host' => 'smtp.gmail.com',
        ]);
        // Password stored encrypted, never as plaintext.
        $stored = DB::table('sl_user_email_configs')->where('user_id', 1)->value('email_password');
        $this->assertNotSame('supersecret', $stored);
    }

    public function test_save_config_updates_existing_row(): void
    {
        UserEmailConfig::query()->create([
            'user_id' => 1,
            'email_host' => 'old.host.com',
            'email_port' => 25,
            'email_username' => 'tester@example.com',
            'email_password' => 'oldpass',
            'email_encryption' => 'ssl',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/user/add', [
            'email_host' => 'new.host.com',
            'email_port' => 465,
            'email_username' => 'tester@example.com',
            'email_password' => 'newpass',
            'email_encryption' => 'ssl',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email_host', 'new.host.com');

        $this->assertSame(1, DB::table('sl_user_email_configs')->where('user_id', 1)->count());
        $this->assertDatabaseHas('sl_user_email_configs', ['user_id' => 1, 'email_host' => 'new.host.com']);
    }

    public function test_save_config_validation_error_returns_422(): void
    {
        // Missing required fields -> BaseRequest 422 shape { message: { field: [..] } }
        $response = $this->postJson('/api/user/add', ['email_host' => '']);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message' => ['email_host', 'email_port', 'email_username', 'email_password'],
            ]);
    }

    public function test_test_connection_without_config_returns_400(): void
    {
        $response = $this->postJson('/api/user/test');

        $response->assertStatus(400)
            ->assertExactJson([
                'success' => false,
                'message' => 'Email configuration not found or incomplete',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('sl_user_email_configs');
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

        Schema::create('sl_user_email_configs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('email_host')->nullable();
            $table->integer('email_port')->nullable();
            $table->string('email_username')->nullable();
            $table->text('email_password')->nullable();
            $table->string('email_encryption')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('email_from_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
