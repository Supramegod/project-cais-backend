<?php

namespace Tests\Unit\Services\Pks\Fulfillment;

use App\Models\Pks;
use App\Models\PksFulfillmentLog;
use App\Models\PksItemFulfillment;
use App\Models\PksItemRequest;
use App\Models\User;
use App\Services\Pks\Fulfillment\FulfillmentLogService;
use App\Services\Pks\Fulfillment\ItemFulfillmentService;
use App\Services\Pks\Fulfillment\ItemReceivingService;
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

        $databasePath = $this->tempDbPath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
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
        $this->service = new ItemFulfillmentService;

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
            'sl_pks_item_request',
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
            $table->unsignedInteger('qty_request')->default(0);
            $table->unsignedInteger('qty_terpenuhi')->default(0);
            $table->string('status')->default('not_yet_fulfilled');
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks_item_request', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pks_id');
            $table->unsignedInteger('site_id');
            $table->unsignedInteger('fulfillment_id');
            $table->uuid('batch_id');
            $table->unsignedInteger('batch_ke')->nullable();
            $table->uuid('received_batch_id')->nullable();
            $table->unsignedInteger('received_batch_ke')->nullable();
            $table->string('item_type', 32);
            $table->unsignedInteger('item_id');
            $table->unsignedInteger('qty_request');
            $table->unsignedInteger('qty_diterima')->default(0);
            $table->string('status', 32)->default('open');
            $table->timestamp('received_at')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sl_pks_fulfillment_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('pks_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('jenis', 32);
            $table->unsignedBigInteger('reference_id');
            $table->uuid('batch_id')->nullable();
            $table->unsignedInteger('batch_ke')->nullable();
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
        $this->expectExceptionMessage('Qty melebihi sisa yang boleh di-request atau race condition.');

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

        $fulfillment = $this->service->createFulfillment($data, $this->user);

        $log = PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)
            ->where('aksi', PksFulfillmentLog::AKSI_REQUEST)->first();
        $this->assertNotNull($log);
        $this->assertSame(5, $log->meta['qty_sesi_ini']);
        $this->assertSame(10, $log->meta['boleh_direquest_sebelum']);
        $this->assertSame(5, $log->meta['boleh_direquest_sesudah']);

        $this->assertEquals(1, PksFulfillmentLog::count());

        // Request menaikkan qty_request, bukan qty_terpenuhi — barang belum
        // tentu sampai di site.
        $this->assertSame(5, (int) $fulfillment->qty_request);
        $this->assertSame(0, (int) $fulfillment->qty_terpenuhi);
        $this->assertSame(PksItemFulfillment::STATUS_REQUESTED, $fulfillment->status);

        // Satu baris permintaan yang menunggu penerimaan.
        $request = PksItemRequest::where('fulfillment_id', $fulfillment->id)->first();
        $this->assertNotNull($request);
        $this->assertSame(5, (int) $request->qty_request);
        $this->assertSame(PksItemRequest::STATUS_OPEN, $request->status);
        $this->assertSame($log->batch_id, $request->batch_id);
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
            ->where('aksi', PksFulfillmentLog::AKSI_EDIT)->first();
        $this->assertNotNull($editLog);
        // Edit menyetel jumlah DITERIMA secara absolut; qty_terpenuhi masih 0
        // karena barang belum di-receive, jadi deltanya 8.
        $this->assertSame(8, $editLog->meta['qty_sesi_ini']);

        $logCount = PksFulfillmentLog::forRef(PksFulfillmentLog::JENIS_ITEM, $fulfillment->id)->count();
        $this->assertEquals(2, $logCount, 'Should have request + edit log entries');
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

    // ─── TEST 6: getRequestedItems status per tahap ──────────────────
    /** @test */
    public function test_get_requested_items_shows_request_then_received_status(): void
    {
        $pks = Pks::find($this->pksId);

        // First get items (not yet fulfilled)
        $items = $this->service->getRequestedItems($pks, $this->siteId);
        $kaporlapItem = collect($items)->firstWhere('item_type', 'kaporlap');
        $this->assertEquals('not_yet_fulfilled', $kaporlapItem['status']);
        $this->assertEquals(10, $kaporlapItem['boleh_direquest']);

        // Tahap 1: seluruh kebutuhan dikirim.
        $fulfillment = $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 10,
            'catatan' => 'Fulfillment penuh untuk testing',
        ], $this->user);

        // Sudah dikirim, belum diterima: tidak boleh dikirim ulang.
        $items = $this->service->getRequestedItems($pks, $this->siteId);
        $kaporlapItem = collect($items)->firstWhere('item_type', 'kaporlap');
        $this->assertEquals('requested', $kaporlapItem['status']);
        $this->assertEquals(10, $kaporlapItem['qty_request']);
        $this->assertEquals(0, $kaporlapItem['qty_terpenuhi']);
        $this->assertEquals(0, $kaporlapItem['boleh_direquest']);

        // Tahap 2: site mengonfirmasi seluruhnya diterima.
        (new ItemReceivingService)->receive(
            [['fulfillment_id' => $fulfillment->id, 'qty' => 10]],
            $this->user
        );

        $items = $this->service->getRequestedItems($pks, $this->siteId);
        $kaporlapItem = collect($items)->firstWhere('item_type', 'kaporlap');
        $this->assertEquals('fully_fulfilled', $kaporlapItem['status']);
        $this->assertEquals(10, $kaporlapItem['qty_terpenuhi']);
        $this->assertEquals(0, $kaporlapItem['qty_request']);
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

    // --- TEST 6e: getPksLog dikelompokkan per batch, filter jenis ---
    /** @test */
    public function test_get_pks_log_groups_per_batch_with_filter(): void
    {
        $logService = new FulfillmentLogService;

        // 1 log item lewat createFulfillment - dapat batch sendiri
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
            'batch_id' => (string) Str::uuid(),
            'batch_ke' => 1,
            'aksi' => 'create',
            'catatan' => 'Visit operasional',
            'meta' => ['role' => 'operasional'],
            'created_by' => $this->user->full_name,
            'created_by_user_id' => $this->user->id,
        ]);

        // Dua batch berbeda, terbaru dulu (visit terakhir dibuat).
        $all = $logService->getPksLog($this->pksId);
        $this->assertSame(2, $all->count());
        $this->assertSame('visit', $all->first()['jenis']);
        $this->assertSame(1, $all->first()['jumlah_log']);
        $this->assertNotNull($all->first()['batch_id']);

        $itemBatch = $all->last();
        $this->assertSame('item', $itemBatch['jenis']);
        $this->assertSame(PksFulfillmentLog::AKSI_REQUEST, $itemBatch['aksi']);
        $this->assertSame(1, $itemBatch['batch_ke']);
        $this->assertSame('Kirim item tahap 1', $itemBatch['logs'][0]['catatan']);
        $this->assertSame(4, $itemBatch['logs'][0]['meta']['qty_sesi_ini']);

        // Filter per jenis
        $this->assertSame(1, $logService->getPksLog($this->pksId, PksFulfillmentLog::JENIS_ITEM)->count());
        $visitOnly = $logService->getPksLog($this->pksId, PksFulfillmentLog::JENIS_VISIT);
        $this->assertSame(1, $visitOnly->count());
        $this->assertSame('operasional', $visitOnly->first()['logs'][0]['meta']['role']);
    }

    // --- TEST 6f: log lama tanpa batch tetap tampil ---
    /** @test */
    public function test_get_pks_log_keeps_legacy_logs_without_batch(): void
    {
        foreach ([1, 2] as $ref) {
            PksFulfillmentLog::create([
                'pks_id' => $this->pksId,
                'jenis' => PksFulfillmentLog::JENIS_ITEM,
                'reference_id' => $ref,
                'aksi' => 'create',
                'catatan' => 'Log lama tanpa batch '.$ref,
                'meta' => ['qty_sesi_ini' => 1],
                'created_by' => $this->user->full_name,
                'created_by_user_id' => $this->user->id,
            ]);
        }

        // Tanpa batch_id, tiap log berdiri sendiri - tidak boleh menyatu.
        $groups = (new FulfillmentLogService)->getPksLog($this->pksId);
        $this->assertSame(2, $groups->count());
        $this->assertNull($groups->first()['batch_id']);
        $this->assertSame(1, $groups->first()['jumlah_log']);
    }

    // --- TEST 6g: nomor batch berjalan terpisah per jenis dan per aksi ---
    /** @test */
    public function test_batch_ke_runs_separately_per_jenis(): void
    {
        $kirim = fn (int $itemId, int $qty) => $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $itemId,
            'qty_diminta' => 10,
            'qty' => $qty,
            'catatan' => 'Kirim item',
        ], $this->user);

        $kirim(1, 2);
        $kirim(2, 3);

        $this->assertSame(
            [1, 2],
            PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)
                ->orderBy('id')->pluck('batch_ke')->all()
        );

        // Visit mulai dari 1 lagi, bukan melanjutkan nomor item.
        $this->assertSame(1, PksFulfillmentLog::nextBatchKe($this->pksId, PksFulfillmentLog::JENIS_VISIT));

        // Penerimaan juga punya deret sendiri: "kirim batch ke-3" dan "terima
        // batch ke-3" tidak boleh saling makan nomor.
        $this->assertSame(1, PksFulfillmentLog::nextBatchKe(
            $this->pksId,
            PksFulfillmentLog::JENIS_ITEM,
            PksFulfillmentLog::AKSI_RECEIVE
        ));
        $this->assertSame(3, PksFulfillmentLog::nextBatchKe(
            $this->pksId,
            PksFulfillmentLog::JENIS_ITEM,
            PksFulfillmentLog::AKSI_REQUEST
        ));
    }

    // --- TEST 6h: edit menghasilkan batch sendiri ---
    /** @test */
    public function test_edit_fulfillment_gets_its_own_batch(): void
    {
        $fulfillment = $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => 1,
            'qty_diminta' => 10,
            'qty' => 4,
            'catatan' => 'Kirim item tahap 1',
        ], $this->user);

        $this->service->editFulfillment($fulfillment, 6, 'Revisi qty jadi enam', $this->user);

        $batches = PksFulfillmentLog::orderBy('id')->get();
        // Deret nomor dipisah per aksi, jadi batch edit pertama juga bernomor 1.
        $this->assertSame([1, 1], $batches->pluck('batch_ke')->all());
        $this->assertSame(
            [PksFulfillmentLog::AKSI_REQUEST, PksFulfillmentLog::AKSI_EDIT],
            $batches->pluck('aksi')->all()
        );
        $this->assertSame(2, $batches->pluck('batch_id')->unique()->count());
    }

    // --- TEST 6i: detail batch berisi nama barang + qty batch itu ---
    /** @test */
    public function test_get_batch_detail_returns_items_with_names(): void
    {
        $result = $this->service->createBulkFulfillment([
            [
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'item_type' => 'kaporlap',
                'item_id' => 1,
                'qty_diminta' => 10,
                'qty' => 4,
                'catatan' => 'Kirim kaporlap',
            ],
            [
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'leads_id' => $this->leadsId,
                'item_type' => 'device',
                'item_id' => 1,
                'qty_diminta' => 5,
                'qty' => 5,
                'catatan' => 'Kirim device',
            ],
        ], $this->user);

        $detail = (new FulfillmentLogService)->getBatchDetail($result['batch_id']);

        $this->assertSame($result['batch_id'], $detail['batch_id']);
        $this->assertSame('item', $detail['jenis']);
        $this->assertSame(2, $detail['jumlah_item']);

        $kaporlap = $detail['items'][0];
        $this->assertSame('kaporlap', $kaporlap['item_type']);
        $this->assertSame(1, $kaporlap['item_type_id']);
        // Qty yang ditampilkan adalah isi batch ini, bukan akumulasi.
        $this->assertSame(4, $kaporlap['qty_dikirim']);
        $this->assertNotNull($kaporlap['nama']);
        $this->assertSame('Kirim kaporlap', $kaporlap['catatan']);

        $device = $detail['items'][1];
        $this->assertSame('device', $device['item_type']);
        $this->assertSame(5, $device['qty_dikirim']);

        // Record fulfillment yang sudah dihapus tidak boleh menghilangkan log.
        PksItemFulfillment::query()->delete();
        $afterDelete = (new FulfillmentLogService)->getBatchDetail($result['batch_id']);
        $this->assertSame(2, $afterDelete['jumlah_item']);
        $this->assertSame(4, $afterDelete['items'][0]['qty_dikirim']);

        // Batch yang tidak ada -> null
        $this->assertNull((new FulfillmentLogService)->getBatchDetail((string) Str::uuid()));
    }

    // ══════ Tahap 2 — penerimaan barang (ItemReceivingService) ══════
    //
    // Ditaruh satu file dengan tahap request karena keduanya berbagi skema dan
    // data awal yang sama; memisah file berarti menyalin ~300 baris setup.

    /**
     * Kirim barang dan kembalikan baris fulfillment-nya.
     */
    private function kirim(int $qty, string $itemType = 'kaporlap', int $itemId = 1, int $qtyDiminta = 10): PksItemFulfillment
    {
        return $this->service->createFulfillment([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'qty_diminta' => $qtyDiminta,
            'qty' => $qty,
            'catatan' => 'Kirim barang untuk pengujian',
        ], $this->user);
    }

    // --- TEST 9a: diterima penuh menutup baris permintaan ---
    /** @test */
    public function test_receive_full_closes_request_and_raises_qty_terpenuhi(): void
    {
        $fulfillment = $this->kirim(5);

        $hasil = (new ItemReceivingService)->receive(
            [['fulfillment_id' => $fulfillment->id, 'qty' => 5]],
            $this->user,
            null,
            'Barang diterima lengkap'
        );

        $fulfillment->refresh();
        $this->assertSame(5, (int) $fulfillment->qty_terpenuhi);
        $this->assertSame(0, (int) $fulfillment->qty_request);
        $this->assertSame(PksItemFulfillment::STATUS_PARTIAL, $fulfillment->status);

        $request = PksItemRequest::where('fulfillment_id', $fulfillment->id)->first();
        $this->assertSame(PksItemRequest::STATUS_RECEIVED, $request->status);
        $this->assertSame(5, (int) $request->qty_diterima);
        $this->assertSame(0, $request->kurang);

        // Batch penerimaan punya deret nomor sendiri, mulai dari 1.
        $this->assertSame(1, $hasil['batch_ke']);
        $log = PksFulfillmentLog::where('aksi', PksFulfillmentLog::AKSI_RECEIVE)->first();
        $this->assertSame($hasil['batch_id'], $log->batch_id);
        $this->assertSame(5, $log->meta['qty_diterima']);
        $this->assertSame(0, $log->meta['kurang']);
        $this->assertSame('Barang diterima lengkap', $log->catatan);
    }

    // --- TEST 9b: diterima kurang, sisanya boleh dikirim lagi ---
    /** @test */
    public function test_receive_short_returns_the_gap_to_requestable(): void
    {
        $fulfillment = $this->kirim(5);

        (new ItemReceivingService)->receive(
            [['fulfillment_id' => $fulfillment->id, 'qty' => 4]],
            $this->user,
            null,
            'Satu unit rusak saat diterima'
        );

        $fulfillment->refresh();
        $this->assertSame(4, (int) $fulfillment->qty_terpenuhi);
        // Baris ditutup seluruhnya, jadi tidak ada lagi yang "di jalan".
        $this->assertSame(0, (int) $fulfillment->qty_request);
        // 10 - 4 diterima - 0 di jalan: yang 1 unit kurang boleh dikirim ulang.
        $this->assertSame(6, $fulfillment->boleh_direquest);

        $request = PksItemRequest::where('fulfillment_id', $fulfillment->id)->first();
        $this->assertSame(PksItemRequest::STATUS_SHORT, $request->status);
        $this->assertSame(1, $request->kurang);

        // Pengiriman ulang atas kekurangan itu diterima.
        $this->kirim(6);
        $fulfillment->refresh();
        $this->assertSame(6, (int) $fulfillment->qty_request);
    }

    // --- TEST 9c: tidak boleh menerima lebih dari yang dikirim ---
    /** @test */
    public function test_receive_rejects_qty_above_open_request(): void
    {
        $fulfillment = $this->kirim(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('melebihi yang dikirim dan belum diterima');

        (new ItemReceivingService)->receive(
            [['fulfillment_id' => $fulfillment->id, 'qty' => 4]],
            $this->user
        );
    }

    // --- TEST 9d: tanpa batch_id, pengiriman paling lama ditutup dulu ---
    /** @test */
    public function test_receive_without_batch_id_closes_oldest_first(): void
    {
        $fulfillment = $this->kirim(3);
        $this->kirim(4);

        (new ItemReceivingService)->receive(
            [['fulfillment_id' => $fulfillment->id, 'qty' => 5]],
            $this->user
        );

        $requests = PksItemRequest::where('fulfillment_id', $fulfillment->id)->orderBy('id')->get();
        $this->assertSame(PksItemRequest::STATUS_RECEIVED, $requests[0]->status);
        $this->assertSame(3, (int) $requests[0]->qty_diterima);
        // Batch kedua tersentuh sebagian, jadi ikut ditutup sebagai kurang.
        $this->assertSame(PksItemRequest::STATUS_SHORT, $requests[1]->status);
        $this->assertSame(2, (int) $requests[1]->qty_diterima);

        $fulfillment->refresh();
        $this->assertSame(5, (int) $fulfillment->qty_terpenuhi);
        $this->assertSame(0, (int) $fulfillment->qty_request);
    }

    // --- TEST 9e: batch_id membatasi penutupan ke satu pengiriman ---
    /** @test */
    public function test_receive_with_batch_id_only_touches_that_batch(): void
    {
        $fulfillment = $this->kirim(3);
        $this->kirim(4);

        $requests = PksItemRequest::where('fulfillment_id', $fulfillment->id)->orderBy('id')->get();
        $batchKedua = $requests[1]->batch_id;

        (new ItemReceivingService)->receive(
            [['fulfillment_id' => $fulfillment->id, 'qty' => 4]],
            $this->user,
            $batchKedua
        );

        $requests = PksItemRequest::where('fulfillment_id', $fulfillment->id)->orderBy('id')->get();
        $this->assertSame(PksItemRequest::STATUS_OPEN, $requests[0]->status);
        $this->assertSame(PksItemRequest::STATUS_RECEIVED, $requests[1]->status);

        // Batch pertama masih menunggu, jadi 3 unit tetap terhitung di jalan.
        $fulfillment->refresh();
        $this->assertSame(4, (int) $fulfillment->qty_terpenuhi);
        $this->assertSame(3, (int) $fulfillment->qty_request);
    }

    // --- TEST 9f: detail batch penerimaan menjawab "diterima berapa" ---
    /** @test */
    public function test_batch_detail_of_receive_shows_received_numbers(): void
    {
        $fulfillment = $this->kirim(5);

        $hasil = (new ItemReceivingService)->receive(
            [['fulfillment_id' => $fulfillment->id, 'qty' => 4]],
            $this->user
        );

        $detail = (new FulfillmentLogService)->getBatchDetail($hasil['batch_id']);

        $this->assertSame(PksFulfillmentLog::AKSI_RECEIVE, $detail['aksi']);
        $this->assertSame(1, $detail['jumlah_item']);

        $item = $detail['items'][0];
        $this->assertSame(4, $item['qty_diterima']);
        $this->assertSame(5, $item['qty_request_ditutup']);
        $this->assertSame(1, $item['kurang']);
        $this->assertNotNull($item['nama']);
    }

    // --- TEST 9g: satu penerimaan gagal membatalkan seluruh panggilan ---
    /** @test */
    public function test_receive_is_all_or_nothing(): void
    {
        $kaporlap = $this->kirim(5);
        $device = $this->kirim(3, 'device', 1, 5);

        try {
            (new ItemReceivingService)->receive([
                ['fulfillment_id' => $kaporlap->id, 'qty' => 5],
                ['fulfillment_id' => $device->id, 'qty' => 99],
            ], $this->user);
            $this->fail('Penerimaan dengan qty berlebih seharusnya gagal.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Item ke-2', $e->getMessage());
        }

        // Item pertama ikut dibatalkan.
        $kaporlap->refresh();
        $this->assertSame(0, (int) $kaporlap->qty_terpenuhi);
        $this->assertSame(5, (int) $kaporlap->qty_request);
        $this->assertSame(0, PksFulfillmentLog::where('aksi', PksFulfillmentLog::AKSI_RECEIVE)->count());
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
