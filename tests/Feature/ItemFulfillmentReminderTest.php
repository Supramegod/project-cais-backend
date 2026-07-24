<?php

namespace Tests\Feature;

use App\Jobs\SendItemFulfillmentReminder;
use App\Mail\ItemFulfillmentReminderNotification;
use App\Models\Pks;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the mid-contract item fulfillment reminder job.
 *
 * @group pks-fulfillment
 */
class ItemFulfillmentReminderTest extends TestCase
{
    private int $leadsId;
    private int $quotationId;
    private int $kaporlapId;
    private int $deviceId;
    private int $chemicalId;

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

        // PIC user (mysqlhris m_user) — jadi penerima email.
        DB::connection('mysqlhris')->table('m_user')->insert([
            'id' => 1,
            'username' => 'crmuser',
            'password' => bcrypt('secret'),
            'full_name' => 'CRM User',
            'email' => 'crm@example.com',
            'cais_role_id' => 8,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedBaseData();
    }

    private function seedBaseData(): void
    {
        $this->leadsId = DB::table('sl_leads')->insertGetId([
            'nomor' => 'LEAD-001',
            'nama_perusahaan' => 'PT Test Reminder',
            'kebutuhan_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->quotationId = DB::table('sl_quotation')->insertGetId([
            'leads_id' => $this->leadsId,
            'nomor' => 'Q-001',
            'is_aktif' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->kaporlapId = DB::table('sl_quotation_kaporlap')->insertGetId([
            'quotation_id' => $this->quotationId,
            'quotation_detail_id' => 1,
            'barang_id' => 1,
            'jumlah' => 10,
            'nama' => 'Seragam Security',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deviceId = DB::table('sl_quotation_devices')->insertGetId([
            'quotation_id' => $this->quotationId,
            'quotation_detail_id' => 1,
            'barang_id' => 2,
            'jumlah' => 5,
            'nama' => 'HT Radio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->chemicalId = DB::table('sl_quotation_chemical')->insertGetId([
            'quotation_id' => $this->quotationId,
            'quotation_detail_id' => 1,
            'barang_id' => 3,
            'jumlah' => 20,
            'nama' => 'Disinfectant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Buat satu PKS aktif beserta site-nya. Return id PKS.
     */
    private function createPks(Carbon $awal, Carbon $akhir, ?Carbon $remindedAt = null): int
    {
        $pksId = DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'nomor' => 'PKS/TEST/001',
            'nama_perusahaan' => 'PT Test Reminder',
            'status_pks_id' => 7,
            'layanan_id' => 1,
            'kontrak_awal' => $awal->toDateString(),
            'kontrak_akhir' => $akhir->toDateString(),
            'is_aktif' => 1,
            'tipe_pks' => 'baru',
            'crm_id_1' => 1,
            'item_fulfillment_reminded_at' => $remindedAt?->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sl_site')->insert([
            'pks_id' => $pksId,
            'leads_id' => $this->leadsId,
            'quotation_id' => $this->quotationId,
            'kota_id' => 1,
            'kota' => 'Jakarta',
            'nama_site' => 'Site Test 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $pksId;
    }

    /**
     * Isi penuh semua item pada site pertama PKS agar remaining = 0.
     */
    private function fullyFulfill(int $pksId): void
    {
        $siteId = DB::table('sl_site')->where('pks_id', $pksId)->value('id');

        foreach ([
            ['kaporlap', $this->kaporlapId, 10],
            ['device', $this->deviceId, 5],
            ['chemical', $this->chemicalId, 20],
        ] as [$type, $itemId, $qty]) {
            DB::table('sl_pks_item_fulfillment')->insert([
                'pks_id' => $pksId,
                'site_id' => $siteId,
                'leads_id' => $this->leadsId,
                'item_type' => $type,
                'item_id' => $itemId,
                'qty_diminta' => $qty,
                'qty_terpenuhi' => $qty,
                'status' => 'fully_fulfilled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ─── Schema ────────────────────────────────────────────────────────

    private function rebuildSchema(): void
    {
        foreach ([
            'sl_pks_fulfillment_log',
            'sl_pks_item_fulfillment',
            'sl_quotation_chemical',
            'sl_quotation_devices',
            'sl_quotation_kaporlap',
            'sl_quotation_detail',
            'sl_quotation',
            'sl_site',
            'sl_pks',
            'sl_leads',
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
            $table->boolean('is_aktif')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->string('nama_site')->nullable();
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
            $table->string('tipe_pks')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_pks')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->date('kontrak_awal')->nullable();
            $table->date('kontrak_akhir')->nullable();
            $table->unsignedInteger('status_pks_id')->nullable();
            $table->unsignedInteger('layanan_id')->nullable();
            $table->boolean('is_aktif')->nullable();
            $table->unsignedInteger('crm_id_1')->nullable();
            $table->unsignedInteger('crm_id_2')->nullable();
            $table->unsignedInteger('crm_id_3')->nullable();
            $table->unsignedInteger('ro_id_1')->nullable();
            $table->unsignedInteger('ro_id_2')->nullable();
            $table->unsignedInteger('ro_id_3')->nullable();
            $table->unsignedInteger('spv_ro_id')->nullable();
            $table->timestamp('item_fulfillment_reminded_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
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

    // ─── TEST 1: after midpoint + pending items → email sent ──────────
    /** @test */
    public function test_reminder_sent_when_past_midpoint_with_pending_items(): void
    {
        Mail::fake();

        $pksId = $this->createPks(now()->subMonths(8), now()->addMonths(4));

        (new SendItemFulfillmentReminder())->handle();

        Mail::assertSent(ItemFulfillmentReminderNotification::class, 1);

        $this->assertNotNull(
            Pks::findOrFail($pksId)->item_fulfillment_reminded_at,
            'reminded_at should be set after sending'
        );
    }

    // ─── TEST 2: all items fulfilled → no email ──────────────────────
    /** @test */
    public function test_no_reminder_when_all_items_fulfilled(): void
    {
        Mail::fake();

        $pksId = $this->createPks(now()->subMonths(8), now()->addMonths(4));
        $this->fullyFulfill($pksId);

        (new SendItemFulfillmentReminder())->handle();

        Mail::assertNothingSent();
    }

    // ─── TEST 3: before midpoint → no email ──────────────────────────
    /** @test */
    public function test_no_reminder_before_midpoint(): void
    {
        Mail::fake();

        $this->createPks(now()->subDay(), now()->addYear());

        (new SendItemFulfillmentReminder())->handle();

        Mail::assertNothingSent();
    }

    // ─── TEST 4: already reminded → no duplicate email ───────────────
    /** @test */
    public function test_no_duplicate_reminder_when_already_reminded(): void
    {
        Mail::fake();

        $this->createPks(now()->subMonths(8), now()->addMonths(4), now()->subDay());

        (new SendItemFulfillmentReminder())->handle();

        Mail::assertNothingSent();
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
