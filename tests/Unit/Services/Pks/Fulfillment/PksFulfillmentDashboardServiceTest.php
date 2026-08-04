<?php

namespace Tests\Unit\Services\Pks\Fulfillment;

use App\Services\Pks\Fulfillment\PksFulfillmentDashboardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit test PksFulfillmentDashboardService::recapForPage — rekap item+visit per PKS.
 *
 * @group pks-fulfillment
 */
class PksFulfillmentDashboardServiceTest extends TestCase
{
    private PksFulfillmentDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = $this->tempDbPath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
        touch($databasePath);
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        Config::set('database.connections.mysqlhris', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        foreach (['sqlite', 'mysql', 'mysqlhris'] as $c) {
            DB::purge($c);
            DB::reconnect($c);
        }

        $this->buildSchema();
        $this->seedData();

        $this->service = app(PksFulfillmentDashboardService::class);
    }

    private function buildSchema(): void
    {
        foreach ([
            'sl_pks_visit_schedule', 'sl_pks_visit_target',
            'sl_pks_item_fulfillment', 'sl_quotation_chemical', 'sl_quotation_devices',
            'sl_quotation_kaporlap', 'sl_quotation_detail', 'sl_site',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('sl_quotation_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('jumlah_hc')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // sl_site kosong — HC batched (overallForPksIds) early-return, hc = kosong.
        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['sl_quotation_kaporlap', 'sl_quotation_devices', 'sl_quotation_chemical'] as $t) {
            Schema::create($t, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_id')->nullable();
                $table->unsignedInteger('quotation_detail_id')->nullable();
                $table->unsignedInteger('jumlah')->nullable();
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

        Schema::create('sl_pks_visit_target', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('target_total')->default(0);
            $table->unsignedInteger('target_terpakai')->default(0);
            $table->timestamps();
        });

        Schema::create('sl_pks_visit_schedule', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });
    }

    private function seedData(): void
    {
        // PKS 1 (quotation 11) LENGKAP: item 10/10, visit 4/4
        DB::table('sl_quotation_kaporlap')->insert(['quotation_id' => 11, 'jumlah' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_pks_item_fulfillment')->insert(['pks_id' => 1, 'qty_terpenuhi' => 10, 'status' => 'fully_fulfilled', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_pks_visit_target')->insert(['pks_id' => 1, 'target_total' => 4, 'target_terpakai' => 4, 'created_at' => now(), 'updated_at' => now()]);

        // PKS 2 (quotation 22) BELUM: item 3/10, visit 1/4, 2 missed
        DB::table('sl_quotation_kaporlap')->insert(['quotation_id' => 22, 'jumlah' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_pks_item_fulfillment')->insert(['pks_id' => 2, 'qty_terpenuhi' => 3, 'status' => 'partially_fulfilled', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_pks_visit_target')->insert(['pks_id' => 2, 'target_total' => 4, 'target_terpakai' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_pks_visit_schedule')->insert([
            ['pks_id' => 2, 'status' => 'missed', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => 2, 'status' => 'missed', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** @test */
    public function test_recap_computes_item_and_visit_per_pks(): void
    {
        $rows = collect([
            (object) ['id' => 1, 'quotation_id' => 11],
            (object) ['id' => 2, 'quotation_id' => 22],
        ]);

        $recap = $this->service->recapForPage($rows);

        // PKS 1 lengkap
        $this->assertTrue($recap[1]['is_complete']);
        $this->assertSame(100.0, $recap[1]['item']['persen']);
        $this->assertSame(4, $recap[1]['visit']['target_terpakai']);
        $this->assertSame(0, $recap[1]['visit']['missed']);

        // PKS 2 belum
        $this->assertFalse($recap[2]['is_complete']);
        $this->assertSame(30.0, $recap[2]['item']['persen']); // 3/10
        $this->assertSame(1, $recap[2]['visit']['target_terpakai']);
        $this->assertSame(2, $recap[2]['visit']['missed']);

        // slot hc ada (HRIS/sl_site kosong di test → overall kosong, persen 100)
        $this->assertArrayHasKey('hc', $recap[1]);
        $this->assertSame(0, $recap[1]['hc']['total_vacancy']);
        $this->assertSame(100.0, $recap[1]['hc']['persen']);
    }

    /** @test */
    public function test_recap_empty_page_returns_empty(): void
    {
        $this->assertSame([], $this->service->recapForPage(collect([])));
    }

    /** @test */
    public function test_aggregate_summary_sums_item_and_visit(): void
    {
        $rows = collect([
            (object) ['id' => 1, 'quotation_id' => 11],
            (object) ['id' => 2, 'quotation_id' => 22],
        ]);
        $recap = $this->service->recapForPage($rows);

        $summary = $this->service->aggregateSummary($recap);

        // hanya item + visit (tanpa blok pks)
        $this->assertArrayNotHasKey('pks', $summary);

        // item agregat: diminta 10+10=20, terpenuhi 10+3=13 → 65%
        $this->assertSame(20, $summary['item']['qty_diminta']);
        $this->assertSame(13, $summary['item']['qty_terpenuhi']);
        $this->assertSame(65.0, $summary['item']['persen']);

        // visit agregat: target 4+4=8, terpakai 4+1=5 → 62.5%; missed 2
        $this->assertSame(8, $summary['visit']['target_total']);
        $this->assertSame(5, $summary['visit']['target_terpakai']);
        $this->assertSame(62.5, $summary['visit']['persen']);
        $this->assertSame(2, $summary['visit']['missed']);

        // hc agregat ada (kosong di test)
        $this->assertArrayHasKey('hc', $summary);
        $this->assertSame(0, $summary['hc']['target_kebutuhan']);
    }

    protected ?string $tempDbPath = null;

    protected function tearDown(): void
    {
        foreach (['sqlite', 'mysql', 'mysqlhris'] as $connection) {
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
