<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\SystemAnnouncement;
use App\Models\SystemAnnouncementFile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of
 * SystemAnnouncementV2Controller after refactoring it onto the ApiResponser
 * trait + FormRequest. Success/data/status envelopes are byte-identical to the
 * pre-refactor controller; validation errors now follow the BaseRequest shape
 * { message: { field: [..] } }.
 */
class SystemAnnouncementV2ControllerTest extends TestCase
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

    private function makeAnnouncement(array $overrides = []): SystemAnnouncement
    {
        return SystemAnnouncement::query()->create(array_merge([
            'category'     => 'Update',
            'title'        => 'Judul',
            'version'      => '1.0',
            'release_date' => '2026-01-01',
            'description'  => 'Deskripsi',
            'details'      => '<p>detail</p>',
            'is_active'    => true,
        ], $overrides));
    }

    public function test_list_returns_success_with_paginated_data(): void
    {
        $this->makeAnnouncement(['title' => 'Satu']);
        $this->makeAnnouncement(['title' => 'Dua']);

        $response = $this->getJson('/api/v2/system-announcements/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_list_filters_by_category(): void
    {
        $this->makeAnnouncement(['category' => 'Update']);
        $this->makeAnnouncement(['category' => 'Bug Fix']);

        $response = $this->getJson('/api/v2/system-announcements/list?category=Bug Fix');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.category', 'Bug Fix');
    }

    public function test_view_found_returns_success_data(): void
    {
        $row = $this->makeAnnouncement(['title' => 'Lihat']);

        $response = $this->getJson("/api/v2/system-announcements/view/{$row->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.title', 'Lihat')
            ->assertJsonPath('data.files', []);
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/v2/system-announcements/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Announcement tidak ditemukan',
            ]);
    }

    public function test_add_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/v2/system-announcements/add', [
            'category'     => 'Fitur Baru',
            'title'        => 'Baru',
            'version'      => '2.0',
            'release_date' => '2026-02-02',
            'description'  => 'desc',
            'details'      => '<p>x</p>',
            'is_active'    => 1,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Announcement berhasil dibuat',
            ])
            ->assertJsonPath('data.title', 'Baru')
            ->assertJsonPath('data.files', []);

        $this->assertDatabaseHas('system_announcements', ['title' => 'Baru', 'category' => 'Fitur Baru']);
    }

    public function test_add_validation_error_returns_422_baserequest_shape(): void
    {
        $response = $this->postJson('/api/v2/system-announcements/add', [
            'category' => 'Invalid',
            'title'    => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['category', 'title']]);
    }

    public function test_update_found_returns_success(): void
    {
        $row = $this->makeAnnouncement(['title' => 'Lama']);

        $response = $this->postJson("/api/v2/system-announcements/update/{$row->id}", [
            'category' => 'Aturan',
            'title'    => 'Baru',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Announcement berhasil diupdate',
            ])
            ->assertJsonPath('data.title', 'Baru')
            ->assertJsonPath('data.category', 'Aturan');

        $this->assertDatabaseHas('system_announcements', ['id' => $row->id, 'title' => 'Baru']);
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->postJson('/api/v2/system-announcements/update/999', [
            'category' => 'Update',
            'title'    => 'X',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Announcement tidak ditemukan',
            ]);
    }

    public function test_delete_found_returns_message_only(): void
    {
        $row = $this->makeAnnouncement();

        $response = $this->deleteJson("/api/v2/system-announcements/delete/{$row->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Announcement berhasil dihapus',
            ]);

        $this->assertSoftDeleted('system_announcements', ['id' => $row->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/v2/system-announcements/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Announcement tidak ditemukan',
            ]);
    }

    public function test_delete_file_found_returns_message_only(): void
    {
        $row = $this->makeAnnouncement();
        $file = SystemAnnouncementFile::query()->create([
            'system_announcement_id' => $row->id,
            'nama_file'              => 'a.pdf',
            'url_file'               => url('document/announcement-files/a.pdf'),
            'mime_type'              => 'application/pdf',
            'size'                   => 100,
        ]);

        $response = $this->deleteJson("/api/v2/system-announcements/delete-file/{$file->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'File berhasil dihapus',
            ]);

        $this->assertSoftDeleted('system_announcement_files', ['id' => $file->id]);
    }

    public function test_delete_file_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/v2/system-announcements/delete-file/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'File tidak ditemukan',
            ]);
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('system_announcement_files');
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
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('system_announcement_files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('system_announcement_id');
            $table->string('nama_file')->nullable();
            $table->string('url_file')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
