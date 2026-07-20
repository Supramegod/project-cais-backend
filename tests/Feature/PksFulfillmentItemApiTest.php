<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckTokenExpiry;
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

        $databasePath = storage_path('framework/testing-' . Str::random(8) . '.sqlite');
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
        DB::table('sl_pks_item_fulfillment_log')->delete();
        DB::table('sl_pks_item_fulfillment')->delete();
    }

    // ─── Schema ────────────────────────────────────────────────────────

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_pks_item_fulfillment_log',
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
            $table->unsignedInteger('qty_terpenuhi')->default(0);
            $table->string('status')->default('not_yet_fulfilled');
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks_item_fulfillment_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('fulfillment_id');
            $table->string('aksi');
            $table->unsignedInteger('qty_sesi_ini')->default(0);
            $table->unsignedInteger('remaining_sebelum')->default(0);
            $table->unsignedInteger('remaining_sesudah')->default(0);
            $table->text('catatan')->nullable();
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
            'catatan' => 'Testing fulfillment ' . $itemType,
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

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Fulfillment berhasil disimpan.')
            ->assertJsonPath('data.qty_terpenuhi', 3)
            ->assertJsonPath('data.status', 'partially_fulfilled');

        $this->assertDatabaseHas('sl_pks_item_fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'item_type' => 'kaporlap',
            'qty_terpenuhi' => 3,
        ]);

        $this->assertDatabaseHas('sl_pks_item_fulfillment_log', [
            'aksi' => 'create',
            'qty_sesi_ini' => 3,
        ]);
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

    // ─── TEST 6: POST fulfillment fully fulfilled ────────────────────
    /** @test */
    public function test_store_fulfillment_fully_fulfilled_when_qty_equals_remaining(): void
    {
        $this->clearFulfillments();

        $response = $this->postJson('/api/pks-fulfillment/item-fulfillment', [
            'pks_id' => $this->pksId,
            'site_id' => $this->siteId,
            'leads_id' => $this->leadsId,
            'item_type' => 'kaporlap',
            'item_id' => $this->kaporlapId,
            'qty_diminta' => 10,
            'qty' => 10,
            'catatan' => 'Mengisi lengkap 10 unit kaporlap',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.qty_terpenuhi', 10)
            ->assertJsonPath('data.status', 'fully_fulfilled');
    }

    // ─── TEST 7: POST fulfillment partially fulfilled ────────────────
    /** @test */
    public function test_store_fulfillment_partially_fulfilled_when_qty_less_than_remaining(): void
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
            'catatan' => 'Mengisi 3 dari 10 unit kaporlap',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.qty_terpenuhi', 3)
            ->assertJsonPath('data.status', 'partially_fulfilled');
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
}
