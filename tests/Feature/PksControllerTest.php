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
 * Characterization + contract test for PksController after adopting the
 * ApiResponser envelope + FormRequests + closure transactions.
 *
 * Success/404/message envelopes are locked to their PRE-refactor shape;
 * validation (422) is locked to the BaseRequest contract { message: { field: [..] } }
 * (an approved contract change vs the old { success:false, errors:{} }).
 */
class PksControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = storage_path('framework/testing-' . Str::random(8) . '.sqlite');
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

    // ── index ────────────────────────────────────────────────────────────

    public function test_index_returns_list_with_pagination_shape(): void
    {
        DB::table('m_status_pks')->insert(['id' => 5, 'nama' => 'Draft']);
        $this->seedPks(1, ['nomor' => 'PKS-A', 'status_pks_id' => 5]);
        $this->seedPks(2, ['nomor' => 'PKS-B', 'status_pks_id' => 5]);

        $response = $this->getJson('/api/pks/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'PKS data retrieved successfully',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination' => ['current_page', 'last_page', 'total', 'total_per_page'],
                'meta' => ['tgl_dari', 'tgl_sampai'],
            ]);
    }

    // ── show ─────────────────────────────────────────────────────────────

    public function test_show_found_returns_mapped_envelope(): void
    {
        DB::table('m_status_pks')->insert(['id' => 5, 'nama' => 'Draft']);
        $this->seedPks(10, [
            'nomor' => 'PKS-10',
            'status_pks_id' => 5,
            'kontrak_awal' => '2026-01-01',
            'kontrak_akhir' => '2026-12-31',
        ]);

        $response = $this->getJson('/api/pks/view/10');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.pks_mapped.id', 10)
            ->assertJsonPath('data.pks_mapped.nomor', 'PKS-10')
            ->assertJsonStructure([
                'success',
                'data' => ['pks_mapped', 'leads_mapped'],
                'quotation_data',
                'spk_data',
                'sites_info',
            ]);
    }

    public function test_show_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/pks/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'PKS not found',
            ]);
    }

    // ── destroy ──────────────────────────────────────────────────────────

    public function test_destroy_found_returns_message_only(): void
    {
        $this->seedPks(20, ['nomor' => 'PKS-20']);

        $response = $this->deleteJson('/api/pks/delete/20');

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'PKS deleted successfully',
            ]);

        $this->assertSoftDeleted('sl_pks', ['id' => 20]);
    }

    public function test_destroy_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/pks/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'PKS not found',
            ]);
    }

    // ── approve (complementary to PksWizardFinalizeTest's 422 path) ───────

    public function test_approve_not_found_returns_404(): void
    {
        $response = $this->postJson('/api/pks/999/approve', ['ot' => 1]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'PKS not found',
            ]);
    }

    // ── storePasal ───────────────────────────────────────────────────────

    public function test_store_pasal_creates_and_returns_201(): void
    {
        $this->seedPks(30, ['nomor' => 'PKS-30']); // leads_id null → no activity log branch

        $response = $this->postJson('/api/pks/30/perjanjian', [
            'pasal' => 'Pasal 1',
            'judul' => 'RUANG LINGKUP',
            'raw_text' => '<p>Isi pasal</p>',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pasal berhasil ditambahkan',
            ])
            ->assertJsonPath('data.pasal', 'Pasal 1')
            ->assertJsonPath('data.pks_id', 30);

        $this->assertDatabaseHas('sl_pks_perjanjian', [
            'pks_id' => 30,
            'pasal' => 'Pasal 1',
        ]);
    }

    public function test_store_pasal_duplicate_returns_422_error_envelope(): void
    {
        $this->seedPks(31, ['nomor' => 'PKS-31']);
        DB::table('sl_pks_perjanjian')->insert([
            'pks_id' => 31,
            'pasal' => 'Pasal 1',
            'judul' => 'X',
            'raw_text' => 'Y',
        ]);

        $response = $this->postJson('/api/pks/31/perjanjian', [
            'pasal' => 'Pasal 1',
            'judul' => 'RUANG LINGKUP',
            'raw_text' => '<p>Isi pasal</p>',
        ]);

        $response->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => "Pasal 'Pasal 1' sudah ada dalam perjanjian PKS ini.",
            ]);
    }

    public function test_store_pasal_validation_error_uses_base_request_shape(): void
    {
        $this->seedPks(32, ['nomor' => 'PKS-32']);

        $response = $this->postJson('/api/pks/32/perjanjian', [
            'pasal' => '',
        ]);

        // BaseRequest contract: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['pasal', 'judul', 'raw_text']]);
    }

    public function test_store_pasal_pks_not_found_returns_404(): void
    {
        $response = $this->postJson('/api/pks/999/perjanjian', [
            'pasal' => 'Pasal 1',
            'judul' => 'RUANG LINGKUP',
            'raw_text' => '<p>Isi pasal</p>',
        ]);

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'PKS tidak ditemukan',
            ]);
    }

    // ── destroyPasal ─────────────────────────────────────────────────────

    public function test_destroy_pasal_found_returns_message(): void
    {
        $this->seedPks(40, ['nomor' => 'PKS-40']);
        $pasalId = DB::table('sl_pks_perjanjian')->insertGetId([
            'pks_id' => 40,
            'pasal' => 'Pasal 1',
            'judul' => 'X',
            'raw_text' => 'Y',
        ]);

        $response = $this->deleteJson("/api/pks/perjanjian/{$pasalId}");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Pasal berhasil dihapus',
            ]);

        $this->assertSoftDeleted('sl_pks_perjanjian', ['id' => $pasalId]);
    }

    public function test_destroy_pasal_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/pks/perjanjian/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Pasal tidak ditemukan',
            ]);
    }

    // ── endpoints requiring heavy/unseedable graphs ──────────────────────

    public function test_store_full_flow_skipped(): void
    {
        $this->markTestSkipped('store() drives processPksLogic across sl_spk_site/quotation_site graph + PksTemplateFactory — envelope refactored, full flow out of scope here (covered by PKS wizard suite).');
    }

    public function test_upload_pks_skipped(): void
    {
        $this->markTestSkipped('uploadPks() needs a real file upload + pks storage disk + createUploadPksActivity — envelope refactored to closure transaction, not unit-seedable here.');
    }

    public function test_compare_perjanjian_skipped(): void
    {
        $this->markTestSkipped('comparePerjanjian() needs sl_pks_perjanjian_history graph + diff builder — envelope refactored (ComparePerjanjianRequest + successResponse/notFoundResponse).');
    }

    public function test_get_available_sites_skipped(): void
    {
        $this->markTestSkipped('getAvailableSites() depends on the sl_spk_site/quotation candidate graph — envelope refactored to successResponse.');
    }

    public function test_get_perjanjian_template_data_skipped(): void
    {
        $this->markTestSkipped('getPerjanjianTemplateData() builds the full template graph (company/salaryRule/ruleThr/sites) — envelope refactored to successResponse/notFoundResponse.');
    }

    // ── schema + seed helpers ────────────────────────────────────────────

    private function seedPks(int $id, array $overrides = []): void
    {
        DB::table('sl_pks')->insert(array_merge([
            'id' => $id,
            'nomor' => 'PKS-' . $id,
            'tipe_pks' => 'baru',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_customer_activity',
            'sl_pks_perjanjian',
            'sl_site',
            'sl_spk',
            'sl_pks',
            'sl_leads',
            'm_pks_wizard_status',
            'm_status_pks',
            'm_rule_thr',
            'm_kebutuhan',
            'm_user',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_rule_thr', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_status_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_pks_wizard_status', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode')->nullable();
            $table->string('nama')->nullable();
            $table->unsignedInteger('urutan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->text('alamat')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kota')->nullable();
            $table->string('pic')->nullable();
            $table->string('jabatan')->nullable();
            $table->boolean('pma')->default(false);
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_induk_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_pks')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->date('kontrak_awal')->nullable();
            $table->date('kontrak_akhir')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->unsignedInteger('rule_thr_id')->nullable();
            $table->string('link_pks_disetujui')->nullable();
            $table->boolean('is_aktif')->nullable();
            $table->string('tipe_pks')->nullable();
            $table->unsignedInteger('wizard_status_id')->nullable();
            $table->unsignedInteger('wizard_current_step')->nullable();
            $table->json('wizard_completed_steps')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_spk', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_spk')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('status_spk_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('spk_id')->nullable();
            $table->unsignedInteger('pks_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->string('kota')->nullable();
            $table->string('penempatan')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks_perjanjian', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id')->nullable();
            $table->string('pasal')->nullable();
            $table->string('judul')->nullable();
            $table->text('raw_text')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_customer_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('tgl_activity')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('pks_id')->nullable();
            $table->text('notes')->nullable();
            $table->string('tipe')->nullable();
            $table->boolean('is_activity')->default(false);
            $table->unsignedInteger('user_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
