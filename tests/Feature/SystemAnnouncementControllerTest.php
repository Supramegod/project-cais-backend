<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\SystemAnnouncement;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test locking the JSON response shape of
 * SystemAnnouncementController after the ApiResponser + FormRequest refactor.
 * Success envelopes (keys/status) stay byte-identical to the pre-refactor
 * controller; the 422 validation shape follows the approved BaseRequest
 * contract: { message: { field: [..] } }.
 */
class SystemAnnouncementControllerTest extends TestCase
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

    public function test_list_returns_success_data_envelope(): void
    {
        SystemAnnouncement::query()->create(['category' => 'Update', 'title' => 'A', 'release_date' => '2026-01-01']);
        SystemAnnouncement::query()->create(['category' => 'Bug Fix', 'title' => 'B', 'release_date' => '2026-02-01']);

        $response = $this->getJson('/api/system-announcements/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data')
            // ordered by release_date desc → B (Feb) first
            ->assertJsonPath('data.0.title', 'B')
            ->assertJsonPath('data.1.title', 'A');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_list_filters_by_category(): void
    {
        SystemAnnouncement::query()->create(['category' => 'Update', 'title' => 'A']);
        SystemAnnouncement::query()->create(['category' => 'Bug Fix', 'title' => 'B']);

        $response = $this->getJson('/api/system-announcements/list?category=Bug Fix');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'B');
    }

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/system-announcements/add', [
            'category' => 'Fitur Baru',
            'title' => 'Export Excel',
            'version' => 'v2.5.0',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Announcement berhasil dibuat',
            ])
            ->assertJsonPath('data.title', 'Export Excel')
            ->assertJsonPath('data.created_by', 1);

        $this->assertDatabaseHas('system_announcements', ['title' => 'Export Excel']);
    }

    public function test_add_validation_error_returns_422(): void
    {
        $response = $this->postJson('/api/system-announcements/add', [
            'category' => 'Invalid',
            'title' => '',
        ]);

        // BaseRequest shape: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['category', 'title']]);
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = SystemAnnouncement::query()->create(['category' => 'Aturan', 'title' => 'Rule']);

        $response = $this->getJson("/api/system-announcements/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.title', 'Rule');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/system-announcements/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Announcement tidak ditemukan',
            ]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = SystemAnnouncement::query()->create(['category' => 'Update', 'title' => 'Old']);

        $response = $this->putJson("/api/system-announcements/update/{$row->id}", [
            'category' => 'Update',
            'title' => 'New',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Announcement berhasil diupdate',
            ])
            ->assertJsonPath('data.title', 'New');
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/system-announcements/update/999', [
            'category' => 'Update',
            'title' => 'X',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Announcement tidak ditemukan',
            ]);
    }

    public function test_update_validation_error_returns_422(): void
    {
        $row = SystemAnnouncement::query()->create(['category' => 'Update', 'title' => 'Old']);

        $response = $this->putJson("/api/system-announcements/update/{$row->id}", [
            'category' => 'Nope',
            'title' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['category', 'title']]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = SystemAnnouncement::query()->create(['category' => 'Update', 'title' => 'Hapus']);

        $response = $this->deleteJson("/api/system-announcements/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Announcement berhasil dihapus',
            ]);

        $this->assertSoftDeleted('system_announcements', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/system-announcements/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Announcement tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('system_announcements');
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

        Schema::create('system_announcements', function (Blueprint $table) {
            $table->increments('id');
            $table->string('category')->nullable();
            $table->string('version')->nullable();
            $table->date('release_date')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
