<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\QuotationSite;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization test: locks the exact JSON response shape of
 * SiteController BEFORE/AFTER refactoring it onto the ApiResponser trait.
 * SiteController is read-only (list / view / availableCustomer) so there is
 * no validation-422 path and no write path — the envelope (keys present,
 * status codes, success/data payload) must stay identical.
 */
class SiteControllerTest extends TestCase
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
            'cais_role_id' => 2, // superadmin: applyUserFilters applies no filter
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
        $leads = Leads::query()->create([
            'nama_perusahaan' => 'PT. Contoh Perusahaan',
            'customer_id' => 10,
            'tgl_leads' => '2024-01-15',
        ]);

        QuotationSite::query()->create([
            'leads_id' => $leads->id,
            'nama_site' => 'Site Jakarta',
            'provinsi' => 'DKI Jakarta',
            'kota' => 'Jakarta Pusat',
            'penempatan' => 'Gedung Lt. 5',
            'created_by' => 1,
        ]);

        $response = $this->getJson('/api/site/list');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_perusahaan', 'PT. Contoh Perusahaan')
            ->assertJsonPath('data.0.nama_site', 'Site Jakarta')
            ->assertJsonPath('data.0.provinsi', 'DKI Jakarta')
            ->assertJsonPath('data.0.kota', 'Jakarta Pusat')
            ->assertJsonPath('data.0.penempatan', 'Gedung Lt. 5')
            ->assertJsonPath('data.0.spk', false)
            ->assertJsonPath('data.0.kontrak', false);

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_found_returns_success_data(): void
    {
        $leads = Leads::query()->create([
            'nama_perusahaan' => 'PT. Detail',
            'customer_id' => 20,
            'tgl_leads' => '2024-01-15',
        ]);

        CustomerActivity::query()->create([
            'leads_id' => $leads->id,
            'tgl_activity' => '2024-01-16',
            'notes' => 'Meeting dengan client',
            'tipe' => 'meeting',
        ]);

        $response = $this->getJson("/api/site/view/{$leads->id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.leads.nama_perusahaan', 'PT. Detail')
            ->assertJsonPath('data.activity.0.notes', 'Meeting dengan client')
            ->assertJsonPath('data.activity.0.tipe', 'meeting')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'leads',
                    'activity',
                    'master_data' => ['branch', 'jabatan_pic', 'jenis_perusahaan', 'kebutuhan', 'platform'],
                ],
            ]);

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        $response = $this->getJson('/api/site/view/999');

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Site not found',
            ]);
    }

    public function test_view_without_customer_id_returns_404(): void
    {
        // Leads without customer_id is excluded by whereNotNull('customer_id')
        $leads = Leads::query()->create([
            'nama_perusahaan' => 'PT. Tanpa Customer',
            'tgl_leads' => '2024-01-15',
        ]);

        $response = $this->getJson("/api/site/view/{$leads->id}");

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Site not found',
            ]);
    }

    public function test_available_customer_returns_success_data_envelope(): void
    {
        // Locked with no leads: eager loading is skipped so the response is a
        // clean { success: true, data: [] } envelope.
        $response = $this->getJson('/api/site/available-customer');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(0, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'sl_leads', 'sl_quotation_site', 'sl_spk_site', 'sl_site',
            'sl_customer_activity', 'm_jabatan_pic', 'm_jenis_perusahaan',
            'm_kebutuhan', 'm_platform', 'm_branch',
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
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('platform_id')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->unsignedInteger('ro_id')->nullable();
            $table->string('ro')->nullable();
            $table->unsignedInteger('crm_id')->nullable();
            $table->string('crm')->nullable();
            $table->date('tgl_leads')->nullable();
            $table->string('pic')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kota')->nullable();
            $table->string('penempatan')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_spk_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_customer_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->date('tgl_activity')->nullable();
            $table->text('notes')->nullable();
            $table->string('tipe')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->unsignedInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['m_jabatan_pic', 'm_jenis_perusahaan', 'm_kebutuhan', 'm_platform'] as $t) {
            Schema::create($t, function (Blueprint $table) {
                $table->increments('id');
                $table->string('nama')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
