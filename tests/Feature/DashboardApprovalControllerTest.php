<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\LogNotification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of the seedable
 * DashboardApprovalController endpoints (notification CRUD) BEFORE/AFTER
 * the ApiResponser + FormRequest refactor. Envelope keys and status codes
 * must stay identical; only the 422 body migrates to the BaseRequest shape.
 *
 * The heavy aggregate endpoint (getListDashboardApprovalData) is skipped:
 * it depends on the Quotation `byUserRole` scope plus several joined tables
 * (leads, status_quotation, quotation_sites, quotation_details -> wages),
 * which is out of scope for a byte-identical envelope characterization.
 */
class DashboardApprovalControllerTest extends TestCase
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
            'cais_role_id' => 96,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    private function seedNotification(array $overrides = []): LogNotification
    {
        return LogNotification::query()->create(array_merge([
            'user_id' => 1,
            'tabel' => 'sl_quotation',
            'doc_id' => 10,
            'transaksi' => 'Quotation',
            'pesan' => 'Quotation approved',
            'is_read' => false,
            'created_by' => 'Someone',
        ], $overrides));
    }

    public function test_notifications_returns_success_data_envelope(): void
    {
        $this->seedNotification(['pesan' => 'A']);
        $this->seedNotification(['pesan' => 'B', 'is_read' => true]);

        $response = $this->getJson('/api/dashboard-approval/notifications');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.limit', 10)
            ->assertJsonPath('data.offset', 0)
            ->assertJsonCount(2, 'data.notifications')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notifications' => [['id', 'tabel', 'doc_id', 'transaksi', 'pesan', 'is_read', 'created_at', 'created_by', 'time_ago']],
                    'unread_count',
                    'total',
                    'limit',
                    'offset',
                ],
            ]);
    }

    public function test_notifications_is_read_filter(): void
    {
        $this->seedNotification(['is_read' => false]);
        $this->seedNotification(['is_read' => true]);

        $response = $this->getJson('/api/dashboard-approval/notifications?is_read=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.notifications.0.is_read', true);
    }

    public function test_notifications_validation_error_returns_422(): void
    {
        // limit above max (100) -> BaseRequest 422 shape: { message: { field: [...] } }
        $response = $this->getJson('/api/dashboard-approval/notifications?limit=999');

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['limit']]);
    }

    public function test_unread_count_returns_success_data(): void
    {
        $this->seedNotification(['is_read' => false]);
        $this->seedNotification(['is_read' => false]);
        $this->seedNotification(['is_read' => true]);

        $response = $this->getJson('/api/dashboard-approval/notifications/unread-count');

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => ['unread_count' => 2],
            ]);
    }

    public function test_mark_as_read_found_returns_success(): void
    {
        $notif = $this->seedNotification(['is_read' => false]);

        $response = $this->putJson("/api/dashboard-approval/notifications/{$notif->id}/read");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => [
                    'id' => $notif->id,
                    'is_read' => true,
                ],
            ]);

        $this->assertDatabaseHas('log_notification', ['id' => $notif->id, 'is_read' => true]);
    }

    public function test_mark_as_read_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/dashboard-approval/notifications/999/read');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Notification not found',
            ]);
    }

    public function test_mark_all_as_read_returns_updated_count(): void
    {
        $this->seedNotification(['is_read' => false]);
        $this->seedNotification(['is_read' => false]);
        $this->seedNotification(['is_read' => true]);

        $response = $this->putJson('/api/dashboard-approval/notifications/read-all');

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'All notifications marked as read',
                'data' => ['updated_count' => 2],
            ]);
    }

    public function test_list_dashboard_approval_skipped(): void
    {
        $this->markTestSkipped(
            'getListDashboardApprovalData depends on the Quotation byUserRole scope and several '
            .'joined tables (leads, status_quotation, quotation_sites, quotation_details->wages); '
            .'its bespoke {counts, pending_approval_summary} shape is left as raw json and is out '
            .'of scope for this envelope characterization.'
        );
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('log_notification');
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

        Schema::create('log_notification', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('tabel')->nullable();
            $table->unsignedInteger('doc_id')->nullable();
            $table->string('transaksi')->nullable();
            $table->text('pesan')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_read')->default(false);
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
