<?php

namespace Tests\Unit\Services\Pks\Fulfillment;

use App\Models\Pks;
use App\Models\PksItemFulfillment;
use App\Models\PksFulfillmentLog;
use App\Models\User;
use App\Services\Pks\Fulfillment\ItemFulfillmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for ItemFulfillmentService.
 *
 * @group pks-fulfillment
 */
class ItemFulfillmentServiceTest extends TestCase
{
    private ItemFulfillmentService $service;
    private User $user;
    private int $pksId;
    private int $siteId;
    private int $leadsId;
    private int $quotationId;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = $this->tempDbPath = storage_path('framework/testing-' . Str::random(8) . '.sqlite');
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

        // Insert user
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

        $this->user = User::query()->findOrFail(1);
        $this->service = new ItemFulfillmentService();

        $this->seedBaseData();
    }

    private function seedBaseData(): void
    {
        // sl_leads
        $this->leadsId = DB::table('sl_leads')->insertGetId([
            'nomor' => 'LEAD-UNIT-001',
            'nama_perusahaan' => 'PT Unit Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation
        $this->quotationId = DB::table('sl_quotation')->insertGetId([
            'leads_id' => $this->leadsId,
            'nomor' => 'Q-UNIT-001',
            'is_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation_kaporlap
        DB::table('sl_quotation_kaporlap')->insert([
            [
                'quotation_id' => $this->quotationId,
                'quotation_detail_id' => 1,
                'barang_id' => 1,
                'jumlah' => 10,
                'nama' => 'Seragam Security',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // sl_quotation_devices
        DB::table('sl_quotation_devices')->insert([
            [
                'quotation_id' => $this->quotationId,
                'quotation_detail_id' => 1,
                'barang_id' => 2,
                'jumlah' => 5,
                'nama' => 'HT Radio',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // sl_pks
        $this->pksId = DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'nomor' => 'PKS/UNIT/001',
            'status_pks_id' => 7,
            'layanan_id' => 1,
            'is_aktif' => 1,
            'tipe_pks' => 'baru',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_site
        $this->siteId = DB::table('sl_site')->insertGetId([
            'pks_id' => $this->pksId,
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'kota_id' => 1,
            'kota' => 'Jakarta',
            'nama_site' => 'Site Unit Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_pks_fulfillment_log',
            'sl_pks_item_fulfillment',
            'sl_quotation_devices',
            'sl_quotation_kaporlap',
            'sl_quotation_chemical',
            'sl_quotation_detail',
            'sl_quotation',
            'sl_site',
            'sl_pks',
            'sl_leads',
            'm_kebutuhan',
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

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->boolean('is_aktif')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('jumlah_hc')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_kaporlap', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->unsignedInteger('barang_id')->nullable();
            $table->unsignedInteger('jumlah')->nullable();
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_devices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->unsignedInteger('barang_id')->nullable();
            $table->unsignedInteger('jumlah')->nullable();
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_chemical', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->unsignedInteger('barang_id')->nullable();
            $table->unsignedInteger('jumlah')->nullable();
            $table->string('nama')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_induk_id')->nullable();
            $table->string('tipe_pks')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('kontrak_awal')->nullable();
            $table->date('kontrak_akhir')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->boolean('is_aktif')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('pks_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->string('kota')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

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
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('jenis', 32);
            $table->unsignedBigInteger('reference_id');
            $table->string('aksi', 32);
            $table->text('catatan')->nullable();
            $table->json('meta')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    // ─── TEST 1: createFulfillment atomic ────────────────────────────
    /** @test */
    public function test_create_fulfillment_is_atomic_guarded_against_over_fulfill(): void
    {
        // First fulfillment: qty = 8 (remaining = 10 - 8 = 2)
        $data1 = [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 8,
            'catatan' => 'Fulfillment pertama 8 unit',
        ];
        $this->service->createFulfillment($data1, $this->user);

        // Second fulfillment attempt: qty = 3 (exceeds remaining 2)
        $data2 = [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 3,
            'catatan' => 'Fulfillment kedua 3 unit - harus gagal',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Qty melebihi remaining atau race condition.');

        $this->service->createFulfillment($data2, $this->user);
    }

    // ─── TEST 2: createFulfillment menghasilkan log ──────────────────
    /** @test */
    public function test_create_fulfillment_produces_log_entry(): void
    {
        $data = [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 5,
            'catatan' => 'Fulfillment test untuk log',
        ];

        $this->service->createFulfillment($data, $this->user);

        $log = PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)
            ->where('aksi', 'create')->first();
        $this->assertNotNull($log);
        $this->assertSame(5, $log->meta['qty_sesi_ini']);
        $this->assertSame(10, $log->meta['remaining_sebelum']);
        $this->assertSame(5, $log->meta['remaining_sesudah']);

        $this->assertEquals(1, PksFulfillmentLog::count());
    }

    // ─── TEST 3: editFulfillment menghasilkan log audit ──────────────
    /** @test */
    public function test_edit_fulfillment_produces_audit_log(): void
    {
        // Create first
        $data = [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 5,
            'catatan' => 'Fulfillment awal',
        ];
        $fulfillment = $this->service->createFulfillment($data, $this->user);

        // Then edit
        $this->service->editFulfillment($fulfillment, 8, 'Edit menjadi 8 unit', $this->user);

        $editLog = PksFulfillmentLog::forRef(PksFulfillmentLog::JENIS_ITEM, $fulfillment->id)
            ->where('aksi', 'edit')->first();
        $this->assertNotNull($editLog);
        $this->assertSame(3, $editLog->meta['qty_sesi_ini']); // delta: 8 - 5

        $logCount = PksFulfillmentLog::forRef(PksFulfillmentLog::JENIS_ITEM, $fulfillment->id)->count();
        $this->assertEquals(2, $logCount, 'Should have create + edit log entries');
    }

    // ─── TEST 4: editFulfillment validasi qty ────────────────────────
    /** @test */
    public function test_edit_fulfillment_rejects_invalid_qty(): void
    {
        $data = [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 5,
            'catatan' => 'Fulfillment awal',
        ];
        $fulfillment = $this->service->createFulfillment($data, $this->user);

        // Try to set qty > qty_diminta
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Qty tidak valid.');

        $this->service->editFulfillment($fulfillment, 15, 'Melebihi qty_diminta', $this->user);
    }

    /** @test */
    public function test_edit_fulfillment_rejects_negative_qty(): void
    {
        $data = [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 5,
            'catatan' => 'Fulfillment awal',
        ];
        $fulfillment = $this->service->createFulfillment($data, $this->user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Qty tidak valid.');

        $this->service->editFulfillment($fulfillment, -1, 'Qty negatif tidak valid', $this->user);
    }

    // ─── TEST 5: getRequestedItems exclude OHC ───────────────────────
    /** @test */
    public function test_get_requested_items_excludes_ohc(): void
    {
        $pks = Pks::find($this->pksId);

        $items = $this->service->getRequestedItems($pks, $this->siteId);

        // Only kaporlap and device (no chemical or OHC)
        $types = array_column($items, 'item_type');
        $this->assertContains('kaporlap', $types);
        $this->assertContains('device', $types);
        $this->assertNotContains('ohc', $types, 'OHC items should be excluded');
    }

    // ─── TEST 6: getRequestedItems status fully_fulfilled ────────────
    /** @test */
    public function test_get_requested_items_shows_fully_fulfilled_status(): void
    {
        $pks = Pks::find($this->pksId);

        // First get items (not yet fulfilled)
        $items = $this->service->getRequestedItems($pks, $this->siteId);
        $kaporlapItem = collect($items)->firstWhere('item_type', 'kaporlap');
        $this->assertEquals('not_yet_fulfilled', $kaporlapItem['status']);

        // Fulfill completely
        $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 10,
            'catatan' => 'Fulfillment penuh untuk testing',
        ], $this->user);

        // Check status now
        $items = $this->service->getRequestedItems($pks, $this->siteId);
        $kaporlapItem = collect($items)->firstWhere('item_type', 'kaporlap');
        $this->assertEquals('fully_fulfilled', $kaporlapItem['status']);
        $this->assertEquals(10, $kaporlapItem['qty_terpenuhi']);
        $this->assertEquals(0, $kaporlapItem['remaining']);
    }

    // ─── TEST 6b: qty kaporlap dikali jumlah_hc detail ───────────────
    /** @test */
    public function test_get_requested_items_multiplies_kaporlap_by_jumlah_hc(): void
    {
        // Detail dengan 3 HC → kaporlap 2 per personil = 6
        DB::table('sl_quotation_detail')->insert([
            'id' => 5,
            'quotation_id' => $this->quotationId,
            'jumlah_hc' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sl_quotation_kaporlap')->insert([
            'id' => 50,
            'quotation_id' => $this->quotationId,
            'quotation_detail_id' => 5,
            'barang_id' => 3,
            'jumlah' => 2,
            'nama' => 'Sepatu PDL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $items = collect($this->service->getRequestedItems(Pks::find($this->pksId), $this->siteId));

        $this->assertSame(6, $items->firstWhere('item_id', 50)['qty_diminta']);

        // Detail tidak ditemukan (data legacy) → pengali 1, bukan 0
        $this->assertSame(10, $items->firstWhere('item_id', 1)['qty_diminta']);

        // Device tidak ikut dikali
        $this->assertSame(5, $items->firstWhere('item_type', 'device')['qty_diminta']);
    }

    // ─── TEST 6c: catatan tersimpan per sesi di list log ─────────────
    /** @test */
    public function test_catatan_is_stored_per_session_in_log(): void
    {
        // getRequestedItems tidak lagi membawa catatan.
        $items = collect($this->service->getRequestedItems(Pks::find($this->pksId), $this->siteId));
        $this->assertArrayNotHasKey('catatan', $items->firstWhere('item_type', 'kaporlap'));

        $fulfillment = $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 4,
            'catatan' => 'Pengiriman tahap pertama 4 unit',
        ], $this->user);

        // Sesi berikutnya menambah baris log baru (tidak menimpa).
        $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 6,
            'catatan' => 'Pelunasan sisa 6 unit',
        ], $this->user);

        // Kedua catatan tampil di list log (terbaru dulu).
        $log = $this->service->getFulfillmentLog($fulfillment->id);
        $this->assertSame(2, $log->count());
        $this->assertSame('Pelunasan sisa 6 unit', $log->first()['catatan']);
        $this->assertSame('Pengiriman tahap pertama 4 unit', $log->last()['catatan']);
    }

    // ─── TEST 6d: editFulfillment menulis catatan koreksi ke log ─────
    /** @test */
    public function test_edit_fulfillment_writes_catatan_to_log(): void
    {
        $fulfillment = $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 5,
            'catatan' => 'Catatan awal pengiriman',
        ], $this->user);

        $this->service->editFulfillment($fulfillment, 8, 'Koreksi jumlah jadi 8 unit', $this->user);

        // Catatan edit jadi baris log terbaru.
        $log = $this->service->getFulfillmentLog($fulfillment->id);
        $this->assertSame('edit', $log->first()['aksi']);
        $this->assertSame('Koreksi jumlah jadi 8 unit', $log->first()['catatan']);
    }

    // ─── TEST 6e: getPksLog gabung item + visit, filter jenis ────────
    /** @test */
    public function test_get_pks_log_combines_item_and_visit_with_filter(): void
    {
        // 1 log item lewat createFulfillment
        $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 4,
            'catatan' => 'Kirim item tahap 1',
        ], $this->user);

        // 1 log visit disisipkan langsung (tanpa mesin visit)
        PksFulfillmentLog::create([
            'pks_id' => $this->pksId,
            'jenis' => PksFulfillmentLog::JENIS_VISIT,
            'reference_id' => 99,
            'aksi' => 'create',
            'catatan' => 'Visit operasional',
            'meta' => ['role' => 'operasional'],
            'created_by' => $this->user->full_name,
            'created_by_user_id' => $this->user->id,
        ]);

        // Gabungan: 2 baris, terbaru dulu (visit terakhir dibuat).
        $all = $this->service->getPksLog($this->pksId);
        $this->assertSame(2, $all->count());
        $this->assertSame('visit', $all->first()['jenis']);

        // Filter per jenis
        $this->assertSame(1, $this->service->getPksLog($this->pksId, PksFulfillmentLog::JENIS_ITEM)->count());
        $visitOnly = $this->service->getPksLog($this->pksId, PksFulfillmentLog::JENIS_VISIT);
        $this->assertSame(1, $visitOnly->count());
        $this->assertSame('operasional', $visitOnly->first()['meta']['role']);
    }

    // ─── TEST 7: createFulfillment restore soft-deleted ──────────────
    /** @test */
    public function test_create_fulfillment_restores_soft_deleted_record(): void
    {
        $data = [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 5,
            'catatan' => 'Fulfillment original',
        ];

        $fulfillment = $this->service->createFulfillment($data, $this->user);
        $fulfillment->delete();
        $this->assertNotNull($fulfillment->deleted_at, 'Should be soft-deleted');

        // Re-create should restore
        $data2 = [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 3,
            'catatan' => 'Fulfillment setelah restore',
        ];

        $restored = $this->service->createFulfillment($data2, $this->user);
        $this->assertNull($restored->deleted_at, 'Record should be restored');
        $this->assertEquals($fulfillment->id, $restored->id, 'Should be the same record');
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
