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
 * Characterization test: locks the JSON response shapes of
 * SalesRevenueController BEFORE refactoring it onto the ApiResponser trait +
 * FormRequest validation.
 *
 * The controller endpoints are read-only revenue reports with bespoke
 * envelopes (success/data/summary/metadata) that the trait cannot reproduce,
 * so those stay raw and are locked here as-is. Only the validation envelope
 * moves from the ad-hoc 400 `{success:false,message,errors}` to the app
 * standard BaseRequest 422 `{message:{field:[..]}}`.
 *
 * Seeding note: the seeded user has cais_role_id = 2, which is NOT in the
 * sales role set [29..33]. SalesRevenueService::fetchSalesUsers() therefore
 * returns empty and every calculation short-circuits to an empty (but fully
 * shaped) result WITHOUT touching sl_pks/sl_quotation/etc. This lets us seed a
 * deterministic empty envelope using only the m_user table.
 */
class SalesRevenueControllerTest extends TestCase
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
        $this->assertTrue(class_exists(\App\Http\Controllers\SalesRevenueController::class));
    }

    public function test_monthly_revenue_returns_full_report_envelope(): void
    {
        $response = $this->getJson('/api/sales-revenue/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Monthly revenue data retrieved successfully.',
                'data' => [],
                'summary' => [
                    'total_revenue' => 0,
                    'total_revenue_formatted' => 'Rp 0',
                    'user_count' => 0,
                    'month_count' => 0,
                    'average_per_user' => 0,
                    'average_per_month' => 0,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'timestamp',
                'data',
                'summary',
                'metadata' => ['total_records', 'generated_at', 'filters_applied'],
            ]);
    }

    public function test_revenue_summary_returns_success_data_timestamp(): void
    {
        $response = $this->getJson('/api/sales-revenue/summary');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ])
            ->assertJsonStructure(['success', 'data', 'timestamp']);
    }

    public function test_kpi_returns_full_report_envelope(): void
    {
        $response = $this->getJson('/api/sales-revenue/kpi?year=2025');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'KPI data retrieved successfully.',
                'data' => [],
                'summary' => [
                    'total_actual' => 0,
                    'total_actual_formatted' => 'Rp 0',
                    'total_target_personal' => 0,
                    'total_target_formatted' => 'Rp 0',
                    'avg_achievement_personal' => null,
                    'status_breakdown' => [
                        'achieved' => 0,
                        'on_track' => 0,
                        'under_target' => 0,
                        'no_target' => 0,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'summary',
                'metadata' => ['total_records', 'generated_at', 'filters_applied'],
            ]);
    }

    /**
     * Post-refactor validation contract: inline 400 becomes the BaseRequest
     * 422 `{ message: { field: [..] } }` standard.
     */
    public function test_monthly_revenue_validation_error_returns_422(): void
    {
        $response = $this->getJson('/api/sales-revenue/list?month=13');

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['month']]);
    }

    public function test_kpi_missing_required_year_returns_422(): void
    {
        $response = $this->getJson('/api/sales-revenue/kpi');

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['year']]);
    }

    /**
     * by-month relies on MySQL-only SQL functions (DATE_FORMAT / TIMESTAMPDIFF)
     * inside calculateMonthlyRevenueOptimized(), which SQLite cannot execute,
     * so its aggregate path is not seedable in this test harness.
     */
    public function test_by_month_skipped_needs_mysql_functions(): void
    {
        $this->markTestSkipped('by-month uses MySQL DATE_FORMAT/TIMESTAMPDIFF; not runnable on SQLite test DB.');
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_user');

        Schema::create('m_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('cais_role_id')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
