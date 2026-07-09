<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\Leads;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the JSON response envelope of LeadsController
 * BEFORE/AFTER the ApiResponser + FormRequest + closure-transaction refactor.
 *
 * The 3-connection wiring (sqlite / mysql / mysqlhris all pointed at the same
 * temp sqlite file) mirrors BentukUsahaControllerTest so cross-connection
 * models resolve against one schema.
 */
class LeadsControllerTest extends TestCase
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
            'cais_role_id' => 2, // superadmin: filterByUserRole applies no extra filter
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    /** Helper: insert a lead row directly and return its id. */
    private function seedLead(array $overrides = []): int
    {
        return DB::table('sl_leads')->insertGetId(array_merge([
            'nomor' => 'AAAAA',
            'nama_perusahaan' => 'PT ALPHA',
            'branch_id' => null,
            'platform_id' => null,
            'status_leads_id' => 1,
            'tgl_leads' => now()->toDateTimeString(),
            'created_by' => 'Tester',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ], $overrides));
    }

    // ---------------------------------------------------------------------
    // list — custom pagination envelope (kept as raw response()->json)
    // ---------------------------------------------------------------------
    public function test_list_returns_success_with_custom_pagination_block(): void
    {
        // tgl_leads a few days back so it sits inside the default 6-month window
        // (upper bound is a date-only string, so a same-day datetime falls outside).
        $tgl = now()->subDays(2)->toDateTimeString();
        $this->seedLead(['nomor' => 'AAAAA', 'nama_perusahaan' => 'PT ALPHA', 'tgl_leads' => $tgl]);
        $this->seedLead(['nomor' => 'AAAAB', 'nama_perusahaan' => 'PT BETA', 'tgl_leads' => $tgl]);

        $response = $this->getJson('/api/leads/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data leads berhasil diambil',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination' => ['current_page', 'last_page', 'total', 'total_per_page'],
            ]);

        $this->assertSame(2, $response->json('pagination.total'));
    }

    // ---------------------------------------------------------------------
    // view — found / 404
    // ---------------------------------------------------------------------
    public function test_view_found_returns_success_data(): void
    {
        $id = $this->seedLead(['nama_perusahaan' => 'PT VIEWME']);

        $response = $this->getJson("/api/leads/view/{$id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Detail lead berhasil diambil',
            ])
            ->assertJsonPath('data.nama_perusahaan', 'PT VIEWME');
    }

    public function test_view_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/leads/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Lead tidak ditemukan',
            ]);
    }

    // ---------------------------------------------------------------------
    // childLeads — simple sl_leads-only query
    // ---------------------------------------------------------------------
    public function test_child_leads_returns_parent_and_children(): void
    {
        $parent = $this->seedLead(['nomor' => 'AAAAA', 'nama_perusahaan' => 'PT PARENT']);
        $this->seedLead(['nomor' => 'AAAAB', 'nama_perusahaan' => 'PT CHILD', 'leads_id' => $parent]);

        $response = $this->getJson("/api/leads/child/{$parent}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data child leads berhasil diambil',
            ])
            ->assertJsonCount(2, 'data');
    }

    // ---------------------------------------------------------------------
    // delete — found / 404 (manual transaction path)
    // ---------------------------------------------------------------------
    public function test_delete_found_returns_message(): void
    {
        $id = $this->seedLead(['nama_perusahaan' => 'PT HAPUS']);

        $response = $this->deleteJson("/api/leads/delete/{$id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Leads PT HAPUS berhasil dihapus beserta kebutuhan terkait',
            ]);

        $this->assertSoftDeleted('sl_leads', ['id' => $id]);
    }

    public function test_delete_not_found_returns_404(): void
    {
        $response = $this->deleteJson('/api/leads/delete/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Lead tidak ditemukan',
            ]);
    }

    // ---------------------------------------------------------------------
    // restore — soft-deleted lead (manual transaction path)
    // ---------------------------------------------------------------------
    public function test_restore_found_returns_message(): void
    {
        $id = $this->seedLead(['nama_perusahaan' => 'PT RESTORE', 'deleted_at' => now()->toDateTimeString()]);

        $response = $this->postJson("/api/leads/restore/{$id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Leads PT RESTORE berhasil direstore beserta kebutuhan terkait',
            ]);

        $this->assertDatabaseHas('sl_leads', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_restore_not_found_returns_404(): void
    {
        $response = $this->postJson('/api/leads/restore/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Lead tidak ditemukan',
            ]);
    }

    // ---------------------------------------------------------------------
    // activateLead — found / 404 (manual transaction path)
    // ---------------------------------------------------------------------
    public function test_activate_found_returns_message(): void
    {
        $id = $this->seedLead(['nama_perusahaan' => 'PT AKTIF']);

        $response = $this->postJson("/api/leads/activate/{$id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Lead PT AKTIF berhasil diaktifkan',
            ]);

        $this->assertDatabaseHas('sl_leads', ['id' => $id, 'is_aktif' => 1]);
    }

    public function test_activate_not_found_returns_404(): void
    {
        $response = $this->postJson('/api/leads/activate/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Lead tidak ditemukan',
            ]);
    }

    // ---------------------------------------------------------------------
    // add — validation 422 (BaseRequest shape)
    // ---------------------------------------------------------------------
    public function test_add_validation_error_returns_422_baserequest_shape(): void
    {
        $response = $this->postJson('/api/leads/add', []);

        // BaseRequest: { message: { field: [..] } }
        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['nama_perusahaan']]);
    }

    // ---------------------------------------------------------------------
    // getXByLead — 404 guards (return before touching downstream tables)
    // ---------------------------------------------------------------------
    public function test_get_spk_by_lead_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/leads/spk/999');
        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Lead tidak ditemukan']);
    }

    public function test_get_pks_by_lead_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/leads/pks/999');
        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Lead tidak ditemukan']);
    }

    public function test_get_quotation_by_lead_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/leads/quotation/999');
        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Lead tidak ditemukan']);
    }

    public function test_get_sales_kebutuhan_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/leads/sales-kebutuhan/999');
        $response->assertStatus(404)
            ->assertExactJson(['success' => false, 'message' => 'Lead tidak ditemukan']);
    }

    // ---------------------------------------------------------------------
    // listTerhapus / leadsBelumAktif — simple list envelopes
    // ---------------------------------------------------------------------
    public function test_list_terhapus_returns_success(): void
    {
        $this->seedLead(['nama_perusahaan' => 'PT TRASH', 'deleted_at' => now()->toDateTimeString()]);

        $response = $this->getJson('/api/leads/deleted');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data leads terhapus berhasil diambil',
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_leads_belum_aktif_returns_success(): void
    {
        $this->seedLead(['nama_perusahaan' => 'PT BELUM', 'is_aktif' => null]);

        $response = $this->getJson('/api/leads/belum-aktif');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data leads belum aktif berhasil diambil',
            ]);
    }

    // ---------------------------------------------------------------------
    // Heavy endpoints skipped: multi-table graph writes / cross-connection
    // lookups / file IO that are out of scope for the envelope refactor.
    // ---------------------------------------------------------------------
    public function test_heavy_endpoints_are_out_of_scope(): void
    {
        $this->markTestSkipped(
            'add/update happy paths (Province/City/District/Village/Benua/Negara + LeadsPic + '.
            'CustomerActivity + sales assignment graph), saveChildLeads happy, assignSales/removeSales '.
            '(exists rules + activity), availableSales (role graph), import/exportExcel/templateImport '.
            '(file IO), and generateNullKode are not seed-reasonable here and are validated at '.
            'integration level. Their envelopes are covered by the converted-in-place helpers.'
        );
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'sl_leads', 'sl_leads_kebutuhan', 'sl_leads_pic', 'm_status_leads',
            'm_branch', 'm_platform', 'm_tim_sales', 'm_tim_sales_d', 'm_kebutuhan',
            'm_jenis_perusahaan', 'm_company', 'sl_perusahaan_groups_d', 'm_jabatan_pic',
        ] as $t) {
            Schema::dropIfExists($t);
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

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('kebutuhan_id')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('jenis_perusahaan_id')->nullable();
            $table->unsignedInteger('bidang_perusahaan_id')->nullable();
            $table->unsignedInteger('platform_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('telp_perusahaan')->nullable();
            $table->string('jenis_perusahaan')->nullable();
            $table->string('bentuk_usaha')->nullable();
            $table->string('bidang_perusahaan')->nullable();
            $table->text('alamat')->nullable();
            $table->string('pic')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->string('pma')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('provinsi_id')->nullable();
            $table->string('provinsi')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('kota')->nullable();
            $table->unsignedBigInteger('kecamatan_id')->nullable();
            $table->string('kecamatan')->nullable();
            $table->unsignedBigInteger('kelurahan_id')->nullable();
            $table->string('kelurahan')->nullable();
            $table->unsignedInteger('benua_id')->nullable();
            $table->string('benua')->nullable();
            $table->unsignedInteger('negara_id')->nullable();
            $table->string('negara')->nullable();
            $table->tinyInteger('is_aktif')->nullable();
            $table->tinyInteger('customer_active')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->dateTime('tgl_leads')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_leads_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_leads_pic', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama')->nullable();
            $table->unsignedInteger('jabatan_id')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_kuasa')->nullable();
            $table->string('created_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_status_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_platform', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_tim_sales', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_tim_sales_d', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->boolean('is_leader')->nullable();
            $table->boolean('is_active')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_jenis_perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_company', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_perusahaan_groups_d', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_jabatan_pic', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });
    }
}
