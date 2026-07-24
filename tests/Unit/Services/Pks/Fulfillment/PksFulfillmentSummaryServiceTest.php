<?php

namespace Tests\Unit\Services\Pks\Fulfillment;

use App\Models\Pks;
use App\Services\Pks\Fulfillment\PksFulfillmentSummaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit test PksFulfillmentSummaryService — skema gabungan item + visit.
 *
 * @group pks-fulfillment
 */
class PksFulfillmentSummaryServiceTest extends TestCase
{
    private PksFulfillmentSummaryService $service;
    private int $pksId;
    private int $quotationId;
    private int $leadsId;
    private int $siteId;

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

        $this->service = app(PksFulfillmentSummaryService::class);
    }

    private function buildSchema(): void
    {
        foreach ([
            'sl_pks_visit_schedule', 'sl_pks_visit_target',
            'sl_pks_fulfillment_log', 'sl_pks_item_fulfillment',
            'sl_quotation_chemical', 'sl_quotation_devices', 'sl_quotation_kaporlap',
            'sl_quotation_detail', 'sl_quotation', 'sl_site', 'sl_pks',
            'm_employee', 't_applicant', 'm_vacancy', 'm_position', 'm_site', 'm_branch',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        // Tabel HRIS (kosong) — supaya slot hc (HcFulfillmentService) bisa jalan.
        Schema::create('m_branch', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
        });
        Schema::create('m_site', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('site_id')->nullable();
            $t->unsignedInteger('branch_id')->nullable();
            $t->string('name')->nullable();
        });
        Schema::create('m_position', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name')->nullable();
        });
        Schema::create('m_vacancy', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('site_id')->nullable();
            $t->unsignedInteger('position_id')->nullable();
            $t->string('title')->nullable();
            $t->unsignedInteger('needs')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->unsignedInteger('is_active')->default(1);
        });
        Schema::create('t_applicant', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('vacancy_id')->nullable();
            $t->unsignedInteger('employee_id')->nullable();
            $t->unsignedInteger('is_active')->default(1);
        });
        Schema::create('m_employee', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('status_approval')->nullable();
            $t->string('followup_status')->nullable();
            $t->unsignedInteger('is_active')->default(1);
        });

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->date('kontrak_awal')->nullable();
            $table->date('kontrak_akhir')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->boolean('is_visit_anchor')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('jumlah_hc')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['sl_quotation_kaporlap', 'sl_quotation_devices', 'sl_quotation_chemical'] as $t) {
            Schema::create($t, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_id')->nullable();
                $table->unsignedInteger('quotation_detail_id')->nullable();
                $table->unsignedInteger('quotation_site_id')->nullable();
                $table->unsignedInteger('barang_id')->nullable();
                $table->unsignedInteger('jumlah')->nullable();
                $table->string('nama')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        Schema::create('sl_pks_item_fulfillment', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('item_type');
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('qty_diminta')->default(0);
            $table->unsignedInteger('qty_terpenuhi')->default(0);
            $table->string('status')->default('not_yet_fulfilled');
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks_fulfillment_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('pks_id');
            $table->string('jenis', 32);
            $table->unsignedBigInteger('reference_id');
            $table->string('aksi', 32);
            $table->text('catatan')->nullable();
            $table->json('meta')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sl_pks_visit_target', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->string('role');
            $table->unsignedInteger('kategori_sesuai_hc_id')->nullable();
            $table->unsignedInteger('target_total')->default(0);
            $table->unsignedInteger('target_terpakai')->default(0);
            $table->timestamps();
        });

        Schema::create('sl_pks_visit_schedule', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('role');
            $table->unsignedBigInteger('pic_user_id')->nullable();
            $table->date('tgl_jadwal');
            $table->date('tgl_jadwal_asli')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });
    }

    private function seedData(): void
    {
        $this->leadsId = 1; // service tidak query sl_leads

        $this->quotationId = DB::table('sl_quotation')->insertGetId([
            'leads_id' => $this->leadsId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Kaporlap: 2 item (qty 10 & 4)
        DB::table('sl_quotation_kaporlap')->insert([
            ['id' => 1, 'quotation_id' => $this->quotationId, 'quotation_detail_id' => 1, 'barang_id' => 1, 'jumlah' => 10, 'nama' => 'Seragam', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'quotation_id' => $this->quotationId, 'quotation_detail_id' => 1, 'barang_id' => 2, 'jumlah' => 4, 'nama' => 'Sepatu', 'created_at' => now(), 'updated_at' => now()],
        ]);
        // Device: 1 item (qty 6)
        DB::table('sl_quotation_devices')->insert([
            ['id' => 1, 'quotation_id' => $this->quotationId, 'quotation_site_id' => null, 'barang_id' => 3, 'jumlah' => 6, 'nama' => 'HT', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->pksId = DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId, 'quotation_id' => $this->quotationId, 'nomor' => 'PKS/SUM/1',
            'status_pks_id' => 7, 'kontrak_awal' => '2026-01-01', 'kontrak_akhir' => '2027-12-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->siteId = DB::table('sl_site')->insertGetId([
            'pks_id' => $this->pksId, 'quotation_id' => $this->quotationId, 'leads_id' => $this->leadsId,
            'nama_site' => 'Site 1', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Fulfillment: kaporlap#1 penuh (10/10), kaporlap#2 sebagian (1/4). device belum ada row.
        DB::table('sl_pks_item_fulfillment')->insert([
            ['pks_id' => $this->pksId, 'site_id' => $this->siteId, 'item_type' => 'kaporlap', 'item_id' => 1, 'qty_diminta' => 10, 'qty_terpenuhi' => 10, 'status' => 'fully_fulfilled', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => $this->pksId, 'site_id' => $this->siteId, 'item_type' => 'kaporlap', 'item_id' => 2, 'qty_diminta' => 4, 'qty_terpenuhi' => 1, 'status' => 'partially_fulfilled', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Visit target: crm 4 (terpakai 1), operasional 4 (terpakai 0)
        DB::table('sl_pks_visit_target')->insert([
            ['pks_id' => $this->pksId, 'role' => 'crm', 'target_total' => 4, 'target_terpakai' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => $this->pksId, 'role' => 'operasional', 'target_total' => 4, 'target_terpakai' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Schedules: 1 done, 1 missed, 1 upcoming (masa depan)
        DB::table('sl_pks_visit_schedule')->insert([
            ['pks_id' => $this->pksId, 'site_id' => $this->siteId, 'role' => 'crm', 'tgl_jadwal' => '2026-02-01', 'status' => 'done', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => $this->pksId, 'site_id' => $this->siteId, 'role' => 'crm', 'tgl_jadwal' => '2026-03-01', 'status' => 'missed', 'created_at' => now(), 'updated_at' => now()],
            ['pks_id' => $this->pksId, 'site_id' => $this->siteId, 'role' => 'operasional', 'tgl_jadwal' => now()->addMonth()->toDateString(), 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** @test */
    public function test_summary_aggregates_item_and_visit(): void
    {
        $pks = Pks::findOrFail($this->pksId);
        $summary = $this->service->build($pks);

        // struktur utama
        $this->assertSame($this->pksId, $summary['pks']['id']);
        $this->assertNull($summary['training']);
        // hc terisi (HRIS kosong di test → overall 0/persen 100), bukan null lagi.
        $this->assertIsArray($summary['hc']);
        $this->assertSame(0, $summary['hc']['total_vacancy']);
        $this->assertSame(100.0, $summary['hc']['persen']);

        // item overall: 3 item (2 kaporlap + 1 device); device belum ada fulfillment → not_yet
        $item = $summary['item']['overall'];
        $this->assertSame(3, $item['total_item']);
        $this->assertSame(1, $item['fully_fulfilled']);
        $this->assertSame(1, $item['partially_fulfilled']);
        $this->assertSame(1, $item['not_yet_fulfilled']);
        // qty diminta = 10 + 4 + 6 = 20; terpenuhi = 10 + 1 + 0 = 11
        $this->assertSame(20, $item['total_qty_diminta']);
        $this->assertSame(11, $item['total_qty_terpenuhi']);
        $this->assertSame(55.0, $item['persen']); // 11/20 = 55%

        // per_site ada 1 site
        $this->assertCount(1, $summary['item']['per_site']);
        $this->assertSame($this->siteId, $summary['item']['per_site'][0]['site_id']);

        // visit per_role
        $this->assertCount(2, $summary['visit']['per_role']);

        // schedule counts
        $counts = $summary['visit']['schedule_counts'];
        $this->assertSame(1, $counts['done']);
        $this->assertSame(1, $counts['missed']);
        $this->assertSame(1, $counts['scheduled']);
        $this->assertSame(0, $counts['rescheduled']);

        // upcoming = jadwal operasional masa depan
        $this->assertNotNull($summary['visit']['upcoming']);
        $this->assertSame('operasional', $summary['visit']['upcoming']['role']);
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
