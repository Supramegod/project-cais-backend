<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\TimSales;
use App\Models\TimSalesDetail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shape of TimSalesController
 * around its refactor onto the ApiResponser trait + FormRequest.
 * The envelope ({success, message, data} keys + status codes) must stay
 * identical, except 422 which moves to the BaseRequest shape ({message:{...}}).
 */
class TimSalesControllerTest extends TestCase
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

        DB::table('m_branch')->insert([
            'id' => 1,
            'name' => 'Jakarta Pusat',
            'is_active' => 1,
        ]);

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

    private function makeTeam(string $nama = 'Tim Jakarta'): TimSales
    {
        return TimSales::query()->create([
            'nama' => $nama,
            'branch_id' => 1,
            'branch' => 'Jakarta Pusat',
            'created_by' => 'Tester',
        ]);
    }

    public function test_list_returns_success_envelope_with_member_count(): void
    {
        $team = $this->makeTeam();
        TimSalesDetail::query()->create([
            'tim_sales_id' => $team->id, 'nama' => 'Andi', 'user_id' => 5, 'username' => 'andi',
        ]);

        $response = $this->getJson('/api/tim-sales/list');

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Data tim sales berhasil diambil'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Tim Jakarta')
            ->assertJsonPath('data.0.jumlah_anggota', 1);
    }

    public function test_show_found_returns_success_with_anggota(): void
    {
        $team = $this->makeTeam();
        TimSalesDetail::query()->create([
            'tim_sales_id' => $team->id, 'nama' => 'Andi', 'user_id' => 5, 'username' => 'andi', 'is_leader' => 1,
        ]);

        $response = $this->getJson("/api/tim-sales/show/{$team->id}");

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Detail tim sales berhasil diambil'])
            ->assertJsonPath('data.nama', 'Tim Jakarta')
            ->assertJsonCount(1, 'data.anggota')
            ->assertJsonPath('data.anggota.0.nama', 'Andi');
    }

    public function test_show_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/tim-sales/show/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Tim sales tidak ditemukan',
            ]);
    }

    public function test_store_creates_and_returns_201(): void
    {
        $response = $this->postJson('/api/tim-sales/store', [
            'nama' => 'Tim Baru',
            'branch_id' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tim sales berhasil dibuat',
            ])
            ->assertJsonPath('data.nama', 'Tim Baru');

        $this->assertDatabaseHas('m_tim_sales', [
            'nama' => 'Tim Baru',
            'branch_id' => 1,
            'created_by_user_id' => 1,
        ]);
    }

    public function test_store_validation_error_returns_422_baserequest_shape(): void
    {
        $response = $this->postJson('/api/tim-sales/store', ['nama' => '']);

        // BaseRequest shape: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama', 'branch_id']]);
    }

    public function test_update_found_returns_success(): void
    {
        $team = $this->makeTeam();

        $response = $this->putJson("/api/tim-sales/update/{$team->id}", [
            'nama' => 'Tim Diperbarui',
            'branch_id' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Tim sales berhasil diupdate',
            ])
            ->assertJsonPath('data.nama', 'Tim Diperbarui');
    }

    public function test_update_not_found_returns_404(): void
    {
        // Valid body so FormRequest passes and we reach the not-found path.
        $response = $this->putJson('/api/tim-sales/update/999', [
            'nama' => 'X',
            'branch_id' => 1,
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Tim sales tidak ditemukan',
            ]);
    }

    public function test_destroy_soft_deletes_team_and_details(): void
    {
        $team = $this->makeTeam();
        $member = TimSalesDetail::query()->create([
            'tim_sales_id' => $team->id, 'nama' => 'Andi', 'user_id' => 5, 'username' => 'andi',
        ]);

        $response = $this->deleteJson("/api/tim-sales/destroy/{$team->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Tim sales berhasil dihapus secara soft delete.',
            ]);

        $this->assertSoftDeleted('m_tim_sales', ['id' => $team->id]);
        $this->assertSoftDeleted('m_tim_sales_d', ['id' => $member->id]);
    }

    public function test_destroy_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/tim-sales/destroy/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Tim sales tidak ditemukan',
            ]);
    }

    public function test_get_members_returns_success(): void
    {
        $team = $this->makeTeam();
        TimSalesDetail::query()->create([
            'tim_sales_id' => $team->id, 'nama' => 'Andi', 'user_id' => 5, 'username' => 'andi',
        ]);

        $response = $this->getJson("/api/tim-sales/getMembers/{$team->id}");

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Anggota tim sales berhasil diambil'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Andi');
    }

    public function test_get_members_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/tim-sales/getMembers/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Tim sales tidak ditemukan',
            ]);
    }

    public function test_remove_member_returns_message_only(): void
    {
        $team = $this->makeTeam();
        $member = TimSalesDetail::query()->create([
            'tim_sales_id' => $team->id, 'nama' => 'Andi', 'user_id' => 5, 'username' => 'andi',
        ]);

        $response = $this->deleteJson("/api/tim-sales/removeMember/{$team->id}/{$member->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Anggota berhasil dihapus',
            ]);

        $this->assertSoftDeleted('m_tim_sales_d', ['id' => $member->id]);
    }

    public function test_set_leader_returns_success_and_flips_leader(): void
    {
        $team = $this->makeTeam();
        $old = TimSalesDetail::query()->create([
            'tim_sales_id' => $team->id, 'nama' => 'Andi', 'user_id' => 5, 'username' => 'andi', 'is_leader' => 1,
        ]);
        $new = TimSalesDetail::query()->create([
            'tim_sales_id' => $team->id, 'nama' => 'Budi', 'user_id' => 6, 'username' => 'budi', 'is_leader' => 0,
        ]);

        $response = $this->putJson("/api/tim-sales/setLeader/{$team->id}", ['member_id' => $new->id]);

        $response->assertOk()
            ->assertJson(['success' => true, 'message' => 'Leader berhasil diset'])
            ->assertJsonPath('data.is_leader', 1);

        $this->assertDatabaseHas('m_tim_sales_d', ['id' => $new->id, 'is_leader' => 1]);
        $this->assertDatabaseHas('m_tim_sales_d', ['id' => $old->id, 'is_leader' => 0]);
    }

    public function test_set_leader_validation_error_returns_422(): void
    {
        $team = $this->makeTeam();

        $response = $this->putJson("/api/tim-sales/setLeader/{$team->id}", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['member_id']]);
    }

    public function test_add_member_and_get_available_users_skipped(): void
    {
        $this->markTestSkipped(
            'addMember/getAvailableUsers depend on the cross-connection mysqlhris.m_user '
            .'directory filtered by cais_role_id [29,31,32,33] + branch matching; the '
            .'success paths need HR-directory fixtures beyond this envelope characterization.'
        );
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('m_tim_sales_d');
        Schema::dropIfExists('m_tim_sales');
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
            $table->boolean('is_active')->default(true);
        });

        Schema::create('m_tim_sales', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('branch')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_tim_sales_d', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->string('nama')->nullable();
            $table->string('username')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->boolean('is_leader')->default(0);
            $table->boolean('is_active')->default(1);
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
