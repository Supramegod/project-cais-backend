<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response shapes of QuotationController
 * around its refactor onto the ApiResponser trait + FormRequest.
 *
 * The base sl_quotation* tables are NOT created by migrations (prod imports a
 * MySQL dump). We hand-build a minimal schema in setUp() and point the
 * sqlite / mysql / mysqlhris connections at one temp file, mirroring
 * QuotationCalculationCharacterizationTest.
 *
 * Only endpoints seedable WITHOUT the full calculation graph are asserted here.
 * Calculation-heavy endpoints (store/show building a QuotationResource, destroy
 * cascading over 14 relation tables, submit/reset approval) are skipped with
 * reasons — their plain envelopes were still converted to the trait.
 */
class QuotationControllerTest extends TestCase
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
            'cais_role_id' => 2, // superadmin → byUserRole/filterByUserRole unfiltered
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    public function test_index_returns_bespoke_pagination_envelope(): void
    {
        DB::table('m_status_quotation')->insert(['id' => 1, 'nama' => 'Draft']);

        DB::table('sl_quotation')->insert([
            'id' => 1,
            'leads_id' => 10,
            'nomor' => 'Q-001',
            'step' => 12,
            'jumlah_site' => 'Single Site',
            'company_id' => 5,
            'company' => 'PT ION',
            'kebutuhan' => 'Security',
            'kebutuhan_id' => 3,
            'nama_perusahaan' => 'PT Example',
            'tgl_quotation' => now()->toDateString(),
            'status_quotation_id' => 1,
            'jenis_kontrak' => 'TERPADU',
            'created_by' => 'Tester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sl_quotation_site')->insert([
            'id' => 1, 'quotation_id' => 1, 'nama_site' => 'Head Office', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/quotations/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Quotations retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'nomor', 'step', 'jumlah_site', 'company', 'status_quotation', 'sl_quotation_site']],
                'pagination' => ['current_page', 'last_page', 'total', 'total_per_page'],
                'message',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor', 'Q-001')
            ->assertJsonPath('data.0.status_quotation.nama', 'Draft')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_index_exposes_status_berlaku_label_per_row(): void
    {
        $this->seedQuotationsWithKontrakSelesai();

        $response = $this->getJson('/api/quotations/list?per_page=50');

        $labels = collect($response->assertOk()->json('data'))
            ->pluck('status_berlaku', 'nomor');

        $this->assertSame('Kontrak Habis', $labels['Q-HABIS']);
        $this->assertSame('Berakhir dalam 2 bulan', $labels['Q-2BLN']);
        $this->assertSame('Berakhir dalam 3 bulan', $labels['Q-3BLN']);
        $this->assertSame('Lebih dari 3 Bulan', $labels['Q-LEBIH']);
        $this->assertNull($labels['Q-NULL']);
    }

    #[DataProvider('statusBerlakuFilterProvider')]
    public function test_index_filters_by_status_berlaku(string $filter, string $expectedNomor): void
    {
        $this->seedQuotationsWithKontrakSelesai();

        $response = $this->getJson('/api/quotations/list?per_page=50&status_berlaku='.$filter);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor', $expectedNomor);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function statusBerlakuFilterProvider(): array
    {
        return [
            'kontrak habis' => ['kontrak_habis', 'Q-HABIS'],
            'berakhir 2 bulan' => ['berakhir_2_bulan', 'Q-2BLN'],
            'berakhir 3 bulan' => ['berakhir_3_bulan', 'Q-3BLN'],
            'lebih 3 bulan' => ['lebih_3_bulan', 'Q-LEBIH'],
        ];
    }

    public function test_index_ignores_unknown_status_berlaku_value(): void
    {
        $this->seedQuotationsWithKontrakSelesai();

        $response = $this->getJson('/api/quotations/list?per_page=50&status_berlaku=bogus');

        $response->assertOk()->assertJsonCount(5, 'data');
    }

    /**
     * Seeds one quotation per status_berlaku bucket plus one without kontrak_selesai.
     */
    private function seedQuotationsWithKontrakSelesai(): void
    {
        DB::table('m_status_quotation')->insert(['id' => 1, 'nama' => 'Draft']);

        $rows = [
            ['Q-HABIS', now()->subDay()->toDateString()],
            ['Q-2BLN', now()->addDays(30)->toDateString()],
            ['Q-3BLN', now()->addDays(75)->toDateString()],
            ['Q-LEBIH', now()->addDays(200)->toDateString()],
            ['Q-NULL', null],
        ];

        foreach ($rows as $index => [$nomor, $kontrakSelesai]) {
            DB::table('sl_quotation')->insert([
                'id' => $index + 1,
                'leads_id' => 10,
                'nomor' => $nomor,
                'step' => 12,
                'jumlah_site' => 'Single Site',
                'company_id' => 5,
                'company' => 'PT ION',
                'kebutuhan' => 'Security',
                'kebutuhan_id' => 3,
                'nama_perusahaan' => 'PT Example',
                'tgl_quotation' => now()->toDateString(),
                'mulai_kontrak' => now()->toDateString(),
                'kontrak_selesai' => $kontrakSelesai,
                'status_quotation_id' => 1,
                'jenis_kontrak' => 'TERPADU',
                'created_by' => 'Tester',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_reference_validation_returns_422_baserequest_shape(): void
    {
        // Missing/invalid tipe_quotation → rejected by QuotationReferenceRequest
        // BEFORE the controller runs. BaseRequest 422 shape: { message: { field: [..] } }.
        $response = $this->getJson('/api/quotations/reference/1?tipe_quotation=invalid');

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['tipe_quotation']]);
    }

    public function test_available_leads_invalid_tipe_returns_400(): void
    {
        $response = $this->getJson('/api/quotations/available-leads/bogus');

        $response->assertStatus(400)
            ->assertExactJson([
                'success' => false,
                'message' => 'Parameter tipe_quotation harus diisi dengan nilai: baru, revisi, rekontrak, atau addendum',
            ]);
    }

    public function test_store_skipped_calculation_graph(): void
    {
        $this->markTestSkipped('store builds a full QuotationResource (calculation graph) and dispatches QuotationCreated events/jobs; envelope left bespoke (QuotationResource + metadata) intentionally.');
    }

    public function test_show_skipped_calculation_graph(): void
    {
        $this->markTestSkipped('show returns new QuotationResource($quotation) which computes the quotation; Resource return left untouched, only the dead try/catch->500 was removed.');
    }

    public function test_destroy_skipped_relation_cascade(): void
    {
        $this->markTestSkipped('destroy cascades soft-deletes over 14 relation tables via QuotationBusinessService; envelope converted to messageResponse + DB::transaction closure.');
    }

    public function test_submit_and_reset_approval_skipped(): void
    {
        $this->markTestSkipped('submit/reset approval mutate approval state and trigger notifications/escalation jobs; success envelopes converted to successResponse.');
    }

    private function rebuildSchema(): void
    {
        foreach (['m_user', 'm_status_quotation', 'sl_quotation', 'sl_quotation_site'] as $table) {
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

        Schema::create('m_status_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nomor')->nullable();
            $table->integer('step')->nullable();
            $table->string('jumlah_site')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->string('company')->nullable();
            $table->string('kebutuhan')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->date('tgl_quotation')->nullable();
            $table->date('mulai_kontrak')->nullable();
            $table->date('kontrak_selesai')->nullable();
            $table->unsignedInteger('status_quotation_id')->nullable();
            $table->string('jenis_kontrak')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
