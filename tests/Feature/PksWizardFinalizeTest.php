<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Pks;
use App\Models\User;
use App\Services\PksPasalPreviewService;
use App\Services\PksWizardFinalizeService;
use App\Services\PksWizardService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Tests\TestCase;

class PksWizardFinalizeTest extends TestCase
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

    public function test_finalize_service_persists_final_pks_sites_and_perjanjian(): void
    {
        $user = $this->seedUser(1, 2, 'Tester');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);
        $this->seedSpk(30, 10, 20);
        $this->seedSpkSite(40, 30, 20, 10, 'Site A');

        $pks = Pks::query()->create([
            'id' => 50,
            'leads_id' => 10,
            'quotation_id' => 20,
            'branch_id' => 1,
            'nomor' => 'draft/PKS/SIG/LDS001-062026-00001',
            'kode_perusahaan' => 'LDS001',
            'nama_perusahaan' => 'PT Customer',
            'alamat_perusahaan' => 'Jl. Testing',
            'layanan_id' => 3,
            'layanan' => 'Cleaning Service',
            'bidang_usaha_id' => 1,
            'bidang_usaha' => 'Services',
            'jenis_perusahaan_id' => 1,
            'jenis_perusahaan' => 'PT',
            'provinsi_id' => 1,
            'provinsi' => 'Jatim',
            'kota_id' => 1,
            'kota' => 'Surabaya',
            'company_id' => 13,
            'salary_rule_id' => 1,
            'rule_thr_id' => 2,
            'tipe_pks' => 'baru',
            'status_pks_id' => 5,
            'wizard_status_id' => 3,
            'wizard_current_step' => 7,
            'wizard_completed_steps' => [1, 2, 3, 4, 5, 6, 7],
            'wizard_payload' => [
                'header' => [
                    'tanggal_pks' => '2026-07-01',
                    'tanggal_awal_kontrak' => '2026-07-01',
                    'tanggal_akhir_kontrak' => '2027-06-30',
                    'company_id' => 13,
                    'salary_rule_id' => 1,
                    'rule_thr_id' => 2,
                    'kategori_sesuai_hc_id' => 1,
                    'loyalty_id' => 1,
                ],
                'sites' => [
                    'site_ids' => [40],
                ],
                'pic' => [
                    'pic_1' => 'Budi',
                ],
            ],
            'template_payload' => ['pks' => ['nomor' => 'draft/PKS/SIG/LDS001-062026-00001']],
            'pasal_preview_payload' => [[
                'key' => 'section_0',
                'pasal' => 'Pasal 1',
                'judul' => 'RUANG LINGKUP',
                'raw_text' => '<p>Isi pasal</p>',
                'is_edited' => false,
            ]],
            'initialized_at' => now(),
            'created_by' => 'Tester',
            'created_by_user_id' => 1,
        ]);

        $service = app(PksWizardFinalizeService::class);
        $finalized = $service->finalize($pks, $user);
        $finalizedId = $finalized->id;

        $this->assertSame('PKS/SIG/LDS001-062026-00001', $finalized->nomor);
        $this->assertSame(4, (int) $finalized->wizard_status_id);
        $this->assertNotNull($finalized->finalized_at);
        $this->assertDatabaseHas('sl_site', [
            'pks_id' => $finalizedId,
            'nama_site' => 'Site A',
        ]);
        $this->assertDatabaseHas('sl_pks_perjanjian', [
            'pks_id' => $finalizedId,
            'pasal' => 'Pasal 1',
        ]);
        $this->assertDatabaseHas('sl_customer_activity', [
            'pks_id' => $finalizedId,
            'tipe' => 'PKS',
        ]);
        $this->assertDatabaseHas('sl_leads', [
            'id' => 10,
            'status_leads_id' => 99,
        ]);
        $this->assertDatabaseHas('sl_quotation', [
            'id' => 20,
            'status_quotation_id' => 5,
        ]);
        $this->assertDatabaseHas('sl_spk', [
            'id' => 30,
            'status_spk_id' => 3,
        ]);
    }

    public function test_approve_endpoint_blocks_non_finalized_wizard_pks(): void
    {
        $user = $this->seedUser(2, 2, 'Approver');

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        DB::table('sl_pks')->insert([
            'id' => 60,
            'nomor' => 'draft/PKS/TEST',
            'status_pks_id' => 5,
            'wizard_status_id' => 2,
            'tipe_pks' => 'baru',
        ]);

        $response = $this->postJson('/api/pks/60/approve', [
            'ot' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            'message' => 'PKS wizard belum finalized',
            ]);
    }

    public function test_initialize_service_creates_draft_for_baru_rekontrak_and_addendum(): void
    {
        $user = $this->seedUser(3, 2, 'Initializer');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);
        $this->seedSpk(30, 10, 20);

        $service = app(PksWizardService::class);

        $baru = $service->initialize('baru', [
            'leads_id' => 10,
            'company_id' => 13,
            'quotation_id' => 20,
            'spk_id' => 30,
        ], $user);
        $this->assertStringStartsWith('draft/PKS/SIG/LDS001-', $baru->nomor);
        $this->assertSame(1, (int) $baru->wizard_status_id);
        $this->assertSame('baru', $baru->tipe_pks);

        $rekontrak = $service->initialize('rekontrak', [
            'leads_id' => 10,
            'company_id' => 13,
            'quotation_id' => 20,
        ], $user);
        $this->assertSame('rekontrak', $rekontrak->tipe_pks);
        $this->assertStringStartsWith('draft/PKS/SIG/LDS001-', $rekontrak->nomor);

        $pksInduk = Pks::query()->create([
            'leads_id' => 10,
            'quotation_id' => 20,
            'company_id' => 13,
            'nomor' => 'PKS/SIG/LDS001-062026-00001',
            'tipe_pks' => 'baru',
            'status_pks_id' => 5,
            'created_by' => 'Initializer',
            'created_by_user_id' => 3,
        ]);

        $addendum = $service->initialize('addendum', [
            'leads_id' => 10,
            'pks_induk_id' => $pksInduk->id,
            'quotation_id' => 20,
        ], $user);
        $this->assertSame('addendum', $addendum->tipe_pks);
        $this->assertStringStartsWith('draft/ADD/PKS/SIG/LDS001-062026-00001/', $addendum->nomor);
    }

    public function test_addendum_preview_can_be_generated_and_edited(): void
    {
        $this->seedCommonMasterData();
        $this->seedLead(10);

        $pksInduk = Pks::query()->create([
            'leads_id' => 10,
            'nomor' => 'PKS/SIG/LDS001-062026-00001',
            'tipe_pks' => 'baru',
            'status_pks_id' => 5,
            'created_by' => 'System',
        ]);

        $pks = Pks::query()->create([
            'leads_id' => 10,
            'pks_induk_id' => $pksInduk->id,
            'nomor' => 'draft/ADD/PKS/SIG/LDS001-062026-00001/0001',
            'tipe_pks' => 'addendum',
            'status_pks_id' => 5,
            'wizard_status_id' => 2,
            'wizard_payload' => ['source' => ['pks_induk' => ['nomor' => $pksInduk->nomor]]],
            'created_by' => 'System',
        ]);

        $service = app(PksPasalPreviewService::class);
        $preview = $service->generatePreview($pks->fresh(['pksInduk']), [
            'regenerate' => true,
            'additional_articles' => [[
                'pasal' => 'Addendum 1',
                'judul' => 'PASAL TAMBAHAN',
                'raw_text' => '<p>Isi awal</p>',
            ]],
        ]);

        $this->assertCount(1, $preview);
        $this->assertSame('section_0', $preview[0]['key']);

        $updated = $service->updatePreview($pks->fresh(), 'section_0', [
            'raw_text' => '<p>Isi edit</p>',
        ]);

        $this->assertSame('<p>Isi edit</p>', $updated[0]['raw_text']);
        $this->assertTrue($updated[0]['is_edited']);
    }

    public function test_finalize_addendum_does_not_create_sites(): void
    {
        $user = $this->seedUser(4, 2, 'Finalizer');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);

        $pksInduk = Pks::query()->create([
            'leads_id' => 10,
            'quotation_id' => 20,
            'company_id' => 13,
            'nomor' => 'PKS/SIG/LDS001-062026-00001',
            'tipe_pks' => 'baru',
            'status_pks_id' => 5,
            'created_by' => 'Finalizer',
            'created_by_user_id' => 4,
        ]);

        $addendum = Pks::query()->create([
            'leads_id' => 10,
            'quotation_id' => 20,
            'company_id' => 13,
            'pks_induk_id' => $pksInduk->id,
            'nomor' => 'draft/ADD/PKS/SIG/LDS001-062026-00001/0001',
            'layanan_id' => 3,
            'layanan' => 'Cleaning Service',
            'tipe_pks' => 'addendum',
            'status_pks_id' => 5,
            'wizard_status_id' => 3,
            'wizard_current_step' => 7,
            'wizard_completed_steps' => [1, 2, 4, 5, 6, 7],
            'wizard_payload' => [
                'header' => [
                    'tanggal_pks' => '2026-07-01',
                    'tanggal_awal_kontrak' => '2026-07-01',
                    'tanggal_akhir_kontrak' => '2027-06-30',
                    'company_id' => 13,
                    'salary_rule_id' => 1,
                    'rule_thr_id' => 2,
                ],
            ],
            'pasal_preview_payload' => [[
                'key' => 'section_0',
                'pasal' => 'Addendum 1',
                'judul' => 'PASAL TAMBAHAN',
                'raw_text' => '<p>Addendum</p>',
                'is_edited' => false,
            ]],
            'created_by' => 'Finalizer',
            'created_by_user_id' => 4,
        ]);

        $finalized = app(PksWizardFinalizeService::class)->finalize($addendum, $user);

        $this->assertSame(0, $finalized->sites->count());
        $this->assertSame(1, $finalized->perjanjian->count());
        $this->assertStringStartsWith('ADD/PKS/SIG/LDS001-062026-00001/', $finalized->nomor);
    }

    public function test_finalize_failure_keeps_draft_state_unchanged(): void
    {
        $user = $this->seedUser(5, 2, 'Verifier');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);

        $pks = Pks::query()->create([
            'leads_id' => 10,
            'quotation_id' => 20,
            'company_id' => 13,
            'nomor' => 'draft/PKS/SIG/LDS001-062026-00009',
            'layanan_id' => 3,
            'layanan' => 'Cleaning Service',
            'tipe_pks' => 'baru',
            'status_pks_id' => 5,
            'wizard_status_id' => 3,
            'wizard_current_step' => 7,
            'wizard_completed_steps' => [1, 2, 3, 4, 5, 6, 7],
            'wizard_payload' => [
                'header' => [
                    'tanggal_pks' => '2026-07-01',
                    'tanggal_awal_kontrak' => '2026-07-01',
                    'tanggal_akhir_kontrak' => '2027-06-30',
                    'company_id' => 13,
                    'salary_rule_id' => 1,
                    'rule_thr_id' => 2,
                ],
                'sites' => ['site_ids' => []],
            ],
            'pasal_preview_payload' => [],
            'created_by' => 'Verifier',
            'created_by_user_id' => 5,
        ]);

        try {
            app(PksWizardFinalizeService::class)->finalize($pks, $user);
            $this->fail('Finalize seharusnya gagal');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Pasal preview belum tersedia', $e->getMessage());
        }

        $pks->refresh();
        $this->assertSame('draft/PKS/SIG/LDS001-062026-00009', $pks->nomor);
        $this->assertSame(3, (int) $pks->wizard_status_id);
        $this->assertNull($pks->finalized_at);
    }

    public function test_crm_user_can_access_wizard_step(): void
    {
        $user = $this->seedUser(6, 54, 'CRM User');
        $this->seedCommonMasterData();
        $this->seedLead(10);

        $pks = Pks::query()->create([
            'leads_id' => 10,
            'nomor' => 'draft/PKS/SIG/LDS001-062026-00010',
            'tipe_pks' => 'baru',
            'status_pks_id' => 5,
            'wizard_status_id' => 1,
            'wizard_current_step' => 1,
            'wizard_payload' => ['source' => ['tipe_pks' => 'baru']],
            'created_by' => 'CRM User',
        ]);

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->getJson("/api/pks-wizard/{$pks->id}/step/2");

        $response->assertOk()
            ->assertJsonPath('data.pks_id', $pks->id)
            ->assertJsonPath('data.step', 2);
    }

    public function test_cancel_endpoint_marks_wizard_as_cancelled(): void
    {
        $user = $this->seedUser(7, 54, 'CRM Cancel');
        $this->seedCommonMasterData();
        $this->seedLead(10);

        $pks = Pks::query()->create([
            'leads_id' => 10,
            'nomor' => 'draft/PKS/SIG/LDS001-062026-00011',
            'tipe_pks' => 'baru',
            'status_pks_id' => 5,
            'wizard_status_id' => 2,
            'created_by' => 'CRM Cancel',
        ]);

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->deleteJson("/api/pks-wizard/{$pks->id}");

        $response->assertOk()->assertJsonPath('data.wizard_status_id', 5);
        $this->assertDatabaseHas('sl_pks', [
            'id' => $pks->id,
            'wizard_status_id' => 5,
        ]);
    }

    public function test_source_quotation_endpoint_returns_quotation_candidates(): void
    {
        $user = $this->seedUser(8, 54, 'CRM Source');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);
        $this->seedQuotation(21, 10, 'Q-SEARCH');

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->postJson('/api/pks-wizard/source/quotations/10', [
            'search' => 'SEARCH',
            'search_by' => 'nomor',
            'per_page' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', 21)
            ->assertJsonPath('data.0.nomor', 'Q-SEARCH')
            ->assertJsonPath('data.0.company.id', 13)
            ->assertJsonPath('pagination.per_page', 1);
    }

    public function test_source_spk_endpoint_returns_spk_candidates(): void
    {
        $user = $this->seedUser(9, 54, 'CRM SPK');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);
        $this->seedSpk(30, 10, 20);
        $this->seedSpk(31, 10, 20, 'SPK-SEARCH');
        $this->seedSpkSite(40, 30, 20, 10, 'Site A');
        $this->seedSpkSite(41, 31, 20, 10, 'Site B');

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->getJson('/api/pks-wizard/source/spk/10');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 31)
            ->assertJsonPath('data.0.nomor', 'SPK-SEARCH')
            ->assertJsonPath('data.0.quotations.0.id', 20)
            ->assertJsonPath('data.0.quotations.0.company.id', 13);
    }

    public function test_source_quotation_endpoint_can_be_filtered_by_spk(): void
    {
        $user = $this->seedUser(11, 54, 'CRM Source Filter');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10, 'Q-001');
        $this->seedQuotation(21, 10, 'Q-002');
        $this->seedSpk(30, 10, 20);

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->postJson('/api/pks-wizard/source/quotations/10', [
            'spk_ids' => [30],
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 20);
    }

    public function test_source_quotation_endpoint_can_be_filtered_by_multiple_spk(): void
    {
        $user = $this->seedUser(14, 54, 'CRM Multi SPK');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10, 'Q-001');
        $this->seedQuotation(21, 10, 'Q-002');
        $this->seedSpk(30, 10, 20);
        $this->seedSpk(31, 10, 21, 'SPK-002');

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->postJson('/api/pks-wizard/source/quotations/10', [
            'spk_ids' => [30, 31],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_source_quotation_endpoint_supports_search_by_company_name(): void
    {
        $user = $this->seedUser(13, 54, 'CRM Source Company');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10, 'Q-001');

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->postJson('/api/pks-wizard/source/quotations/10', [
            'search' => 'SIG',
            'search_by' => 'company_name',
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 20);
    }

    public function test_step_three_derives_source_ids_and_primary_quotation(): void
    {
        $user = $this->seedUser(15, 2, 'Step Three');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10, 'Q-001');
        $this->seedQuotation(21, 10, 'Q-002');
        $this->seedSpk(30, 10, 20);
        $this->seedSpk(31, 10, 21, 'SPK-002');
        $this->seedSpkSite(40, 30, 20, 10, 'Site A');
        $this->seedSpkSite(41, 31, 21, 10, 'Site B');

        $pks = app(PksWizardService::class)->initialize('baru', [
            'leads_id' => 10,
            'company_id' => 13,
            'candidate_spk_ids' => [30, 31],
            'candidate_quotation_ids' => [20, 21],
        ], $user);

        $updated = app(PksWizardService::class)->updateStep($pks, 3, [
            'site_ids' => [40, 41],
            'primary_quotation_id' => 21,
        ], true, $user);

        $sitesPayload = $updated->wizard_payload['sites'];
        $this->assertSame([30, 31], $sitesPayload['derived_spk_ids']);
        $this->assertSame([20, 21], $sitesPayload['derived_quotation_ids']);
        $this->assertSame(21, $sitesPayload['primary_quotation_id']);
    }

    public function test_initialize_validation_rejects_mismatched_spk_and_quotation(): void
    {
        $user = $this->seedUser(10, 54, 'CRM Validate');
        $this->seedCommonMasterData();
        $this->seedLead(10);
        $this->seedQuotation(20, 10);
        $this->seedQuotation(21, 10, 'Q-002');
        $this->seedSpk(30, 10, 20);

        $this->actingAs($user, 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $response = $this->postJson('/api/pks-wizard/initialize/baru', [
            'leads_id' => 10,
            'company_id' => 13,
            'candidate_spk_ids' => [30],
            'candidate_quotation_ids' => [21],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message.candidate_quotation_ids.0', 'Ada quotation candidate yang tidak terhubung dengan SPK candidate yang dipilih');
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

    private function seedQuotation(int $id, int $leadsId, string $nomor = 'Q-001'): void
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
