<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
use App\Models\PksFulfillmentLog;
use App\Models\PksItemFulfillment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for PKS Item Fulfillment API endpoints.
 *
 * @group pks-fulfillment
 */
class PksFulfillmentItemApiTest extends TestCase
{
    private int $pksId;

    private int $siteId;

    private int $leadsId;

    private int $quotationId;

    private int $kaporlapId;

    private int $deviceId;

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

        // Insert test user
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

        $this->seedBaseData();
    }

    private function seedBaseData(): void
    {
        // m_kebutuhan
        DB::table('m_kebutuhan')->insert([
            ['id' => 1, 'nama' => 'Security', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // m_kategori_sesuai_hc
        DB::table('m_kategori_sesuai_hc')->insert([
            ['id' => 1, 'nama' => 'Silver', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // sl_leads
        $this->leadsId = DB::table('sl_leads')->insertGetId([
            'nomor' => 'LEAD-001',
            'nama_perusahaan' => 'PT Test Fulfillment',
            'kebutuhan_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation
        $this->quotationId = DB::table('sl_quotation')->insertGetId([
            'leads_id' => $this->leadsId,
            'nomor' => 'Q-001',
            'is_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation_kaporlap
        $this->kaporlapId = DB::table('sl_quotation_kaporlap')->insertGetId([
            'quotation_id' => $this->quotationId,
            'quotation_detail_id' => 1,
            'barang_id' => 1,
            'jumlah' => 10,
            'nama' => 'Seragam Security',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation_devices
        $this->deviceId = DB::table('sl_quotation_devices')->insertGetId([
            'quotation_id' => $this->quotationId,
            'quotation_detail_id' => 1,
            'barang_id' => 2,
            'jumlah' => 5,
            'nama' => 'HT Radio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // sl_quotation_chemical
        DB::table('sl_quotation_chemical')->insert([
            [
                'quotation_id' => $this->quotationId,
                'quotation_detail_id' => 1,
                'barang_id' => 3,
                'jumlah' => 20,
                'nama' => 'Disinfectant',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // sl_pks
        $this->pksId = DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'nomor' => 'PKS/TEST/001',
            'status_pks_id' => 7,
            'layanan_id' => 1,
            'kontrak_awal' => '2026-01-01',
            'kontrak_akhir' => '2027-12-31',
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
            'nama_site' => 'Site Test 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function clearFulfillments(): void
    {
        DB::table('sl_pks_fulfillment_log')->delete();
        DB::table('sl_pks_item_fulfillment')->delete();
    }

    // ─── Schema ────────────────────────────────────────────────────────

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_pks_fulfillment_log',
            'sl_pks_item_request',
            'sl_pks_item_fulfillment',
            'sl_quotation_chemical',
            'sl_quotation_devices',
            'sl_quotation_kaporlap',
            'sl_quotation_detail',
            'sl_quotation',
            'sl_site',
            'sl_pks',
            'sl_leads',
            'm_kategori_sesuai_hc',
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

        Schema::create('m_kategori_sesuai_hc', function (Blueprint $table) {
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
            $table->unsignedInteger('branch_id')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kota')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('status_quotation_id')->nullable();
            $table->boolean('is_aktif')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->unsignedInteger('position_id')->nullable();
            $table->string('jabatan_kebutuhan')->nullable();
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
            $table->unsignedInteger('quotation_site_id')->nullable();
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
            $table->unsignedInteger('quotation_site_id')->nullable();
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
            $table->unsignedInteger('branch_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_pks')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->date('kontrak_awal')->nullable();
            $table->date('kontrak_akhir')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->boolean('is_aktif')->nullable();
            $table->string('kategori_sesuai_hc')->nullable();
            $table->unsignedInteger('kategori_sesuai_hc_id')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('spk_id')->nullable();
            $table->unsignedInteger('pks_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->string('kota')->nullable();
            $table->string('penempatan')->nullable();
            $table->boolean('is_visit_anchor')->default(false);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
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

    // ─── Helper: make a clean fulfillment and return its record ──────
    private function createTestFulfillment(int $qty, string $itemType = 'kaporlap', ?int $itemId = null): PksItemFulfillment
    {
        $itemId ??= ($itemType === 'kaporlap' ? $this->kaporlapId : $this->deviceId);
        $qtyDiminta = $itemType === 'kaporlap' ? 10 : 5;

        $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'qty_diminta' => $qtyDiminta,
            'qty' => $qty,
            'catatan' => 'Testing fulfillment '.$itemType,
        ]);

        return PksItemFulfillment::where('pks_id', $this->pksId)
            ->where('site_id', $this->siteId)
            ->where('item_type', $itemType)
            ->where('item_id', $itemId)
            ->first();
    }

    // ─── TEST 1: GET items success ───────────────────────────────────
    /** @test */
    public function test_get_requested_items_returns_list_with_site_id(): void
    {
        $response = $this->getJson("/api/pks-fulfillment/{$this->pksId}/items?site_id={$this->siteId}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Item list retrieved successfully.')
            ->assertJsonCount(3, 'data');
    }

    // ─── TEST 1b: GET items ter-scope per site (multi-site) ──────────
    /** @test */
    public function test_get_requested_items_scoped_per_site(): void
    {
        // Dua quotation_site: 101 (site A) & 102 (site B), masing2 punya 1 detail
        // ID eksplisit agar tidak bentrok dengan quotation_detail_id=1 milik seed
        $detailA = 201;
        DB::table('sl_quotation_detail')->insert([
            'id' => $detailA,
            'quotation_id' => $this->quotationId,
            'quotation_site_id' => 101,
            'nama_site' => 'Site A',
            'jumlah_hc' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $detailB = 202;
        DB::table('sl_quotation_detail')->insert([
            'id' => $detailB,
            'quotation_id' => $this->quotationId,
            'quotation_site_id' => 102,
            'nama_site' => 'Site B',
            'jumlah_hc' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Item kaporlap khusus site A → pakai quotation_detail_id
        DB::table('sl_quotation_kaporlap')->insert([
            'quotation_id' => $this->quotationId,
            'quotation_detail_id' => $detailA,
            'barang_id' => 9,
            'jumlah' => 7,
            'nama' => 'Sepatu Site A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Device khusus site A → pakai quotation_site_id (detail_id NULL, seperti di DB nyata)
        DB::table('sl_quotation_devices')->insert([
            'quotation_id' => $this->quotationId,
            'quotation_detail_id' => null,
            'quotation_site_id' => 101,
            'barang_id' => 10,
            'jumlah' => 3,
            'nama' => 'HT Site A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteA = DB::table('sl_site')->insertGetId([
            'pks_id' => $this->pksId,
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'quotation_site_id' => 101,
            'nama_site' => 'Site A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $siteB = DB::table('sl_site')->insertGetId([
            'pks_id' => $this->pksId,
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'quotation_site_id' => 102,
            'nama_site' => 'Site B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Site A: kaporlap (via detail_id) + device (via quotation_site_id) = 2 item
        $responseA = $this->getJson("/api/pks-fulfillment/{$this->pksId}/items?site_id={$siteA}");
        $responseA->assertOk()->assertJsonCount(2, 'data');
        $namaA = collect($responseA->json('data'))->pluck('nama')->all();
        $this->assertContains('Sepatu Site A', $namaA);
        $this->assertContains('HT Site A', $namaA);

        // Site B: tidak ada item (semua item seed menempel ke site lain / legacy)
        $responseB = $this->getJson("/api/pks-fulfillment/{$this->pksId}/items?site_id={$siteB}");
        $responseB->assertOk()->assertJsonCount(0, 'data');

        // Site legacy tanpa quotation_site_id → tetap tampil semua item quotation
        // (3 seed detail_id=1 + kaporlap Site A + device Site A = 5)
        $responseLegacy = $this->getJson("/api/pks-fulfillment/{$this->pksId}/items?site_id={$this->siteId}");
        $responseLegacy->assertOk()->assertJsonCount(5, 'data');
    }

    // ─── TEST 2: GET items tanpa site_id ─────────────────────────────
    /** @test */
    public function test_get_requested_items_without_site_id_returns_422(): void
    {
        $response = $this->getJson("/api/pks-fulfillment/{$this->pksId}/items");

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'Parameter site_id wajib diisi.');
    }

    // ─── TEST 3: POST fulfillment success ────────────────────────────
    /** @test */
    public function test_store_fulfillment_creates_and_returns_201(): void
    {
        $this->clearFulfillments();

        $response = $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 3,
            'catatan' => 'Mengisi sebagian kaporlap untuk site 1',
        ]);

        // POST ini tahap request: yang naik qty_request, bukan qty_terpenuhi.
        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Fulfillment berhasil disimpan.')
            ->assertJsonPath('data.qty_request', 3)
            ->assertJsonPath('data.qty_terpenuhi', 0)
            ->assertJsonPath('data.status', 'requested');

        $this->assertDatabaseHas('sl_pks_item_fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'item_type' => 'kaporlap',
            'qty_request' => 3,
            'qty_terpenuhi' => 0,
        ]);

        $log = PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)
            ->where('aksi', PksFulfillmentLog::AKSI_REQUEST)->first();
        $this->assertNotNull($log);
        $this->assertSame(3, $log->meta['qty_sesi_ini']);
    }

    // ─── TEST 3a: POST bulk fulfillment ──────────────────────────────
    /** @test */
    public function test_store_bulk_fulfillment_creates_multiple_and_returns_201(): void
    {
        $this->clearFulfillments();

        $response = $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 1,
                    'item_id' => $this->kaporlapId,
                    'qty' => 3,
                    'catatan' => 'Bulk kaporlap batch pertama',
                ],
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 2,
                    'item_id' => $this->deviceId,
                    'qty' => 5,
                    'catatan' => 'Bulk device batch pertama',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('message', '2 fulfillment berhasil disimpan.')
            ->assertJsonCount(2, 'data.fulfillments');

        $this->assertDatabaseHas('sl_pks_item_fulfillment', [
            'pks_id' => $this->pksId,
            'item_type' => 'kaporlap',
            'qty_request' => 3,
            'qty_terpenuhi' => 0,
            'status' => 'requested',
        ]);
        $this->assertDatabaseHas('sl_pks_item_fulfillment', [
            'pks_id' => $this->pksId,
            'item_type' => 'device',
            'qty_request' => 5,
            'qty_terpenuhi' => 0,
            'status' => 'requested',
        ]);

        // Satu log per item
        $this->assertSame(2, PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)->count());
    }

    // ─── TEST 3a-2: POST bulk dengan bare array di root ──────────────
    /** @test */
    public function test_store_bulk_fulfillment_accepts_bare_array_payload(): void
    {
        $this->clearFulfillments();

        $response = $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            [
                'pks_id' => $this->pksId,
                'site_id' => $this->siteId,
                'item_type_id' => 2,
                'item_id' => $this->deviceId,
                'qty' => 2,
                'catatan' => 'Bulk bare array device',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonCount(1, 'data.fulfillments');

        $this->assertDatabaseHas('sl_pks_item_fulfillment', [
            'item_type' => 'device',
            'qty_request' => 2,
        ]);
    }

    // ─── TEST 3a-3: bulk gagal = tidak ada yang tersimpan ────────────
    /** @test */
    public function test_store_bulk_fulfillment_rejects_whole_batch_when_one_item_invalid(): void
    {
        $this->clearFulfillments();

        $response = $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 1,
                    'item_id' => $this->kaporlapId,
                    'qty' => 3,
                    'catatan' => 'Item valid tapi ikut dibatalkan',
                ],
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 2,
                    'item_id' => $this->deviceId,
                    'qty' => 99, // remaining device = 5
                    'catatan' => 'Item ini melebihi remaining',
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['items.1.qty']]);

        $this->assertSame(0, PksItemFulfillment::count());
        $this->assertSame(0, PksFulfillmentLog::count());
    }

    // ─── TEST 3a-4: qty diakumulasi per item dalam satu batch ────────
    /** @test */
    public function test_store_bulk_fulfillment_accumulates_qty_of_duplicate_item(): void
    {
        $this->clearFulfillments();

        // device remaining = 5; 3 + 3 = 6 → harus ditolak
        $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 2,
                    'item_id' => $this->deviceId,
                    'qty' => 3,
                    'catatan' => 'Duplikat device pertama',
                ],
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 2,
                    'item_id' => $this->deviceId,
                    'qty' => 3,
                    'catatan' => 'Duplikat device kedua',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonStructure(['message' => ['items.1.qty']]);

        // 3 + 2 = 5 → pas remaining, harus lolos dan digabung ke satu row
        $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 2,
                    'item_id' => $this->deviceId,
                    'qty' => 3,
                    'catatan' => 'Duplikat device pertama ok',
                ],
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 2,
                    'item_id' => $this->deviceId,
                    'qty' => 2,
                    'catatan' => 'Duplikat device kedua ok',
                ],
            ],
        ])->assertStatus(201);

        $this->assertSame(1, PksItemFulfillment::where('item_type', 'device')->count());
        $this->assertDatabaseHas('sl_pks_item_fulfillment', [
            'item_type' => 'device',
            'qty_request' => 5,
            'qty_terpenuhi' => 0,
            'status' => 'requested',
        ]);
        $this->assertSame(2, PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)->count());
    }

    // ─── TEST 3a-5: satu batch_id untuk seluruh item bulk ────────────
    /** @test */
    public function test_store_bulk_fulfillment_stamps_one_batch_id_on_every_log(): void
    {
        $this->clearFulfillments();

        $response = $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 1,
                    'item_id' => $this->kaporlapId,
                    'qty' => 3,
                    'catatan' => 'Bulk kaporlap batch pertama',
                ],
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 2,
                    'item_id' => $this->deviceId,
                    'qty' => 5,
                    'catatan' => 'Bulk device batch pertama',
                ],
            ],
        ]);

        $response->assertStatus(201);

        $batchId = $response->json('data.batch_id');
        $this->assertNotEmpty($batchId);

        $logs = PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)->get();
        $this->assertCount(2, $logs);
        $this->assertSame([$batchId], $logs->pluck('batch_id')->unique()->values()->all());
        $this->assertSame([1], $logs->pluck('batch_ke')->unique()->values()->all());
        $this->assertSame(1, $response->json('data.batch_ke'));

        // Batch bisa ditarik lewat pks_id + created_at seperti lewat batch_id.
        $this->assertSame(2, PksFulfillmentLog::forPks($this->pksId)
            ->forBatch($batchId)
            ->count());
    }

    // ─── TEST 3a-5b: nomor batch naik per PKS ────────────────────────
    /** @test */
    public function test_bulk_batch_ke_increments_per_pks(): void
    {
        $this->clearFulfillments();

        $kirim = fn (int $qty, int $itemId, int $typeId) => $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => $typeId,
                    'item_id' => $itemId,
                    'qty' => $qty,
                    'catatan' => 'Pengiriman bertahap item fulfillment',
                ],
            ],
        ]);

        $this->assertSame(1, $kirim(2, $this->deviceId, 2)->assertStatus(201)->json('data.batch_ke'));
        $this->assertSame(2, $kirim(3, $this->deviceId, 2)->assertStatus(201)->json('data.batch_ke'));
        $this->assertSame(3, $kirim(4, $this->kaporlapId, 1)->assertStatus(201)->json('data.batch_ke'));

        $this->assertSame(
            [1, 2, 3],
            PksFulfillmentLog::where('pks_id', $this->pksId)
                ->whereNotNull('batch_ke')
                ->orderBy('id')
                ->pluck('batch_ke')
                ->all()
        );
    }

    // --- TEST 3a-6: pengiriman satuan tetap punya batch sendiri ---
    /** @test */
    public function test_store_single_fulfillment_gets_its_own_batch(): void
    {
        $this->clearFulfillments();

        $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'item_type_id' => 2,
            'item_id' => $this->deviceId,
            'qty' => 2,
            'catatan' => 'Pengiriman satuan device',
        ])->assertStatus(201);

        // Satuan = batch berisi satu log, jadi riwayat PKS terbaca seragam.
        $log = PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)->first();
        $this->assertNotNull($log->batch_id);
        $this->assertSame(1, $log->batch_ke);

        $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$log->batch_id}")
            ->assertStatus(200)
            ->assertJsonPath('data.jumlah_item', 1)
            ->assertJsonPath('data.items.0.qty_dikirim', 2);
    }

    // ─── TEST 3a-7: catatan opsional ─────────────────────────────────
    /** @test */
    public function test_fulfillment_accepts_missing_catatan(): void
    {
        $this->clearFulfillments();

        $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'item_type_id' => 2,
            'item_id' => $this->deviceId,
            'qty' => 1,
        ])->assertStatus(201);

        $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 1,
                    'item_id' => $this->kaporlapId,
                    'qty' => 2,
                ],
            ],
        ])->assertStatus(201);

        $this->assertSame(2, PksFulfillmentLog::where('jenis', PksFulfillmentLog::JENIS_ITEM)
            ->whereNull('catatan')
            ->count());
    }

    // ─── TEST 3b: GET log fulfillment per PKS ────────────────────────
    /** @test */
    public function test_get_pks_fulfillment_log_returns_entries(): void
    {
        $this->clearFulfillments();

        $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 3,
            'catatan' => 'Mengisi sebagian kaporlap untuk log',
        ])->assertStatus(201);

        // Log dikelompokkan per batch: satu grup berisi log-lognya.
        $response = $this->getJson("/api/pks-fulfillment/{$this->pksId}/fulfillment-log");
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.0.jenis', 'item')
            ->assertJsonPath('data.0.batch_ke', 1)
            ->assertJsonPath('data.0.jumlah_log', 1)
            ->assertJsonPath('data.0.logs.0.site_id', $this->siteId)
            ->assertJsonPath('data.0.logs.0.catatan', 'Mengisi sebagian kaporlap untuk log')
            ->assertJsonPath('data.0.logs.0.meta.qty_sesi_ini', 3);

        // Filter jenis tidak valid → 422
        $this->getJson("/api/pks-fulfillment/{$this->pksId}/fulfillment-log?jenis=ngawur")
            ->assertStatus(422);

        // Filter jenis=visit → kosong (belum ada visit)
        $this->getJson("/api/pks-fulfillment/{$this->pksId}/fulfillment-log?jenis=visit")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // --- TEST 3c: detail satu batch lewat batch_id ---
    /** @test */
    public function test_get_batch_detail_returns_items_of_that_batch(): void
    {
        $this->clearFulfillments();

        $batchId = $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 1,
                    'item_id' => $this->kaporlapId,
                    'qty' => 3,
                    'catatan' => 'Kirim kaporlap batch detail',
                ],
                [
                    'pks_id' => $this->pksId,
                    'site_id' => $this->siteId,
                    'item_type_id' => 2,
                    'item_id' => $this->deviceId,
                    'qty' => 5,
                    'catatan' => 'Kirim device batch detail',
                ],
            ],
        ])->assertStatus(201)->json('data.batch_id');

        $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$batchId}")
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.batch_id', $batchId)
            ->assertJsonPath('data.batch_ke', 1)
            ->assertJsonPath('data.jenis', 'item')
            ->assertJsonPath('data.jumlah_item', 2)
            ->assertJsonPath('data.items.0.item_type', 'kaporlap')
            ->assertJsonPath('data.items.0.qty_dikirim', 3)
            ->assertJsonPath('data.items.0.catatan', 'Kirim kaporlap batch detail')
            ->assertJsonPath('data.items.1.item_type', 'device')
            ->assertJsonPath('data.items.1.qty_dikirim', 5);
    }

    // --- TEST 3d: batch tidak ada -> 404 ---
    /** @test */
    public function test_get_batch_detail_returns_404_when_batch_missing(): void
    {
        $this->getJson('/api/pks-fulfillment/fulfillment-log/batch/'.Str::uuid())
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // ─── TEST 4: POST fulfillment qty > remaining ────────────────────
    /** @test */
    public function test_store_fulfillment_rejects_qty_exceeding_remaining(): void
    {
        $this->clearFulfillments();

        // First create a fulfillment with qty 5
        $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 5,
            'catatan' => 'Mengisi pertama 5 unit kaporlap',
        ]);

        // Then try to add qty exceeding remaining (remaining = 10 - 5 = 5, so 6 should fail)
        $response = $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 6,
            'catatan' => 'Mengisi lagi 6 unit - harus gagal',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['qty']]);
    }

    // ─── TEST 5: POST fulfillment catatan < 10 char ─────────────────
    /** @test */
    public function test_store_fulfillment_catatan_less_than_10_char_returns_422(): void
    {
        $this->clearFulfillments();

        $response = $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 1,
            'catatan' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message' => ['catatan']]);
    }

    // ─── TEST 6: request penuh lalu diterima penuh ───────────────────
    /** @test */
    public function test_receive_full_marks_item_fully_fulfilled(): void
    {
        $this->clearFulfillments();

        $fulfillmentId = $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 10,
            'catatan' => 'Mengirim lengkap 10 unit kaporlap',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', 'requested')
            ->json('data.id');

        $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillmentId, 'qty' => 10]],
            'catatan' => 'Seluruh barang diterima site',
        ])->assertStatus(201)
            ->assertJsonPath('data.batch_ke', 1)
            ->assertJsonPath('data.items.0.qty_terpenuhi', 10)
            ->assertJsonPath('data.items.0.status', 'fully_fulfilled');

        $this->assertDatabaseHas('sl_pks_item_fulfillment', [
            'id' => $fulfillmentId,
            'qty_request' => 0,
            'qty_terpenuhi' => 10,
            'status' => 'fully_fulfilled',
        ]);
    }

    // ─── TEST 7: diterima kurang, sisanya boleh dikirim lagi ─────────
    /** @test */
    public function test_receive_short_leaves_the_gap_requestable(): void
    {
        $this->clearFulfillments();

        $fulfillmentId = $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 5,
            'catatan' => 'Mengirim 5 dari 10 unit kaporlap',
        ])->assertStatus(201)->json('data.id');

        // Barang yang sudah di jalan tidak boleh dikirim ulang.
        $this->getJson("/api/pks-fulfillment/{$this->pksId}/item-request?site_id={$this->siteId}")
            ->assertStatus(200)
            ->assertJsonPath('data.0.qty_request', 5)
            ->assertJsonPath('data.0.status', 'open');

        $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillmentId, 'qty' => 4]],
            'catatan' => 'Satu unit rusak saat diterima',
        ])->assertStatus(201)
            ->assertJsonPath('data.items.0.kurang', 1)
            ->assertJsonPath('data.items.0.status', 'partially_fulfilled')
            ->assertJsonPath('data.items.0.boleh_direquest', 6);

        // Baris permintaan ditutup sebagai kurang, tidak menggantung.
        $this->getJson("/api/pks-fulfillment/{$this->pksId}/item-request?status=short")
            ->assertStatus(200)
            ->assertJsonPath('data.0.qty_diterima', 4)
            ->assertJsonPath('data.0.kurang', 1);

        // Kekurangan 1 unit itu boleh dikirim ulang bersama sisa 5 lainnya.
        $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 6,
            'catatan' => 'Mengirim ulang sisa kaporlap',
        ])->assertStatus(201)
            ->assertJsonPath('data.qty_request', 6);
    }

    // ─── TEST 7a: terima lebih dari yang dikirim ditolak ─────────────
    /** @test */
    public function test_receive_more_than_sent_returns_422(): void
    {
        $this->clearFulfillments();

        $fulfillmentId = $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 3,
            'catatan' => 'Mengirim 3 unit kaporlap',
        ])->assertStatus(201)->json('data.id');

        $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillmentId, 'qty' => 4]],
        ])->assertStatus(422)
            ->assertJsonStructure(['message' => ['items.0.qty']]);
    }

    // ─── TEST 7b: penerimaan butuh hak akses ─────────────────────────
    /** @test */
    public function test_receive_forbidden_for_non_authorized_role(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(3, 'kaporlap');

        // User dengan role di luar MANAGE_ROLES.
        DB::connection('mysqlhris')->table('m_user')->insert([
            'id' => 3,
            'username' => 'sales-receive',
            'password' => bcrypt('secret'),
            'full_name' => 'Sales User',
            'email' => 'sales-receive@example.com',
            'cais_role_id' => 29,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(3));

        $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillment->id, 'qty' => 1]],
        ])->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    // ─── TEST 8: PATCH edit fulfillment role 8/10/98 ─────────────────
    /** @test */
    public function test_edit_fulfillment_success_with_authorized_role(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(3, 'kaporlap');

        $this->assertNotNull($fulfillment, 'Fulfillment should have been created');

        $response = $this->patchJson("/api/pks-fulfillment/item-fulfillment/{$fulfillment->id}", [
            'new_qty' => 5,
            'catatan' => 'Edit menjadi 5 unit karena update stok',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.qty_terpenuhi', 5);
    }

    // ─── TEST 9: PATCH edit fulfillment role lain ────────────────────
    /** @test */
    public function test_edit_fulfillment_forbidden_for_non_authorized_role(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(3, 'kaporlap');

        $this->assertNotNull($fulfillment, 'Fulfillment should have been created');

        // Create and switch to a second user with non-authorized role 29
        DB::connection('mysqlhris')->table('m_user')->insert([
            'id' => 2,
            'username' => 'sales',
            'password' => bcrypt('secret'),
            'full_name' => 'Sales User',
            'email' => 'sales@example.com',
            'cais_role_id' => 29,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user2 = User::query()->findOrFail(2);
        $this->actingAs($user2); // use default guard

        $response = $this->patchJson("/api/pks-fulfillment/item-fulfillment/{$fulfillment->id}", [
            'new_qty' => 5,
            'catatan' => 'Edit dari user sales - harus ditolak',
        ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    // ─── TEST 10: GET fulfillment log ────────────────────────────────
    /** @test */
    public function test_get_fulfillment_log_returns_list(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(5, 'kaporlap');

        $this->assertNotNull($fulfillment, 'Fulfillment should have been created');

        // Then edit to create second log entry (switch back to role 8 if needed)
        DB::connection('mysqlhris')->table('m_user')->where('id', 1)->update(['cais_role_id' => 8]);

        $this->patchJson("/api/pks-fulfillment/item-fulfillment/{$fulfillment->id}", [
            'new_qty' => 8,
            'catatan' => 'Edit menjadi 8 unit karena ada tambahan',
        ]);

        $response = $this->getJson("/api/pks-fulfillment/item-fulfillment/{$fulfillment->id}/log");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Fulfillment log retrieved successfully.');
    }

    // ─── TEST 11: GET fulfillment log nonexistent ─────────────────────
    /** @test */
    public function test_get_fulfillment_log_returns_empty_for_nonexistent(): void
    {
        $response = $this->getJson('/api/pks-fulfillment/item-fulfillment/99999/log');

        // Route model binding returns 404 for non-existent records
        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // ─── TEST 12: baris request menyimpan tautan ke batch penerimaan ──
    /** @test */
    public function test_received_batch_id_links_request_row_to_receive_batch(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(4, 'kaporlap');

        // Selama belum diterima, tautannya belum ada.
        $this->getJson("/api/pks-fulfillment/{$this->pksId}/item-request?site_id={$this->siteId}")
            ->assertOk()
            ->assertJsonPath('data.0.status', 'open')
            ->assertJsonPath('data.0.received_batch_id', null)
            ->assertJsonPath('data.0.request_batch_id', fn ($v) => is_string($v) && $v !== '');

        $receiveBatchId = $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillment->id, 'qty' => 4]],
            'catatan' => 'Seluruh barang diterima site',
        ])->assertStatus(201)->json('data.batch_id');

        $row = $this->getJson("/api/pks-fulfillment/{$this->pksId}/item-request?status=received")
            ->assertOk()
            ->assertJsonPath('data.0.received_batch_id', $receiveBatchId)
            ->json('data.0');

        // Inti perbaikan: batch pengiriman dan penerimaan adalah dua batch
        // berbeda, dan barisnya sekarang mengenal keduanya.
        $this->assertNotSame($row['request_batch_id'], $row['received_batch_id']);

        // Membuka received_batch_id memberi batch penerimaan, bukan pengiriman.
        $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$receiveBatchId}")
            ->assertOk()
            ->assertJsonPath('data.aksi', 'receive');
    }

    // ─── TEST 13: batch pengiriman menjawab sudah diterima atau belum ─
    /** @test */
    public function test_request_batch_detail_reports_receiving_status(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(4, 'kaporlap');

        $requestBatchId = PksFulfillmentLog::where('reference_id', $fulfillment->id)
            ->where('aksi', PksFulfillmentLog::AKSI_REQUEST)
            ->value('batch_id');

        $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$requestBatchId}")
            ->assertOk()
            ->assertJsonPath('data.aksi', 'request')
            ->assertJsonPath('data.status_penerimaan', 'belum')
            ->assertJsonPath('data.jumlah_menunggu', 1)
            ->assertJsonPath('data.items.0.penerimaan.status', 'open')
            ->assertJsonPath('data.items.0.penerimaan.batch_id', null);

        $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillment->id, 'qty' => 3]],
            'catatan' => 'Satu unit rusak saat diterima',
        ])->assertStatus(201);

        // aksi tetap 'request' — itu jenis batch, bukan status. Yang menjawab
        // "sudah diterima belum" adalah status_penerimaan.
        $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$requestBatchId}")
            ->assertOk()
            ->assertJsonPath('data.aksi', 'request')
            // Bukan 'selesai': tidak ada lagi yang ditunggu, tapi barangnya kurang 1.
            ->assertJsonPath('data.status_penerimaan', 'selesai_kurang')
            ->assertJsonPath('data.jumlah_diterima', 1)
            ->assertJsonPath('data.jumlah_menunggu', 0)
            ->assertJsonPath('data.jumlah_kurang', 1)
            ->assertJsonPath('data.qty_kurang', 1)
            ->assertJsonPath('data.jumlah_tanpa_tautan', 0)
            ->assertJsonPath('data.items.0.penerimaan.status', 'short')
            ->assertJsonPath('data.items.0.penerimaan.qty_diterima', 3)
            ->assertJsonPath('data.items.0.penerimaan.kurang', 1)
            ->assertJsonPath('data.items.0.penerimaan.batch_id', fn ($v) => is_string($v) && $v !== '');
    }

    // ─── TEST 13a: batch dengan sebagian item saja yang diterima ─────
    /** @test */
    public function test_request_batch_detail_reports_partial_receiving(): void
    {
        $this->clearFulfillments();

        $batchId = $this->postJson('/api/pks-fulfillment/item-fulfillment/bulk', [
            'items' => [
                ['pks_id' => $this->pksId, 'site_id' => $this->siteId, 'item_type_id' => 1, 'item_id' => $this->kaporlapId, 'qty' => 3, 'catatan' => 'Bulk kaporlap batch pertama'],
                ['pks_id' => $this->pksId, 'site_id' => $this->siteId, 'item_type_id' => 2, 'item_id' => $this->deviceId, 'qty' => 5, 'catatan' => 'Bulk device batch pertama'],
            ],
        ])->assertStatus(201)->json('data.batch_id');

        $kaporlap = PksItemFulfillment::where('item_type', 'kaporlap')->firstOrFail();

        // Hanya satu dari dua item yang diterima.
        $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $kaporlap->id, 'qty' => 3]],
            'catatan' => 'Kaporlap diterima, device menyusul',
        ])->assertStatus(201);

        $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$batchId}")
            ->assertOk()
            ->assertJsonPath('data.jumlah_item', 2)
            ->assertJsonPath('data.status_penerimaan', 'sebagian')
            ->assertJsonPath('data.jumlah_diterima', 1)
            ->assertJsonPath('data.jumlah_menunggu', 1)
            ->assertJsonPath('data.jumlah_kurang', 0)
            ->assertJsonPath('data.qty_kurang', 0);
    }

    // ─── TEST 13b: rekap tetap konsisten walau ada log lama ──────────
    /** @test */
    public function test_rollup_accounts_for_legacy_logs_without_request_link(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(4, 'kaporlap');

        $batchId = PksFulfillmentLog::where('reference_id', $fulfillment->id)
            ->where('aksi', PksFulfillmentLog::AKSI_REQUEST)
            ->value('batch_id');

        // Log gaya lama: tidak punya meta.request_id, jadi tidak bisa ditautkan
        // ke baris permintaan mana pun.
        PksFulfillmentLog::create([
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'jenis' => PksFulfillmentLog::JENIS_ITEM,
            'reference_id' => $fulfillment->id,
            'batch_id' => $batchId,
            'batch_ke' => 1,
            'aksi' => PksFulfillmentLog::AKSI_REQUEST,
            'meta' => ['qty_sesi_ini' => 2],
            'created_by' => 'Test User',
        ]);

        $data = $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$batchId}")
            ->assertOk()
            ->assertJsonPath('data.jumlah_tanpa_tautan', 1)
            ->json('data');

        // Inti M3: tidak ada item yang seolah hilang dari hitungan.
        $this->assertSame(
            $data['jumlah_item'],
            $data['jumlah_diterima'] + $data['jumlah_menunggu'] + $data['jumlah_tanpa_tautan'],
            'Rekap harus menjumlah persis sebanyak item dalam batch'
        );
    }

    // ─── TEST 13c: daftar log per PKS membawa rekap penerimaannya ────
    /** @test */
    public function test_pks_log_list_carries_receiving_rollup_per_batch(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(4, 'kaporlap');

        $batchId = PksFulfillmentLog::where('reference_id', $fulfillment->id)
            ->where('aksi', PksFulfillmentLog::AKSI_REQUEST)
            ->value('batch_id');

        $belum = collect($this->getJson("/api/pks-fulfillment/{$this->pksId}/fulfillment-log?jenis=item")->assertOk()->json('data'))
            ->firstWhere('batch_id', $batchId);

        $this->assertSame('belum', $belum['status_penerimaan']);
        $this->assertSame(1, $belum['jumlah_menunggu']);

        $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillment->id, 'qty' => 4]],
            'catatan' => 'Seluruh barang diterima site',
        ])->assertStatus(201);

        $groups = collect($this->getJson("/api/pks-fulfillment/{$this->pksId}/fulfillment-log?jenis=item")->assertOk()->json('data'));

        // Batch pengiriman kini menunjukkan sudah beres, tanpa perlu dibuka.
        $kirim = $groups->firstWhere('batch_id', $batchId);
        $this->assertSame('request', $kirim['aksi']);
        $this->assertSame('selesai', $kirim['status_penerimaan']);
        $this->assertSame(1, $kirim['jumlah_diterima']);

        // Batch penerimaannya sendiri tidak ikut punya rekap.
        $terima = $groups->firstWhere('aksi', 'receive');
        $this->assertNotNull($terima);
        $this->assertNull($terima['status_penerimaan']);
    }

    // ─── TEST 13d: blok penerimaan bersarang, tidak menabrak kunci lain ─
    /** @test */
    public function test_receiving_block_is_nested_and_does_not_collide(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(4, 'kaporlap');

        $requestBatchId = PksFulfillmentLog::where('reference_id', $fulfillment->id)
            ->where('aksi', PksFulfillmentLog::AKSI_REQUEST)
            ->value('batch_id');

        $receiveBatchId = $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillment->id, 'qty' => 3]],
            'catatan' => 'Satu unit rusak saat diterima',
        ])->assertStatus(201)->json('data.batch_id');

        // Batch pengiriman: angka penerimaan hidup di dalam `penerimaan`.
        $kirim = $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$requestBatchId}")
            ->assertOk()->json('data.items.0');

        $this->assertSame(4, $kirim['qty_dikirim']);
        $this->assertArrayNotHasKey('qty_diterima', $kirim, 'qty_diterima tidak boleh datar di batch pengiriman');
        $this->assertArrayNotHasKey('kurang', $kirim);
        $this->assertSame(3, $kirim['penerimaan']['qty_diterima']);
        $this->assertSame(1, $kirim['penerimaan']['kurang']);
        $this->assertSame($receiveBatchId, $kirim['penerimaan']['batch_id']);

        // Batch penerimaan: angkanya milik sesi itu sendiri, tetap datar.
        $terima = $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$receiveBatchId}")
            ->assertOk()->json('data.items.0');

        $this->assertSame(3, $terima['qty_diterima']);
        $this->assertSame(1, $terima['kurang']);
        $this->assertArrayNotHasKey('penerimaan', $terima);
    }

    // ─── TEST 14: batch penerimaan tidak ikut punya rekap penerimaan ──
    /** @test */
    public function test_receive_batch_detail_has_no_receiving_rollup(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(4, 'kaporlap');

        $receiveBatchId = $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillment->id, 'qty' => 4]],
            'catatan' => 'Seluruh barang diterima site',
        ])->assertStatus(201)->json('data.batch_id');

        $this->getJson("/api/pks-fulfillment/fulfillment-log/batch/{$receiveBatchId}")
            ->assertOk()
            ->assertJsonPath('data.aksi', 'receive')
            ->assertJsonPath('data.status_penerimaan', null)
            ->assertJsonPath('data.items.0.qty_diterima', 4);
    }

    // ─── TEST 15: log per-fulfillment tidak lagi menulis 0 untuk receive ─
    /** @test */
    public function test_fulfillment_log_shows_real_numbers_for_receive_action(): void
    {
        $this->clearFulfillments();
        $fulfillment = $this->createTestFulfillment(4, 'kaporlap'); // qty_diminta 10

        $this->postJson('/api/pks-fulfillment/item-fulfillment/receive', [
            'items' => [['fulfillment_id' => $fulfillment->id, 'qty' => 3]],
            'catatan' => 'Satu unit rusak saat diterima',
        ])->assertStatus(201);

        $logs = $this->getJson("/api/pks-fulfillment/item-fulfillment/{$fulfillment->id}/log")
            ->assertOk()
            ->json('data');

        $receive = collect($logs)->firstWhere('aksi', 'receive');

        $this->assertNotNull($receive, 'Log penerimaan harus ada');
        $this->assertSame(3, $receive['qty_diterima']);
        $this->assertSame(3, $receive['qty_sesi_ini'], 'qty_sesi_ini tidak boleh 0 untuk aksi receive');
        $this->assertSame(4, $receive['qty_request_ditutup']);
        $this->assertSame(1, $receive['kurang']);
        $this->assertSame(10, $receive['remaining_sebelum']);
        $this->assertSame(7, $receive['remaining_sesudah']);
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
