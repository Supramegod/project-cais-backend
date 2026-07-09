<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Pks;
use App\Models\User;
use App\Services\Pks\Wizard\PksWizardService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test for PksWizardController endpoint envelopes that are NOT
 * already exercised by PksWizardFinalizeTest (initialize 201, updateStep 200,
 * getStep 404, cancel 404). Guards the ApiResponser envelope refactor.
 *
 * Connection wiring + schema/seed approach mirror PksWizardFinalizeTest.
 */
class PksWizardControllerTest extends TestCase
{
    use WithFaker;

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
    }

    public function test_initialize_endpoint_returns_created_envelope(): void
    {
        $user = $this->seedUser(1, 2, 'Init Endpoint');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);
        $this->seedSpk(30, 10, 20);
        $this->seedSpkSite(40, 30, 20, 10, 'Site A');

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->postJson('/api/pks-wizard/initialize/baru', [
            'leads_id' => 10,
            'company_id' => 13,
            'candidate_spk_ids' => [30],
            'candidate_quotation_ids' => [20],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'PKS wizard initialized successfully')
            ->assertJsonPath('data.wizard_status_id', 1)
            ->assertJsonPath('data.wizard_current_step', 1);

        $this->assertNotNull($response->json('data.pks_id'));
        $this->assertStringStartsWith('draft/PKS/SIG/LDS001-', $response->json('data.nomor'));
    }

    public function test_update_step_endpoint_returns_success_envelope(): void
    {
        $user = $this->seedUser(2, 2, 'Update Step');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);
        $this->seedSpk(30, 10, 20);
        $this->seedSpkSite(40, 30, 20, 10, 'Site A');

        $pks = app(PksWizardService::class)->initialize('baru', [
            'leads_id' => 10,
            'company_id' => 13,
            'candidate_spk_ids' => [30],
            'candidate_quotation_ids' => [20],
        ], $user);

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->postJson("/api/pks-wizard/{$pks->id}/step/1", [
            'mark_as_complete' => true,
            'step_data' => [
                'confirmed' => true,
                'notes' => 'Reviewed',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Step 1 updated successfully')
            ->assertJsonPath('data.step', 1);
    }

    public function test_get_step_endpoint_returns_not_found_envelope_for_missing_pks(): void
    {
        $user = $this->seedUser(3, 2, 'Missing PKS');
        $this->seedCommonMasterData();

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->getJson('/api/pks-wizard/999999/step/1');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'PKS not found');
    }

    public function test_cancel_endpoint_returns_not_found_envelope_for_missing_pks(): void
    {
        $user = $this->seedUser(4, 2, 'Cancel Missing');
        $this->seedCommonMasterData();

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->deleteJson('/api/pks-wizard/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'PKS not found');
    }

    private function seedUser(int $id, int $roleId, string $fullName): User
    {
        DB::table('m_user')->insert([
            'id' => $id,
            'username' => strtolower(str_replace(' ', '', $fullName)),
            'password' => bcrypt('secret'),
            'full_name' => $fullName,
            'email' => strtolower(str_replace(' ', '.', $fullName)) . '@example.com',
            'cais_role_id' => $roleId,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function seedCommonMasterData(): void
    {
        DB::table('m_company')->insert([
            'id' => 13,
            'name' => 'PT SIG',
            'code' => 'SIG',
            'nama_direktur' => 'Direktur SIG',
            'is_active' => 1,
        ]);

        DB::table('m_status_spk')->insert(['id' => 2, 'nama' => 'Active']);
        DB::table('m_kebutuhan')->insert(['id' => 3, 'nama' => 'Cleaning Service']);
        DB::table('m_salary_rule')->insert(['id' => 1, 'nama_salary_rule' => 'SR 1', 'cutoff' => '26-25']);
        DB::table('m_rule_thr')->insert(['id' => 2, 'nama' => 'Rule THR']);
        DB::table('m_kategori_sesuai_hc')->insert(['id' => 1, 'nama' => 'Kategori A']);
        DB::table('m_loyalty')->insert(['id' => 1, 'nama' => 'Loyal']);
        DB::table('m_pks_wizard_status')->insert([
            ['id' => 1, 'kode' => 'initialized', 'nama' => 'Initialized', 'urutan' => 1, 'is_active' => 1],
            ['id' => 2, 'kode' => 'in_progress', 'nama' => 'In Progress', 'urutan' => 2, 'is_active' => 1],
            ['id' => 3, 'kode' => 'ready_to_finalize', 'nama' => 'Ready To Finalize', 'urutan' => 3, 'is_active' => 1],
            ['id' => 4, 'kode' => 'finalized', 'nama' => 'Finalized', 'urutan' => 4, 'is_active' => 1],
            ['id' => 5, 'kode' => 'cancelled', 'nama' => 'Cancelled', 'urutan' => 5, 'is_active' => 1],
        ]);
    }

    private function seedLead(int $id): void
    {
        DB::table('sl_leads')->insert([
            'id' => $id,
            'nomor' => 'LDS001',
            'nama_perusahaan' => 'PT Customer',
            'alamat' => 'Jl. Testing',
            'branch_id' => 1,
            'kebutuhan_id' => 3,
            'status_leads_id' => 1,
            'bidang_perusahaan_id' => 1,
            'bidang_perusahaan' => 'Services',
            'jenis_perusahaan_id' => 1,
            'jenis_perusahaan' => 'PT',
            'provinsi_id' => 1,
            'provinsi' => 'Jatim',
            'kota_id' => 1,
            'kota' => 'Surabaya',
            'pic' => 'Budi',
            'jabatan' => 'Manager',
            'email' => 'budi@example.com',
            'no_telp' => '08123',
            'pma' => 0,
        ]);
    }

    private function seedQuotation(int $id, int $leadsId, string $nomor = 'Q-001', ?string $tipeQuotation = null): void
    {
        DB::table('sl_quotation')->insert([
            'id' => $id,
            'leads_id' => $leadsId,
            'kebutuhan_id' => 3,
            'nomor' => $nomor,
            'status_quotation_id' => 3,
            'company_id' => 13,
            'salary_rule_id' => 1,
            'rule_thr_id' => 2,
            'tipe_quotation' => $tipeQuotation,
            'persentase' => 10,
        ]);
    }

    private function seedSpk(int $id, int $leadsId, int $quotationId, string $nomor = 'SPK-001'): void
    {
        DB::table('sl_spk')->insert([
            'id' => $id,
            'leads_id' => $leadsId,
            'quotation_id' => $quotationId,
            'nomor' => $nomor,
            'status_spk_id' => 2,
            'nama_perusahaan' => 'PT Customer',
        ]);
    }

    private function seedSpkSite(int $id, int $spkId, int $quotationId, int $leadsId, string $namaSite): void
    {
        DB::table('sl_spk_site')->insert([
            'id' => $id,
            'spk_id' => $spkId,
            'quotation_id' => $quotationId,
            'quotation_site_id' => null,
            'leads_id' => $leadsId,
            'nama_site' => $namaSite,
            'provinsi_id' => 1,
            'provinsi' => 'Jatim',
            'kota_id' => 1,
            'kota' => 'Surabaya',
            'ump' => 0,
            'umk' => 0,
            'nominal_upah' => 5000000,
            'penempatan' => 'Surabaya',
            'nomor_quotation' => 'Q-001',
        ]);
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_activity_sales',
            'sl_customer_activity',
            'sl_pks_perjanjian',
            'sl_site',
            'sl_spk_site',
            'sl_spk',
            'sl_pks',
            'sl_quotation',
            'sl_leads',
            'm_pks_wizard_status',
            'm_loyalty',
            'm_kategori_sesuai_hc',
            'm_status_spk',
            'm_rule_thr',
            'm_salary_rule',
            'm_kebutuhan',
            'm_company',
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

        Schema::create('m_company', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->string('nama_direktur')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_salary_rule', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_salary_rule')->nullable();
            $table->string('cutoff')->nullable();
            $table->string('crosscheck_absen')->nullable();
            $table->string('pengiriman_invoice')->nullable();
            $table->string('perkiraan_invoice_diterima')->nullable();
            $table->string('pembayaran_invoice')->nullable();
            $table->string('rilis_payroll')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_status_spk', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('kode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_rule_thr', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('hari_penagihan_invoice')->nullable();
            $table->string('hari_pembayaran_invoice')->nullable();
            $table->string('hari_rilis_thr')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_kategori_sesuai_hc', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_loyalty', function (Blueprint $table) {
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
            $table->unsignedInteger('bidang_perusahaan_id')->nullable();
            $table->string('bidang_perusahaan')->nullable();
            $table->unsignedInteger('jenis_perusahaan_id')->nullable();
            $table->string('jenis_perusahaan')->nullable();
            $table->unsignedInteger('provinsi_id')->nullable();
            $table->string('provinsi')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('kota')->nullable();
            $table->string('pic')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('email')->nullable();
            $table->string('no_telp')->nullable();
            $table->boolean('pma')->default(false);
            $table->date('tgl_leads')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->boolean('customer_active')->default(false);
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('status_quotation_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('salary_rule_id')->nullable();
            $table->unsignedInteger('rule_thr_id')->nullable();
            $table->string('tipe_quotation')->nullable();
            $table->decimal('persentase', 10, 2)->nullable();
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
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('salary_rule_id')->nullable();
            $table->unsignedInteger('rule_thr_id')->nullable();
            $table->string('kode_perusahaan')->nullable();
            $table->text('alamat_perusahaan')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->string('layanan')->nullable();
            $table->unsignedInteger('bidang_usaha_id')->nullable();
            $table->string('bidang_usaha')->nullable();
            $table->unsignedInteger('jenis_perusahaan_id')->nullable();
            $table->string('jenis_perusahaan')->nullable();
            $table->unsignedInteger('provinsi_id')->nullable();
            $table->string('provinsi')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('kota')->nullable();
            $table->boolean('pma')->default(false);
            $table->unsignedInteger('sales_id')->nullable();
            $table->unsignedInteger('loyalty_id')->nullable();
            $table->string('loyalty')->nullable();
            $table->unsignedInteger('kategori_sesuai_hc_id')->nullable();
            $table->string('kategori_sesuai_hc')->nullable();
            $table->string('pic_1')->nullable();
            $table->string('jabatan_pic_1')->nullable();
            $table->string('email_pic_1')->nullable();
            $table->string('telp_pic_1')->nullable();
            $table->string('pic_2')->nullable();
            $table->string('jabatan_pic_2')->nullable();
            $table->string('email_pic_2')->nullable();
            $table->string('telp_pic_2')->nullable();
            $table->string('pic_3')->nullable();
            $table->string('jabatan_pic_3')->nullable();
            $table->string('email_pic_3')->nullable();
            $table->string('telp_pic_3')->nullable();
            $table->string('tipe_pks')->nullable();
            $table->unsignedInteger('wizard_status_id')->nullable();
            $table->unsignedInteger('wizard_current_step')->nullable();
            $table->json('wizard_completed_steps')->nullable();
            $table->json('wizard_payload')->nullable();
            $table->json('template_payload')->nullable();
            $table->json('pasal_preview_payload')->nullable();
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
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('status_spk_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_spk_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('spk_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->unsignedInteger('provinsi_id')->nullable();
            $table->string('provinsi')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('kota')->nullable();
            $table->decimal('ump', 15, 2)->nullable();
            $table->decimal('umk', 15, 2)->nullable();
            $table->decimal('nominal_upah', 15, 2)->nullable();
            $table->string('penempatan')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('kebutuhan')->nullable();
            $table->string('nomor_quotation')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('spk_id')->nullable();
            $table->unsignedInteger('pks_id')->nullable();
            $table->unsignedInteger('spk_site_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nomor')->nullable();
            $table->string('nomor_proyek')->nullable();
            $table->string('nama_proyek')->nullable();
            $table->string('nama_site')->nullable();
            $table->unsignedInteger('provinsi_id')->nullable();
            $table->string('provinsi')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('kota')->nullable();
            $table->decimal('ump', 15, 2)->nullable();
            $table->decimal('umk', 15, 2)->nullable();
            $table->decimal('nominal_upah', 15, 2)->nullable();
            $table->string('penempatan')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('kebutuhan')->nullable();
            $table->string('nomor_quotation')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
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

        Schema::create('sl_activity_sales', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->dateTime('tgl_activity')->nullable();
            $table->string('jenis_activity')->nullable();
            $table->text('notulen')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
    }
}
