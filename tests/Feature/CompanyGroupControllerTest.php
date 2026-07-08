<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Leads;
use App\Models\PerusahaanGroup;
use App\Models\PerusahaanGroupDetail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shapes of
 * CompanyGroupController while it is migrated onto the ApiResponser trait
 * + FormRequest validation. Envelope keys / status codes stay identical,
 * except validation errors which move from 400 -> 422 (BaseRequest shape).
 */
class CompanyGroupControllerTest extends TestCase
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

    // ---------- helpers ----------

    private function makeGroup(string $nama, int $jumlah = 0): PerusahaanGroup
    {
        return PerusahaanGroup::query()->create([
            'nama_grup' => $nama,
            'jumlah_perusahaan' => $jumlah,
            'created_by' => 'Tester',
            'created_by_user_id' => 1,
            'update_by' => 'Tester',
        ]);
    }

    private function makeLead(string $nama, string $kota = 'Jakarta'): Leads
    {
        return Leads::query()->create([
            'nama_perusahaan' => $nama,
            'kota' => $kota,
            'pic' => 'PIC',
            'no_telp' => '0812',
            'email' => 'a@b.com',
        ]);
    }

    // ---------- list ----------

    public function test_list_returns_success_data_and_total(): void
    {
        $this->makeGroup('Grup A');
        $this->makeGroup('Grup B');

        $response = $this->getJson('/api/company-group/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data grup perusahaan berhasil diambil',
                'total' => 2,
            ])
            ->assertJsonCount(2, 'data');
    }

    // ---------- view ----------

    public function test_view_found_returns_success_group_and_perusahaan(): void
    {
        $group = $this->makeGroup('Grup View', 1);
        $lead = $this->makeLead('PT Contoh');
        PerusahaanGroupDetail::query()->create([
            'group_id' => $group->id,
            'leads_id' => $lead->id,
            'nama_perusahaan' => $lead->nama_perusahaan,
            'created_by' => 'Tester',
            'update_by' => 'Tester',
        ]);

        $response = $this->getJson("/api/company-group/view/{$group->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Detail grup berhasil diambil',
            ])
            ->assertJsonPath('data.total_perusahaan', 1)
            ->assertJsonPath('data.perusahaan.0.nama_perusahaan', 'PT Contoh')
            ->assertJsonPath('data.group.nama_grup', 'Grup View');
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/company-group/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Grup tidak ditemukan',
            ]);
    }

    // ---------- create ----------

    public function test_create_group_with_companies_persists_and_returns_success(): void
    {
        $lead = $this->makeLead('PT Anggota');

        $response = $this->postJson('/api/company-group/create', [
            'nama_grup' => 'Grup Baru',
            'perusahaan_ids' => [$lead->id],
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.group.nama_grup', 'Grup Baru')
            ->assertJsonPath('data.added_companies', 1);

        $this->assertDatabaseHas('sl_perusahaan_groups', [
            'nama_grup' => 'Grup Baru',
            'created_by_user_id' => 1, // persists after $fillable fix
        ]);
        $this->assertDatabaseHas('sl_perusahaan_groups_d', [
            'leads_id' => $lead->id,
            'created_by_user_id' => 1,
        ]);
    }

    public function test_create_validation_error_returns_422(): void
    {
        // 422 is the sanctioned contract change (was 400) via BaseRequest.
        $response = $this->postJson('/api/company-group/create', ['nama_grup' => '']);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama_grup']]);
    }

    // ---------- update ----------

    public function test_update_found_returns_success(): void
    {
        $group = $this->makeGroup('Grup Lama');

        $response = $this->putJson("/api/company-group/update/{$group->id}", [
            'nama_grup' => 'Grup Baru Update',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Grup "Grup Baru Update" berhasil diperbarui',
            ])
            ->assertJsonPath('data.nama_grup', 'Grup Baru Update');
    }

    public function test_update_not_found_returns_404(): void
    {
        $response = $this->putJson('/api/company-group/update/999', [
            'nama_grup' => 'Nama Valid',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Grup tidak ditemukan',
            ]);
    }

    public function test_update_validation_error_returns_422(): void
    {
        $group = $this->makeGroup('Grup Ada');

        $response = $this->putJson("/api/company-group/update/{$group->id}", [
            'nama_grup' => 'ab', // < min:3
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama_grup']]);
    }

    // ---------- delete ----------

    public function test_delete_found_returns_message_only(): void
    {
        $group = $this->makeGroup('Grup Hapus', 1);
        $lead = $this->makeLead('PT Del');
        PerusahaanGroupDetail::query()->create([
            'group_id' => $group->id,
            'leads_id' => $lead->id,
            'nama_perusahaan' => $lead->nama_perusahaan,
            'created_by' => 'Tester',
            'update_by' => 'Tester',
        ]);

        $response = $this->deleteJson("/api/company-group/delete/{$group->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Grup perusahaan berhasil dihapus',
            ]);

        $this->assertSoftDeleted('sl_perusahaan_groups', ['id' => $group->id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/company-group/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Grup tidak ditemukan',
            ]);
    }

    // ---------- members ----------

    public function test_get_companies_in_group_returns_success(): void
    {
        $group = $this->makeGroup('Grup Members', 1);
        $lead = $this->makeLead('PT Member');
        PerusahaanGroupDetail::query()->create([
            'group_id' => $group->id,
            'leads_id' => $lead->id,
            'nama_perusahaan' => $lead->nama_perusahaan,
            'created_by' => 'Tester',
            'update_by' => 'Tester',
        ]);

        $response = $this->getJson("/api/company-group/companies/{$group->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data perusahaan dalam grup berhasil diambil',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_perusahaan', 'PT Member');
    }

    public function test_remove_company_found_returns_message(): void
    {
        $group = $this->makeGroup('Grup RC', 1);
        $lead = $this->makeLead('PT Remove');
        PerusahaanGroupDetail::query()->create([
            'group_id' => $group->id,
            'leads_id' => $lead->id,
            'nama_perusahaan' => $lead->nama_perusahaan,
            'created_by' => 'Tester',
            'update_by' => 'Tester',
        ]);

        $response = $this->deleteJson("/api/company-group/remove-company/{$group->id}/{$lead->id}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Perusahaan berhasil dihapus dari grup',
            ]);

        $this->assertSoftDeleted('sl_perusahaan_groups_d', [
            'group_id' => $group->id,
            'leads_id' => $lead->id,
        ]);
    }

    public function test_remove_company_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/company-group/remove-company/1/1');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_bulk_assign_invalid_returns_400(): void
    {
        $response = $this->postJson('/api/company-group/bulk-assign', ['assignments' => []]);

        $response->assertStatus(400)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data assignments tidak valid',
            ]);
    }

    public function test_bulk_remove_invalid_returns_400(): void
    {
        $response = $this->deleteJson('/api/company-group/bulk-remove-companies', ['removals' => []]);

        $response->assertStatus(400)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data removals tidak valid',
            ]);
    }

    /**
     * Skipped: getStatistics / getAvailableCompanies / getRecommendations
     * and the happy-path bulk operations each require multi-table lead
     * seeding across the mocked mysql/mysqlhris connections (jenis_perusahaan,
     * status_leads, whereDoesntHave sub-selects) with little envelope payoff —
     * the envelope helpers they use (successResponse / errorResponse) are
     * already exercised by the tests above.
     */
    public function test_heavy_read_and_bulk_happy_paths_skipped(): void
    {
        $this->markTestSkipped('Multi-table seeding heavy; envelope covered by other cases.');
    }

    private function rebuildSchema(): void
    {
        Schema::dropIfExists('sl_perusahaan_groups');
        Schema::dropIfExists('sl_perusahaan_groups_d');
        Schema::dropIfExists('sl_leads');
        Schema::dropIfExists('m_jenis_perusahaan');
        Schema::dropIfExists('m_status_leads');
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

        Schema::create('sl_perusahaan_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_grup')->nullable();
            $table->integer('jumlah_perusahaan')->default(0);
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('update_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('update_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_perusahaan_groups_d', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('leads_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('update_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('update_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->string('kota')->nullable();
            $table->string('pic')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('jenis_perusahaan_id')->nullable();
            $table->unsignedBigInteger('status_leads_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('m_jenis_perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
        });

        Schema::create('m_status_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('warna_background')->nullable();
            $table->string('warna_font')->nullable();
        });
    }
}
