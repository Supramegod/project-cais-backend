<?php

namespace Tests\Unit\Services\Pks;

use App\Models\Pks;
use App\Services\Pks\HcFulfillmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit test HcFulfillmentService — rekap pemenuhan HC (HRIS) per PKS.
 *
 * @group pks-fulfillment
 */
class HcFulfillmentServiceTest extends TestCase
{
    private HcFulfillmentService $service;
    private int $pksId;
    private int $siteId;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = $this->tempDbPath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
        touch($databasePath);
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        // mysql (CRM) & mysqlhris (HRIS) diarahkan ke sqlite yang sama.
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        Config::set('database.connections.mysqlhris', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));

        foreach (['sqlite', 'mysql', 'mysqlhris'] as $c) {
            DB::purge($c);
            DB::reconnect($c);
        }

        $this->buildSchema();
        $this->seedData();

        $this->service = app(HcFulfillmentService::class);
    }

    private function buildSchema(): void
    {
        foreach (['m_employee', 't_applicant', 'm_vacancy', 'm_position', 'm_site', 'm_branch', 'sl_site', 'sl_pks'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->date('kontrak_awal')->nullable();
            $table->date('kontrak_akhir')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_branch', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        Schema::create('m_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('site_id')->nullable(); // = sl_site.id (CRM)
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('name')->nullable();
        });

        Schema::create('m_position', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        Schema::create('m_vacancy', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('site_id')->nullable();     // = m_site.id (HRIS)
            $table->unsignedInteger('position_id')->nullable();
            $table->string('title')->nullable();
            $table->unsignedInteger('needs')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('is_active')->default(1);
        });

        Schema::create('t_applicant', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('vacancy_id')->nullable();
            $table->unsignedInteger('employee_id')->nullable();
            $table->unsignedInteger('is_active')->default(1);
        });

        Schema::create('m_employee', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('status_approval')->nullable();
            $table->string('followup_status')->nullable();
            $table->unsignedInteger('is_active')->default(1);
        });
    }

    private function seedData(): void
    {
        $this->pksId = DB::table('sl_pks')->insertGetId([
            'quotation_id' => 1, 'status_pks_id' => 7,
            'kontrak_awal' => '2026-01-01', 'kontrak_akhir' => '2026-12-31',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->siteId = DB::table('sl_site')->insertGetId([
            'pks_id' => $this->pksId, 'nama_site' => 'Site A', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::connection('mysqlhris')->table('m_branch')->insert(['id' => 1, 'name' => 'Cabang A']);
        // HRIS site → tautan ke CRM via site_id = sl_site.id
        DB::connection('mysqlhris')->table('m_site')->insert(['id' => 500, 'site_id' => $this->siteId, 'branch_id' => 1, 'name' => 'Site HRIS A']);
        DB::connection('mysqlhris')->table('m_position')->insert(['id' => 9, 'name' => 'Security']);

        // Vacancy target 5
        DB::connection('mysqlhris')->table('m_vacancy')->insert([
            'id' => 77, 'site_id' => 500, 'position_id' => 9, 'title' => 'Lowongan Security',
            'needs' => 5, 'start_date' => '2026-02-01', 'end_date' => '2026-11-30', 'is_active' => 1,
        ]);

        // Employees: 1 pemanggilan (0+Pemanggilan), 1 pengiriman (1), 2 akumulasi (>=3)
        DB::connection('mysqlhris')->table('m_employee')->insert([
            ['id' => 1, 'status_approval' => 0, 'followup_status' => 'Proses Pemanggilan', 'is_active' => 1],
            ['id' => 2, 'status_approval' => 1, 'followup_status' => 'Pengiriman', 'is_active' => 1],
            ['id' => 3, 'status_approval' => 3, 'followup_status' => 'Diterima', 'is_active' => 1],
            ['id' => 4, 'status_approval' => 4, 'followup_status' => 'Onboard', 'is_active' => 1],
            ['id' => 5, 'status_approval' => 0, 'followup_status' => 'Belum diproses', 'is_active' => 1], // tidak dihitung
        ]);
        DB::connection('mysqlhris')->table('t_applicant')->insert([
            ['id' => 1, 'vacancy_id' => 77, 'employee_id' => 1, 'is_active' => 1],
            ['id' => 2, 'vacancy_id' => 77, 'employee_id' => 2, 'is_active' => 1],
            ['id' => 3, 'vacancy_id' => 77, 'employee_id' => 3, 'is_active' => 1],
            ['id' => 4, 'vacancy_id' => 77, 'employee_id' => 4, 'is_active' => 1],
            ['id' => 5, 'vacancy_id' => 77, 'employee_id' => 5, 'is_active' => 1],
        ]);

        // Vacancy site LAIN (tidak terkait PKS) → tidak boleh muncul
        DB::connection('mysqlhris')->table('m_site')->insert(['id' => 999, 'site_id' => 88888, 'branch_id' => 1, 'name' => 'Site Lain']);
        DB::connection('mysqlhris')->table('m_vacancy')->insert([
            'id' => 88, 'site_id' => 999, 'position_id' => 9, 'title' => 'Lowongan Lain',
            'needs' => 10, 'start_date' => '2026-02-01', 'end_date' => '2026-11-30', 'is_active' => 1,
        ]);
    }

    /** @test */
    public function test_forpks_recaps_hc_per_vacancy(): void
    {
        $pks = Pks::findOrFail($this->pksId);
        $result = $this->service->forPks($pks);

        // hanya 1 vacancy milik site PKS
        $this->assertCount(1, $result['per_vacancy']);
        $v = $result['per_vacancy'][0];
        $this->assertSame(77, $v['vacancy_id']);
        $this->assertSame(5, $v['target_kebutuhan']);
        $this->assertSame(1, $v['jumlah_pemanggilan_only']);
        $this->assertSame(1, $v['jumlah_pengiriman_only']);
        $this->assertSame(2, $v['akumulasi_pengiriman']); // status_approval >= 3
        $this->assertSame(3, $v['sisa_outstanding']);      // 5 - 2

        // overall
        $o = $result['overall'];
        $this->assertSame(1, $o['total_vacancy']);
        $this->assertSame(5, $o['target_kebutuhan']);
        $this->assertSame(2, $o['akumulasi_pengiriman']);
        $this->assertSame(3, $o['sisa_outstanding']);
        $this->assertSame(40.0, $o['persen']); // 2/5
    }

    /** @test */
    public function test_overall_for_pks_ids_batched(): void
    {
        $map = $this->service->overallForPksIds([$this->pksId]);

        $this->assertArrayHasKey($this->pksId, $map);
        $o = $map[$this->pksId];
        $this->assertSame(1, $o['total_vacancy']);
        $this->assertSame(5, $o['target_kebutuhan']);
        $this->assertSame(2, $o['akumulasi_pengiriman']);
        $this->assertSame(3, $o['sisa_outstanding']);
        $this->assertSame(40.0, $o['persen']);
    }

    /** @test */
    public function test_forpks_without_site_returns_empty(): void
    {
        $emptyPks = Pks::create([
            'quotation_id' => 2, 'status_pks_id' => 7,
            'kontrak_awal' => '2026-01-01', 'kontrak_akhir' => '2026-12-31',
        ]);

        $result = $this->service->forPks($emptyPks);

        $this->assertSame([], $result['per_vacancy']);
        $this->assertSame(0, $result['overall']['total_vacancy']);
        $this->assertSame(100.0, $result['overall']['persen']);
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
