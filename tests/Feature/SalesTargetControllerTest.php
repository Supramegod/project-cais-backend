<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the intended (post-refactor) JSON response
 * shape of SalesTargetController once it adopts the ApiResponser trait.
 * Also guards that the class loads at all (it previously fataled because a
 * private errorResponse() narrowed the inherited protected trait method).
 */
class SalesTargetControllerTest extends TestCase
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

    public function test_controller_class_loads(): void
    {
        // Guards the original fatal: a private errorResponse() narrowing the
        // protected ApiResponser::errorResponse() made the class un-loadable.
        $this->assertTrue(class_exists(\App\Http\Controllers\SalesTargetController::class));
    }

    public function test_index_returns_success_data_envelope(): void
    {
        $this->seedTarget(['year' => 2025]);
        $this->seedTarget(['year' => 2024]);

        $response = $this->getJson('/api/sales-target');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data.data');
    }

    public function test_store_creates_and_returns_201(): void
    {
        $payload = [
            'type' => 'company',
            'period_type' => 'yearly',
            'year' => 2025,
            'target_amount' => 50000000,
        ];

        $response = $this->postJson('/api/sales-target', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Target berhasil dibuat.',
            ])
            ->assertJsonPath('data.type', 'company')
            ->assertJsonPath('data.target_amount_formatted', 'Rp 50.000.000');

        $this->assertDatabaseHas('sl_sales_targets', ['type' => 'company', 'year' => 2025]);
    }

    public function test_store_duplicate_returns_409(): void
    {
        $this->seedTarget([
            'type' => 'company',
            'period_type' => 'yearly',
            'year' => 2025,
        ]);

        $response = $this->postJson('/api/sales-target', [
            'type' => 'company',
            'period_type' => 'yearly',
            'year' => 2025,
            'target_amount' => 12345,
        ]);

        $response->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Target untuk periode & tipe yang sama sudah ada.',
            ]);
    }

    public function test_store_validation_error_returns_422_baserequest_shape(): void
    {
        $response = $this->postJson('/api/sales-target', []);

        // BaseRequest 422 shape: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['type', 'period_type', 'year', 'target_amount']]);
    }

    public function test_show_found_returns_success_data(): void
    {
        $target = $this->seedTarget(['type' => 'company', 'period_type' => 'yearly', 'year' => 2025]);

        $response = $this->getJson("/api/sales-target/{$target->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $target->id);
        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_show_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/sales-target/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Target tidak ditemukan.',
            ]);
    }

    public function test_update_found_returns_success(): void
    {
        $target = $this->seedTarget(['type' => 'company', 'period_type' => 'yearly', 'year' => 2025, 'target_amount' => 1000]);

        $response = $this->putJson("/api/sales-target/{$target->id}", ['target_amount' => 75000000]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Target berhasil diupdate.',
            ])
            ->assertJsonPath('data.target_amount', 75000000);
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/sales-target/999', ['target_amount' => 100]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Target tidak ditemukan.',
            ]);
    }

    public function test_destroy_found_returns_message_only(): void
    {
        $target = $this->seedTarget(['type' => 'company', 'period_type' => 'yearly', 'year' => 2025]);

        $response = $this->deleteJson("/api/sales-target/{$target->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Target berhasil dihapus.',
            ]);
    }

    public function test_destroy_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/sales-target/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Target tidak ditemukan.',
            ]);
    }

    private function seedTarget(array $overrides = []): SalesTarget
    {
        return SalesTarget::query()->create(array_merge([
            'type' => 'company',
            'period_type' => 'yearly',
            'year' => 2025,
            'month' => null,
            'user_id' => null,
            'branch_id' => null,
            'target_amount' => 1000000,
        ], $overrides));
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('sl_sales_targets');
        Schema::dropIfExists('m_branch');
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

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('sl_sales_targets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('type');
            $table->string('period_type');
            $table->integer('year');
            $table->integer('month')->nullable();
            $table->decimal('target_amount', 20, 2)->default(0);
            $table->timestamps();
        });
    }
}
