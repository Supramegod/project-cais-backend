<?php

namespace Tests\Unit\Services\Pks\Fulfillment;

use App\Services\Pks\Fulfillment\ItemFulfillmentDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit test ItemFulfillmentDashboardService — rekap item fulfillment dengan
 * semantik jumlah baris.
 *
 * @group pks-fulfillment
 */
class ItemFulfillmentDashboardServiceTest extends TestCase
{
    private ItemFulfillmentDashboardService $service;

    protected ?string $tempDbPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = $this->tempDbPath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
        touch($databasePath);
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        foreach (['sqlite', 'mysql'] as $c) {
            DB::purge($c);
            DB::reconnect($c);
        }

        $this->buildSchema();
        $this->seedData();

        $this->service = app(ItemFulfillmentDashboardService::class);
    }

    private function buildSchema(): void
    {
        foreach ([
            'sl_pks_item_request', 'sl_pks_item_fulfillment', 'sl_quotation_chemical',
            'sl_quotation_devices', 'sl_quotation_kaporlap', 'sl_quotation_detail',
            'sl_quotation_site',
        ] as $t) {
            Schema::dropIfExists($t);
        }

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
     * PKS 1 (quotation 11) — semesta 4 item (2 kaporlap, 1 device, 1 chemical),
     * 2 fully_fulfilled, 3 baris pernah diterima, 2 request masih open.
     *
     * PKS 2 (quotation 22) — semesta 3 item, belum ada aktivitas sama sekali.
     *
     * PKS 3 (quotation 33) — semesta 1 item valid; 2 baris lain yatim (induknya
     * soft-deleted) dan tidak boleh ikut terhitung.
     */
    private function seedData(): void
    {
        DB::table('sl_quotation_site')->insert([
            ['id' => 1, 'quotation_id' => 11, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'quotation_id' => 22, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'quotation_id' => 33, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'quotation_id' => 33, 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_detail')->insert([
            ['id' => 1, 'quotation_id' => 11, 'quotation_site_id' => 1, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'quotation_id' => 22, 'quotation_site_id' => 2, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'quotation_id' => 33, 'quotation_site_id' => 3, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'quotation_id' => 33, 'quotation_site_id' => 3, 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_kaporlap')->insert([
            ['quotation_id' => 11, 'quotation_detail_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 11, 'quotation_detail_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 22, 'quotation_detail_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 33, 'quotation_detail_id' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 33, 'quotation_detail_id' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_devices')->insert([
            ['quotation_id' => 11, 'quotation_site_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 22, 'quotation_site_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 33, 'quotation_site_id' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_chemical')->insert([
            ['quotation_id' => 11, 'quotation_site_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['quotation_id' => 22, 'quotation_site_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_pks_item_fulfillment')->insert([
            ['pks_id' => 1, 'qty_terpenuhi' => 5, 'status' => 'fully_fulfilled', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'qty_terpenuhi' => 3, 'status' => 'fully_fulfilled', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'qty_terpenuhi' => 2, 'status' => 'partially_fulfilled', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'qty_terpenuhi' => 0, 'status' => 'requested', 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'qty_terpenuhi' => 9, 'status' => 'fully_fulfilled', 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_pks_item_request')->insert([
            ['pks_id' => 1, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'status' => 'received', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 1, 'status' => 'short', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** @test */
    public function test_per_pks_counts_rows_not_quantities(): void
    {
        $hasil = $this->service->perPks([1 => 11]);

        $this->assertSame(2, $hasil[1]['jumlah_request']);
        $this->assertSame(3, $hasil[1]['jumlah_receive']);
        $this->assertSame(2, $hasil[1]['jumlah_fulfilled']);
        $this->assertSame(4, $hasil[1]['total_item']);
        $this->assertSame(2, $hasil[1]['jumlah_remaining']);
    }

    /** @test */
    public function test_pks_tanpa_aktivitas_tetap_punya_remaining(): void
    {
        $hasil = $this->service->perPks([2 => 22]);

        $this->assertSame(0, $hasil[2]['jumlah_request']);
        $this->assertSame(0, $hasil[2]['jumlah_receive']);
        $this->assertSame(0, $hasil[2]['jumlah_fulfilled']);
        $this->assertSame(3, $hasil[2]['total_item']);
        $this->assertSame(3, $hasil[2]['jumlah_remaining']);
    }

    /** @test */
    public function test_baris_yatim_tidak_masuk_semesta(): void
    {
        $hasil = $this->service->perPks([3 => 33]);

        $this->assertSame(1, $hasil[3]['total_item']);
        $this->assertSame(1, $hasil[3]['jumlah_remaining']);
    }

    /** @test */
    public function test_remaining_dijepit_nol_saat_fulfilled_melebihi_semesta(): void
    {
        DB::table('sl_pks_item_fulfillment')->insert([
            ['pks_id' => 4, 'qty_terpenuhi' => 1, 'status' => 'fully_fulfilled', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 4, 'qty_terpenuhi' => 1, 'status' => 'fully_fulfilled', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $hasil = $this->service->perPks([4 => 33]);

        $this->assertSame(2, $hasil[4]['jumlah_fulfilled']);
        $this->assertSame(1, $hasil[4]['total_item']);
        $this->assertSame(0, $hasil[4]['jumlah_remaining']);
    }

    /** @test */
    public function test_pks_tanpa_quotation_tidak_error(): void
    {
        $hasil = $this->service->perPks([5 => null]);

        $this->assertSame(0, $hasil[5]['total_item']);
        $this->assertSame(0, $hasil[5]['jumlah_remaining']);
        $this->assertSame(0, $hasil[5]['jumlah_request']);
    }

    /** @test */
    public function test_per_pks_kosong_mengembalikan_array_kosong(): void
    {
        $this->assertSame([], $this->service->perPks([]));
    }

    /** @test */
    public function test_summary_menjumlah_seluruh_pks(): void
    {
        $perPks = $this->service->perPks([1 => 11, 2 => 22, 3 => 33]);

        $summary = $this->service->summaryFor($perPks);

        $this->assertSame(2, $summary['total_request']);
        $this->assertSame(3, $summary['total_receive']);
        $this->assertSame(2, $summary['total_fulfilled']);
        $this->assertSame(6, $summary['total_remaining']);
    }

    /** @test */
    public function test_summary_kosong_mengembalikan_empat_nol(): void
    {
        $this->assertSame([
            'total_request' => 0,
            'total_receive' => 0,
            'total_fulfilled' => 0,
            'total_remaining' => 0,
        ], $this->service->summaryFor([]));
    }

    protected function tearDown(): void
    {
        foreach (['sqlite', 'mysql'] as $connection) {
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
