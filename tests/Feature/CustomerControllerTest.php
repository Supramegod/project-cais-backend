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
 * Characterization test: locks the exact JSON response shape of
 * CustomerController (list / view / availableCustomer) BEFORE refactoring
 * it onto the ApiResponser trait. The envelope (keys present, status codes,
 * pagination shape) must stay identical.
 */
class CustomerControllerTest extends TestCase
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
            // role 2 = superadmin -> Leads::scopeFilterByUserRole returns unfiltered
            'cais_role_id' => 2,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);
    }

    private function seedLead(array $overrides = []): int
    {
        $today = now()->toDateString();

        return DB::table('sl_leads')->insertGetId(array_merge([
            'nomor' => 'LD-001',
            'branch_id' => 1,
            'tgl_leads' => $today,
            'tim_sales_id' => null,
            'tim_sales_d_id' => null,
            'nama_perusahaan' => 'PT Contoh',
            'telp_perusahaan' => '021000',
            'provinsi' => 'Jawa Barat',
            'kota' => 'Bandung',
            'no_telp' => '0812345',
            'email' => 'contoh@example.com',
            'status_leads_id' => 102,
            'platform_id' => 1,
            'created_by' => 1,
            'notes' => 'catatan',
            'customer_id' => null,
            'kebutuhan_id' => 1,
            'jenis_perusahaan_id' => 1,
            'ro' => 'RO-1',
            'crm' => 'CRM-1',
            'pic' => 'John Doe',
            'jabatan' => 1,
            'alamat' => 'Jl. Contoh',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ], $overrides));
    }

    public function test_list_returns_success_data_pagination_envelope(): void
    {
        $this->seedLead(['nama_perusahaan' => 'PT Satu']);
        $this->seedLead(['nama_perusahaan' => 'PT Dua']);
        // Excluded: already a customer / wrong status.
        $this->seedLead(['nama_perusahaan' => 'PT Tiga', 'customer_id' => 99]);
        $this->seedLead(['nama_perusahaan' => 'PT Empat', 'status_leads_id' => 1]);

        $response = $this->getJson('/api/customer/list');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Data leads berhasil diambil',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    ['id', 'nomor', 'wilayah', 'wilayah_id', 'tgl_leads', 'sales',
                     'nama_perusahaan', 'status_leads', 'sumber_leads', 'kebutuhan'],
                ],
                'pagination' => ['current_page', 'last_page', 'total', 'total_per_page'],
            ])
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('pagination.current_page', 1);
    }

    public function test_view_found_returns_customer_and_activity(): void
    {
        $id = $this->seedLead(['customer_id' => 55]);

        $response = $this->getJson("/api/customer/view/{$id}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.customer.id', $id)
            ->assertJsonCount(0, 'data.activity');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    public function test_view_not_found_returns_404_with_message(): void
    {
        // Lead without customer_id must not be viewable via this endpoint.
        $id = $this->seedLead(['customer_id' => null]);

        $response = $this->getJson("/api/customer/view/{$id}");

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Customer not found',
            ]);
    }

    public function test_available_empty_returns_success_empty_data(): void
    {
        // No customer rows -> map runs over an empty collection, no bug hit.
        $this->seedLead(['customer_id' => null, 'nama_perusahaan' => 'PT Lead']);

        $response = $this->getJson('/api/customer/available');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(0, 'data');

        $this->assertArrayNotHasKey('message', $response->json());
    }

    /**
     * Regression fix: availableCustomer previously threw once any customer row
     * existed — `$lead->kebutuhan->nama` on a belongsToMany Collection — and the
     * try/catch turned it into a 500. Now it returns 200 with the kebutuhan
     * names joined.
     */
    public function test_available_with_data_returns_joined_kebutuhan(): void
    {
        $leadId = $this->seedLead(['customer_id' => 55, 'nama_perusahaan' => 'PT Cust A']);

        $cs = DB::table('m_kebutuhan')->insertGetId(['nama' => 'Cleaning Service']);
        $sec = DB::table('m_kebutuhan')->insertGetId(['nama' => 'Security']);
        DB::table('sl_leads_kebutuhan')->insert([
            ['leads_id' => $leadId, 'kebutuhan_id' => $cs, 'deleted_at' => null],
            ['leads_id' => $leadId, 'kebutuhan_id' => $sec, 'deleted_at' => null],
        ]);

        $response = $this->getJson('/api/customer/available');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_perusahaan', 'PT Cust A')
            ->assertJsonPath('data.0.kebutuhan', 'Cleaning Service, Security');
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'sl_leads', 'm_status_leads', 'm_branch', 'm_platform',
            'm_kebutuhan', 'sl_leads_kebutuhan', 'sl_customer_activity',
            'm_tim_sales_d', 'm_tim_sales', 'm_jenis_perusahaan', 'm_jabatan_pic',
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
            $table->unsignedInteger('branch_id')->nullable();
            $table->date('tgl_leads')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('telp_perusahaan')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kota')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->unsignedInteger('platform_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('jenis_perusahaan_id')->nullable();
            $table->string('ro')->nullable();
            $table->string('crm')->nullable();
            $table->unsignedInteger('ro_id')->nullable();
            $table->unsignedInteger('crm_id')->nullable();
            $table->string('pic')->nullable();
            $table->unsignedInteger('jabatan')->nullable();
            $table->string('alamat')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('m_status_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('warna_background')->nullable();
            $table->string('warna_font')->nullable();
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

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('sl_leads_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('tim_sales_d_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('sl_customer_activity', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->date('tgl_activity')->nullable();
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('m_tim_sales_d', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('tim_sales_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_tim_sales', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_jenis_perusahaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        Schema::create('m_jabatan_pic', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
        });

        // Reference rows for eager-loaded relations.
        DB::table('m_status_leads')->insert(['id' => 102, 'nama' => 'Customer', 'warna_background' => '#28a745', 'warna_font' => '#fff']);
        DB::table('m_branch')->insert(['id' => 1, 'name' => 'Jakarta']);
        DB::table('m_platform')->insert(['id' => 1, 'nama' => 'Website']);
        DB::table('m_kebutuhan')->insert(['id' => 1, 'nama' => 'Security']);
        DB::table('m_jenis_perusahaan')->insert(['id' => 1, 'nama' => 'PT']);
        DB::table('m_jabatan_pic')->insert(['id' => 1, 'nama' => 'Manager']);
    }
}
