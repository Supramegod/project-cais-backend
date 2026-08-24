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
 * Feature test dashboard item fulfillment PKS.
 *
 * @group pks-fulfillment
 */
class PksItemFulfillmentDashboardTest extends TestCase
{
    private const ENDPOINT = '/api/pks-fulfillment/item-dashboard';

    protected ?string $tempDbPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = $this->tempDbPath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
        touch($databasePath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.connections.mysqlhris', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));

        foreach (['sqlite', 'mysqlhris', 'mysql'] as $c) {
            DB::purge($c);
            DB::reconnect($c);
        }

        $this->rebuildSchema();

        DB::connection('mysqlhris')->table('m_user')->insert([
            'id' => 1,
            'username' => 'tester',
            'password' => bcrypt('secret'),
            'full_name' => 'Test User',
            'email' => 'tester@example.com',
            'cais_role_id' => 8,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(1), 'web');
        $this->withoutMiddleware(CheckTokenExpiry::class);

        $this->seedData();
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_pks_item_request', 'sl_pks_item_fulfillment', 'sl_quotation_chemical',
            'sl_quotation_devices', 'sl_quotation_kaporlap', 'sl_quotation_detail',
            'sl_quotation_site', 'sl_pks', 'sl_leads', 'm_user',
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

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('branch_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nomor')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->date('tgl_pks')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_kaporlap', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['sl_quotation_devices', 'sl_quotation_chemical'] as $t) {
            Schema::create($t, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_id')->nullable();
                $table->unsignedInteger('quotation_site_id')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        Schema::create('sl_pks_item_fulfillment', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('qty_terpenuhi')->default(0);
            $table->unsignedInteger('qty_request')->default(0);
            $table->string('status')->default('not_yet_fulfilled');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks_item_request', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    /**
     * PKS 1 & 2 aktif (status 7). PKS 3 status lain — tidak boleh muncul.
     * PKS 2 sengaja tanpa nama_perusahaan & created_by untuk menguji fallback.
     */
    private function seedData(): void
    {
        DB::table('sl_leads')->insert([
            ['id' => 1, 'branch_id' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'branch_id' => 2, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_pks')->insert([
            ['id' => 1, 'leads_id' => 1, 'quotation_id' => 11, 'nomor' => 'PKS-001', 'nama_perusahaan' => 'PT Alpha', 'tgl_pks' => today()->toDateString(), 'initialized_at' => now(), 'status_pks_id' => 7, 'created_by' => 'Sales Satu', 'created_by_user_id' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'leads_id' => 2, 'quotation_id' => 22, 'nomor' => 'PKS-002', 'nama_perusahaan' => null, 'tgl_pks' => today()->toDateString(), 'initialized_at' => now(), 'status_pks_id' => 7, 'created_by' => null, 'created_by_user_id' => null, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'leads_id' => 1, 'quotation_id' => 33, 'nomor' => 'PKS-003', 'nama_perusahaan' => 'PT Gamma', 'tgl_pks' => today()->toDateString(), 'initialized_at' => now(), 'status_pks_id' => 2, 'created_by' => 'Sales Dua', 'created_by_user_id' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_site')->insert([
            ['id' => 1, 'quotation_id' => 11, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'quotation_id' => 22, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'quotation_id' => 33, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_detail')->insert([
            ['id' => 1, 'quotation_id' => 11, 'quotation_site_id' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'quotation_id' => 22, 'quotation_site_id' => 2, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'quotation_id' => 33, 'quotation_site_id' => 3, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_kaporlap')->insert([
            ['quotation_id' => 11, 'quotation_detail_id' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 11, 'quotation_detail_id' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 22, 'quotation_detail_id' => 2, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 33, 'quotation_detail_id' => 3, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_devices')->insert([
            ['quotation_id' => 11, 'quotation_site_id' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 22, 'quotation_site_id' => 2, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_pks_item_fulfillment')->insert([
            ['pks_id' => 1, 'qty_terpenuhi' => 4, 'status' => 'fully_fulfilled', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'qty_terpenuhi' => 1, 'status' => 'partially_fulfilled', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_pks_item_request')->insert([
            ['pks_id' => 1, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'status' => 'received', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** @test */
    public function test_endpoint_mengembalikan_envelope_lengkap(): void
    {
        $response = $this->getJson(self::ENDPOINT);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'summary' => ['total_request', 'total_receive', 'total_fulfilled', 'total_remaining'],
                'data' => [['id', 'nomor', 'nama_perusahaan', 'jumlah_request', 'jumlah_receive', 'created_by']],
                'pagination' => ['current_page', 'last_page', 'total', 'total_per_page'],
                'meta' => ['tgl_dari', 'tgl_sampai'],
            ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function test_hanya_pks_aktif_yang_tampil(): void
    {
        $nomor = collect($this->getJson(self::ENDPOINT)->json('data'))->pluck('nomor')->all();

        $this->assertContains('PKS-001', $nomor);
        $this->assertContains('PKS-002', $nomor);
        $this->assertNotContains('PKS-003', $nomor);
    }

    /** @test */
    public function test_angka_per_baris_sesuai_semantik_jumlah_baris(): void
    {
        $data = collect($this->getJson(self::ENDPOINT)->json('data'))->keyBy('nomor');

        $this->assertSame(2, $data['PKS-001']['jumlah_request']);
        $this->assertSame(2, $data['PKS-001']['jumlah_receive']);

        $this->assertSame(0, $data['PKS-002']['jumlah_request']);
        $this->assertSame(0, $data['PKS-002']['jumlah_receive']);
    }

    /** @test */
    public function test_nama_perusahaan_dan_created_by_null_jadi_strip(): void
    {
        $data = collect($this->getJson(self::ENDPOINT)->json('data'))->keyBy('nomor');

        $this->assertSame('PT Alpha', $data['PKS-001']['nama_perusahaan']);
        $this->assertSame('Sales Satu', $data['PKS-001']['created_by']);
        $this->assertSame('-', $data['PKS-002']['nama_perusahaan']);
        $this->assertSame('-', $data['PKS-002']['created_by']);
    }

    /** @test */
    public function test_summary_mencakup_seluruh_himpunan_terfilter_bukan_halaman(): void
    {
        $satuHalaman = $this->getJson(self::ENDPOINT.'?per_page=1');

        $satuHalaman->assertOk();
        $this->assertCount(1, $satuHalaman->json('data'));
        $this->assertSame(2, $satuHalaman->json('pagination.total'));

        $this->assertSame(2, $satuHalaman->json('summary.total_request'));
        $this->assertSame(2, $satuHalaman->json('summary.total_receive'));
        $this->assertSame(1, $satuHalaman->json('summary.total_fulfilled'));
        $this->assertSame(4, $satuHalaman->json('summary.total_remaining'));

        $semua = $this->getJson(self::ENDPOINT.'?per_page=50');
        $this->assertSame($satuHalaman->json('summary'), $semua->json('summary'));
    }

    /** @test */
    public function test_search_by_nomor_menyaring_daftar(): void
    {
        $data = $this->getJson(self::ENDPOINT.'?search=PKS-001&search_by=nomor')->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('PKS-001', $data[0]['nomor']);
    }

    /** @test */
    public function test_filter_branch_menyaring_lewat_leads(): void
    {
        $data = $this->getJson(self::ENDPOINT.'?branch=2')->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('PKS-002', $data[0]['nomor']);
    }

    /** @test */
    public function test_route_tidak_tertangkap_binding_pks(): void
    {
        $this->getJson(self::ENDPOINT)->assertOk();
    }

    /** @test */
    public function test_jumlah_query_konstan_terhadap_per_page(): void
    {
        $hitung = function (string $url): int {
            $jumlah = 0;
            DB::listen(function () use (&$jumlah) {
                $jumlah++;
            });
            $this->getJson($url)->assertOk();

            return $jumlah;
        };

        $satuBaris = $hitung(self::ENDPOINT.'?per_page=1');

        $this->assertGreaterThan(0, $satuBaris);
        $this->assertSame($satuBaris, $hitung(self::ENDPOINT.'?per_page=50'));
    }

    protected function tearDown(): void
    {
        foreach (['sqlite', 'mysqlhris', 'mysql'] as $connection) {
            try {
                DB::purge($connection);
            } catch (\Throwable $e) {
                // koneksi mungkin tidak terdaftar — abaikan
            }
        }

        if ($this->tempDbPath && file_exists($this->tempDbPath)) {
            @unlink($this->tempDbPath);
        }

        parent::tearDown();
    }
}
