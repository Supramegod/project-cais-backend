<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotation\QuotationBarangService;
use App\Services\Quotation\QuotationService;
use App\Services\Quotation\Steps\Step11Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Characterization (golden-snapshot) test for the quotation calculation engine.
 *
 * Purpose: lock the CURRENT computed output of QuotationService::calculateQuotation()
 * (and a couple of adjacent write paths) so an upcoming refactor can be verified
 * to preserve behavior. Expected values here are captured from real runs, not spec.
 *
 * The base sl_quotation* tables are NOT created by migrations (prod imports a MySQL
 * dump; migrations only ADD columns). We therefore build the schema by hand in
 * setUp() and point the sqlite / mysql / mysqlhris connections at one temp file,
 * mirroring tests/Feature/PksWizardFinalizeTest.php.
 */
class QuotationCalculationCharacterizationTest extends TestCase
{
    private const QUOTATION_ID = 100;
    private const SITE_A = 200;
    private const SITE_B = 201;
    private const DETAIL_1 = 300; // site A, HC 2, THR+kompensasi diprovisikan, has tunjangan + kaporlap
    private const DETAIL_2 = 301; // site A, HC 1, flat lembur ditagihkan
    private const DETAIL_3 = 302; // site B, HC 3, plain

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
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
    }

    private function seedUser(): User
    {
        DB::table('m_user')->insert([
            'id' => 1,
            'username' => 'tester',
            'password' => bcrypt('secret'),
            'full_name' => 'Tester',
            'email' => 'tester@example.com',
            'cais_role_id' => 2,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail(1);
    }

    /**
     * Seed one multi-site quotation with 3 details across 2 sites plus wage/hpp/coss
     * rows, a tunjangan, a management-fee config, and a kaporlap item.
     */
    private function seedQuotation(): void
    {
        DB::table('m_management_fee')->insert([
            'id' => 1, 'nama' => 'Dari Base Manpower', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('sl_quotation')->insert([
            'id' => self::QUOTATION_ID,
            'leads_id' => 10,
            'kebutuhan_id' => 3,
            'kebutuhan' => 'Cleaning Service',
            'nomor' => 'Q-CHAR-001',
            'status_quotation_id' => 3,
            'company_id' => 13,
            'salary_rule_id' => 1,
            'rule_thr_id' => 2,
            'management_fee_id' => 1,
            'persentase' => 10,
            'durasi_kerjasama' => '12 bulan',
            'jenis_kontrak' => 'TERPADU',
            'hari_kerja' => '25',
            'top' => 'TOP',
            'persen_bunga_bank' => 1,
            'persen_insentif' => 2,
            'is_ppn' => 'Ya',
            'ppn_pph_dipotong' => 'Management Fee',
            'resiko' => 'Rendah',
            'program_bpjs' => 'BPJS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sl_quotation_site')->insert([
            ['id' => self::SITE_A, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => 10, 'nama_site' => 'Site A', 'nominal_upah' => 5000000, 'umk' => 4000000, 'ump' => 3800000, 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::SITE_B, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => 10, 'nama_site' => 'Site B', 'nominal_upah' => 6000000, 'umk' => 5000000, 'ump' => 4800000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_detail')->insert([
            ['id' => self::DETAIL_1, 'quotation_id' => self::QUOTATION_ID, 'quotation_site_id' => self::SITE_A, 'nama_site' => 'Site A', 'position_id' => 10, 'jabatan_kebutuhan' => 'Cleaner', 'jumlah_hc' => 2, 'nominal_upah' => 5000000, 'penjamin_kesehatan' => 'BPJS', 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::DETAIL_2, 'quotation_id' => self::QUOTATION_ID, 'quotation_site_id' => self::SITE_A, 'nama_site' => 'Site A', 'position_id' => 11, 'jabatan_kebutuhan' => 'Supervisor', 'jumlah_hc' => 1, 'nominal_upah' => 4500000, 'penjamin_kesehatan' => 'BPJS', 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::DETAIL_3, 'quotation_id' => self::QUOTATION_ID, 'quotation_site_id' => self::SITE_B, 'nama_site' => 'Site B', 'position_id' => 12, 'jabatan_kebutuhan' => 'Leader', 'jumlah_hc' => 3, 'nominal_upah' => 6000000, 'penjamin_kesehatan' => 'BPJS', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_detail_wages')->insert(['quotation_detail_id' => self::DETAIL_1, 'quotation_id' => self::QUOTATION_ID, 'lembur' => 'Tidak Ada', 'nominal_lembur' => 0, 'jenis_bayar_lembur' => null, 'jam_per_bulan_lembur' => 0, 'lembur_ditagihkan' => 'Tidak Ditagihkan', 'kompensasi' => 'Diprovisikan', 'thr' => 'Diprovisikan', 'tunjangan_holiday' => 'Tidak Ada', 'nominal_tunjangan_holiday' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_quotation_detail_wages')->insert(['quotation_detail_id' => self::DETAIL_2, 'quotation_id' => self::QUOTATION_ID, 'lembur' => 'Flat', 'nominal_lembur' => 100000, 'jenis_bayar_lembur' => 'Per Bulan', 'jam_per_bulan_lembur' => 0, 'lembur_ditagihkan' => 'Ditagihkan', 'kompensasi' => 'Tidak Ada', 'thr' => 'Tidak Ada', 'tunjangan_holiday' => 'Tidak Ada', 'nominal_tunjangan_holiday' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sl_quotation_detail_wages')->insert(['quotation_detail_id' => self::DETAIL_3, 'quotation_id' => self::QUOTATION_ID, 'lembur' => 'Tidak Ada', 'nominal_lembur' => 0, 'jenis_bayar_lembur' => null, 'jam_per_bulan_lembur' => 0, 'lembur_ditagihkan' => 'Tidak Ditagihkan', 'kompensasi' => 'Tidak Ada', 'thr' => 'Tidak Ada', 'tunjangan_holiday' => 'Tidak Ada', 'nominal_tunjangan_holiday' => 0, 'created_at' => now(), 'updated_at' => now()]);

        // HPP & COSS rows: mostly-null so the engine computes values from scratch.
        foreach ([self::DETAIL_1, self::DETAIL_2, self::DETAIL_3] as $detailId) {
            DB::table('sl_quotation_detail_hpp')->insert([
                'quotation_detail_id' => $detailId, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => 10, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('sl_quotation_detail_coss')->insert([
                'quotation_detail_id' => $detailId, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => 10, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // One tunjangan on detail 1 (jenis Reguler → counted in totals).
        DB::table('sl_quotation_detail_tunjangan')->insert([
            'quotation_detail_id' => self::DETAIL_1, 'quotation_id' => self::QUOTATION_ID, 'nama_tunjangan' => 'Tunjangan Transport', 'nominal' => 200000, 'nominal_coss' => 150000, 'jenis' => 'Reguler', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // One kaporlap item scoped to detail 1.
        DB::table('sl_quotation_kaporlap')->insert([
            'quotation_detail_id' => self::DETAIL_1, 'quotation_id' => self::QUOTATION_ID, 'barang_id' => 1, 'jumlah' => 2, 'harga' => 60000, 'nama' => 'Seragam', 'jenis_barang_id' => 1, 'jenis_barang' => 'Kaporlap', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Management fee config (all flags true) — present but not used by the active OLD formula.
        DB::table('sl_quotation_management_fee')->insert([
            'quotation_id' => self::QUOTATION_ID,
            'is_thr' => 1, 'is_kompensasi' => 1, 'is_thl' => 1, 'is_lembur' => 1,
            'is_bpjs_kes' => 1, 'is_bpjs_tk' => 1, 'is_chemical' => 1, 'is_kaporlap' => 1,
            'is_device' => 1, 'is_ohc' => 1, 'is_tunjangan_lain' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_calculate_quotation_matches_golden_snapshot(): void
    {
        $user = $this->seedUser();
        $this->seedQuotation();
        $this->actingAs($user);

        $quotation = Quotation::findOrFail(self::QUOTATION_ID);
        $result = app(QuotationService::class)->calculateQuotation($quotation);

        $s = $result->calculation_summary;
        $d = 0.01; // rupiah-level tolerance for float aggregation

        // ── Top-level HPP summary (golden) ───────────────────────────────────
        $this->assertSame(6, (int) $quotation->jumlah_hc);
        $this->assertEqualsWithDelta(38559088.32, $s->total_sebelum_management_fee, $d);
        $this->assertEqualsWithDelta(3290000.0, $s->nominal_management_fee, $d);
        $this->assertEqualsWithDelta(41849088.32, $s->grand_total_sebelum_pajak, $d);
        $this->assertEqualsWithDelta(361900.0, $s->ppn, $d);
        $this->assertEqualsWithDelta(-65800.0, $s->pph, $d);
        $this->assertEqualsWithDelta(42145188.32, $s->total_invoice, $d);
        $this->assertEqualsWithDelta(42146000.0, $s->pembulatan, $d);
        $this->assertEqualsWithDelta(3290000.0, $s->margin, $d);
        $this->assertEqualsWithDelta(7.861581057257307, $s->gpm, 1e-9);
        $this->assertEqualsWithDelta(32500000.0, $s->upah_pokok, $d);
        $this->assertEqualsWithDelta(2125500.0, $s->total_bpjs, $d);
        $this->assertEqualsWithDelta(1300000.0, $s->total_bpjs_kesehatan, $d);
        $this->assertEqualsWithDelta(32900000.0, $s->total_base_manpower, $d);
        $this->assertEqualsWithDelta(63520.2778, $s->bunga_bank_total, 1e-4);
        $this->assertEqualsWithDelta(10966.666666666666, $s->insentif_total, 1e-6);

        // ── Top-level COSS summary (golden) ──────────────────────────────────
        $this->assertEqualsWithDelta(38012166.68, $s->total_sebelum_management_fee_coss, $d);
        $this->assertEqualsWithDelta(3280000.0, $s->nominal_management_fee_coss, $d);
        $this->assertEqualsWithDelta(41292166.68, $s->grand_total_sebelum_pajak_coss, $d);
        $this->assertEqualsWithDelta(2733078.36, $s->margin_coss, $d);

        // ── Per-detail HPP golden values ─────────────────────────────────────
        $hpp1 = $result->detail_calculations[self::DETAIL_1]->hpp_data;
        $this->assertSame(2, (int) $hpp1['jumlah_hc']);
        $this->assertEqualsWithDelta(5000000.0, (float) $hpp1['gaji_pokok'], $d);
        $this->assertEqualsWithDelta(200000.0, (float) $hpp1['total_tunjangan'], $d);
        $this->assertEqualsWithDelta(416666.67, (float) $hpp1['tunjangan_hari_raya'], $d);
        $this->assertEqualsWithDelta(416666.67, (float) $hpp1['kompensasi'], $d);
        $this->assertEqualsWithDelta(27000.0, (float) $hpp1['bpjs_jkk'], $d);
        $this->assertEqualsWithDelta(200000.0, (float) $hpp1['bpjs_ks'], $d);
        $this->assertEqualsWithDelta(10000.0, (float) $hpp1['provisi_seragam'], $d);
        $this->assertEqualsWithDelta(6644820.28, (float) $hpp1['total_biaya_per_personil'], $d);
        $this->assertEqualsWithDelta(13289640.56, (float) $hpp1['total_biaya_all_personil'], $d);

        $coss1 = $result->detail_calculations[self::DETAIL_1]->coss_data;
        $this->assertEqualsWithDelta(5150000.0, (float) $coss1['total_base_manpower'], $d);
        $this->assertEqualsWithDelta(1370333.34, (float) $coss1['total_exclude_base_manpower'], $d);
        $this->assertEqualsWithDelta(6520333.34, (float) $coss1['total_personil_coss'], $d);
        $this->assertEqualsWithDelta(13040666.68, (float) $coss1['sub_total_personil_coss'], $d);

        // Detail 2: flat lembur that is "Ditagihkan" → lembur folded into HPP as 100000.
        $hpp2 = $result->detail_calculations[self::DETAIL_2]->hpp_data;
        $this->assertEqualsWithDelta(100000.0, (float) $hpp2['lembur'], $d);
        $this->assertEqualsWithDelta(5148786.94, (float) $hpp2['total_biaya_per_personil'], $d);

        // Detail 3: plain, HC 3.
        $hpp3 = $result->detail_calculations[self::DETAIL_3]->hpp_data;
        $this->assertSame(3, (int) $hpp3['jumlah_hc']);
        $this->assertEqualsWithDelta(6706886.94, (float) $hpp3['total_biaya_per_personil'], $d);
        $this->assertEqualsWithDelta(20120660.82, (float) $hpp3['total_biaya_all_personil'], $d);

        // Persisted cache marker.
        $this->assertNotNull(DB::table('sl_quotation')->find(self::QUOTATION_ID)->calculated_at);
    }

    public function test_step11_execute_persists_recalculated_hpp_and_coss(): void
    {
        $user = $this->seedUser();
        $this->seedQuotation();
        $this->seedStep11MasterData();
        $this->actingAs($user);

        $quotation = Quotation::findOrFail(self::QUOTATION_ID);

        // Representative Step 11 payload: adjust wage on detail 2 and edit an HPP field.
        $request = Request::create('/', 'POST', [
            'penagihan' => 'Transfer',
            'persentase' => 10,
            'wage_data' => [
                self::DETAIL_2 => [
                    'lembur' => 'Tidak Ada',
                    'nominal_lembur' => 0,
                    'lembur_ditagihkan' => 'Tidak Ditagihkan',
                ],
            ],
            'hpp_editable_data' => [
                self::DETAIL_1 => ['provisi_peralatan' => 25000],
            ],
        ]);

        app(Step11Service::class)->execute($quotation, $request);

        // Detail 2 lembur was cleared → recomputed HPP lembur is 0.
        $hpp2 = DB::table('sl_quotation_detail_hpp')->where('quotation_detail_id', self::DETAIL_2)->first();
        $this->assertEqualsWithDelta(0.0, (float) $hpp2->lembur, 0.01);

        // Detail 1 provisi_peralatan user edit is honored (25000) and persisted.
        $hpp1 = DB::table('sl_quotation_detail_hpp')->where('quotation_detail_id', self::DETAIL_1)->first();
        $this->assertEqualsWithDelta(25000.0, (float) $hpp1->provisi_peralatan, 0.01);
        $this->assertEqualsWithDelta(5000000.0, (float) $hpp1->gaji_pokok, 0.01);
        $this->assertEqualsWithDelta(416666.67, (float) $hpp1->tunjangan_hari_raya, 0.01);
        $this->assertEqualsWithDelta(27000.0, (float) $hpp1->bpjs_jkk, 0.01);
        // Summary-level fields stamped onto every HPP row.
        $this->assertEqualsWithDelta(10.0, (float) $hpp1->persen_management_fee, 0.01);

        $coss1 = DB::table('sl_quotation_detail_coss')->where('quotation_detail_id', self::DETAIL_1)->first();
        $this->assertEqualsWithDelta(5150000.0, (float) $coss1->total_base_manpower, 0.01);
        // NOTE (characterization): coss_data.total_tunjangan is populated from the HPP
        // total_tunjangan (200000), NOT the per-row nominal_coss (150000). Locking as-is.
        $this->assertEqualsWithDelta(200000.0, (float) $coss1->total_tunjangan, 0.01);
    }

    public function test_sync_barang_data_creates_kaporlap_rows(): void
    {
        $user = $this->seedUser();
        $this->seedQuotation();
        $this->actingAs($user);

        // Master barang catalog (jenis_barang_id 2 is within the kaporlap range 1..5).
        DB::table('m_barang')->insert([
            'id' => 2, 'nama' => 'Sepatu Safety', 'jenis_barang_id' => 2, 'jenis_barang' => 'Kaporlap',
            'harga' => 150000, 'masa_pakai' => 12, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $quotation = Quotation::findOrFail(self::QUOTATION_ID);

        $result = app(QuotationBarangService::class)->syncBarangData(
            $quotation,
            'kaporlap',
            [
                ['barang_id' => 2, 'quotation_detail_id' => self::DETAIL_2, 'jumlah' => 3],
            ]
        );

        // Golden: one new kaporlap row created for detail 2.
        $this->assertTrue($result['success']);
        $this->assertSame('kaporlap', $result['jenis_barang']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['skipped']);

        $row = DB::table('sl_quotation_kaporlap')
            ->where('quotation_detail_id', self::DETAIL_2)
            ->where('barang_id', 2)
            ->whereNull('deleted_at')
            ->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(150000.0, (float) $row->harga, 0.01);
        $this->assertEqualsWithDelta(3.0, (float) $row->jumlah, 0.01);
        $this->assertSame('Sepatu Safety', $row->nama);

        // The pre-existing kaporlap on detail 1 (not in the incoming payload) is soft-deleted.
        $old = DB::table('sl_quotation_kaporlap')
            ->where('quotation_detail_id', self::DETAIL_1)
            ->where('barang_id', 1)
            ->first();
        $this->assertNotNull($old->deleted_at);
    }

    private function seedStep11MasterData(): void
    {
        DB::table('m_salary_rule')->insert([
            'id' => 1, 'nama_salary_rule' => 'SR 1', 'cutoff' => '26-25',
            'pengiriman_invoice' => 'H+1', 'rilis_payroll' => 'H+3',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'm_management_fee', 'm_barang',
            'sl_quotation', 'sl_quotation_site', 'sl_quotation_detail',
            'sl_quotation_detail_wages', 'sl_quotation_detail_hpp', 'sl_quotation_detail_coss',
            'sl_quotation_detail_tunjangan', 'sl_quotation_kaporlap', 'sl_quotation_devices',
            'sl_quotation_ohc', 'sl_quotation_chemical', 'sl_quotation_management_fee',
            'm_salary_rule', 'sl_quotation_aplikasi', 'sl_quotation_kerjasama',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('m_salary_rule', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_salary_rule')->nullable();
            $table->string('cutoff')->nullable();
            $table->string('pengiriman_invoice')->nullable();
            $table->string('rilis_payroll')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_aplikasi', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('aplikasi_pendukung')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_kerjasama', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->text('perjanjian')->nullable();
            $table->boolean('is_delete')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

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

        Schema::create('m_management_fee', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_umk', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->decimal('umk', 20, 2)->nullable();
            $table->date('tgl_berlaku')->nullable();
            $table->string('sumber')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_ump', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id')->nullable();
            $table->string('province_name')->nullable();
            $table->decimal('ump', 20, 2)->nullable();
            $table->date('tgl_berlaku')->nullable();
            $table->string('sumber')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_barang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->unsignedInteger('jenis_barang_id')->nullable();
            $table->string('jenis_barang')->nullable();
            $table->decimal('harga', 20, 2)->nullable();
            $table->string('satuan')->nullable();
            $table->integer('masa_pakai')->nullable();
            $table->string('merk')->nullable();
            $table->integer('jumlah_default')->nullable();
            $table->integer('urutan')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('kebutuhan')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('status_quotation_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('salary_rule_id')->nullable();
            $table->unsignedInteger('rule_thr_id')->nullable();
            $table->unsignedInteger('management_fee_id')->nullable();
            $table->string('management_fee')->nullable();
            $table->decimal('persentase', 15, 4)->nullable();
            $table->string('durasi_kerjasama')->nullable();
            $table->string('jenis_kontrak')->nullable();
            $table->string('hari_kerja')->nullable();
            $table->string('top')->nullable();
            $table->decimal('persen_bunga_bank', 15, 4)->nullable();
            $table->decimal('persen_insentif', 15, 4)->nullable();
            $table->string('is_ppn')->nullable();
            $table->string('ppn_pph_dipotong')->nullable();
            $table->string('resiko')->nullable();
            $table->string('program_bpjs')->nullable();
            $table->decimal('nominal_takaful', 20, 2)->nullable();
            $table->string('pengiriman_invoice')->nullable();
            $table->string('tipe_hari_invoice')->nullable();
            $table->string('jumlah_hari_invoice')->nullable();
            $table->string('kunjungan_operasional')->nullable();
            $table->string('tipe_quotation')->nullable();
            $table->string('penagihan')->nullable();
            $table->string('note_harga_jual')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->unsignedInteger('provinsi_id')->nullable();
            $table->string('provinsi')->nullable();
            $table->unsignedInteger('kota_id')->nullable();
            $table->string('kota')->nullable();
            $table->string('penempatan')->nullable();
            $table->decimal('ump', 20, 2)->nullable();
            $table->decimal('umk', 20, 2)->nullable();
            $table->decimal('umsk', 20, 2)->nullable();
            $table->decimal('nominal_upah', 20, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
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
            $table->integer('jumlah_hc')->nullable();
            $table->decimal('nominal_upah', 20, 2)->nullable();
            $table->string('penjamin_kesehatan')->nullable();
            $table->string('is_bpjs_jkk')->nullable();
            $table->string('is_bpjs_jkm')->nullable();
            $table->string('is_bpjs_jht')->nullable();
            $table->string('is_bpjs_jp')->nullable();
            $table->string('is_bpjs_kes')->nullable();
            $table->decimal('persen_bpjs_jkk', 15, 4)->nullable();
            $table->decimal('persen_bpjs_jkm', 15, 4)->nullable();
            $table->decimal('persen_bpjs_jht', 15, 4)->nullable();
            $table->decimal('persen_bpjs_jp', 15, 4)->nullable();
            $table->decimal('persen_bpjs_kes', 15, 4)->nullable();
            $table->decimal('nominal_takaful', 20, 2)->nullable();
            $table->boolean('is_custom_upah')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail_wages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('upah')->nullable();
            $table->string('hitungan_upah')->nullable();
            $table->string('lembur')->nullable();
            $table->decimal('nominal_lembur', 20, 2)->nullable();
            $table->string('jenis_bayar_lembur')->nullable();
            $table->decimal('jam_per_bulan_lembur', 20, 2)->nullable();
            $table->string('lembur_ditagihkan')->nullable();
            $table->string('kompensasi')->nullable();
            $table->string('thr')->nullable();
            $table->string('tunjangan_holiday')->nullable();
            $table->decimal('nominal_tunjangan_holiday', 20, 2)->nullable();
            $table->string('jenis_bayar_tunjangan_holiday')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['sl_quotation_detail_hpp', 'sl_quotation_detail_coss'] as $hppTable) {
            Schema::create($hppTable, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_id')->nullable();
                $table->unsignedInteger('quotation_detail_id')->nullable();
                $table->unsignedInteger('position_id')->nullable();
                $table->unsignedInteger('leads_id')->nullable();
                $table->integer('jumlah_hc')->nullable();
                $table->decimal('gaji_pokok', 20, 2)->nullable();
                $table->decimal('total_tunjangan', 20, 2)->nullable();
                $table->decimal('total_base_manpower', 20, 2)->nullable();
                $table->decimal('tunjangan_hari_raya', 20, 2)->nullable();
                $table->decimal('kompensasi', 20, 2)->nullable();
                $table->decimal('tunjangan_hari_libur_nasional', 20, 2)->nullable();
                $table->decimal('lembur', 20, 2)->nullable();
                $table->decimal('bpjs_jkk', 20, 2)->nullable();
                $table->decimal('bpjs_jkm', 20, 2)->nullable();
                $table->decimal('bpjs_jht', 20, 2)->nullable();
                $table->decimal('bpjs_jp', 20, 2)->nullable();
                $table->decimal('bpjs_ks', 20, 2)->nullable();
                $table->decimal('persen_bpjs_jkk', 15, 4)->nullable();
                $table->decimal('persen_bpjs_jkm', 15, 4)->nullable();
                $table->decimal('persen_bpjs_jht', 15, 4)->nullable();
                $table->decimal('persen_bpjs_jp', 15, 4)->nullable();
                $table->decimal('persen_bpjs_ks', 15, 4)->nullable();
                $table->decimal('provisi_seragam', 20, 2)->nullable();
                $table->decimal('provisi_peralatan', 20, 2)->nullable();
                $table->decimal('provisi_chemical', 20, 2)->nullable();
                $table->decimal('provisi_ohc', 20, 2)->nullable();
                $table->decimal('total_exclude_base_manpower', 20, 2)->nullable();
                $table->decimal('bunga_bank', 20, 2)->nullable();
                $table->decimal('insentif', 20, 2)->nullable();
                $table->decimal('total_biaya_per_personil', 20, 2)->nullable();
                $table->decimal('total_biaya_all_personil', 20, 2)->nullable();
                $table->decimal('management_fee', 20, 2)->nullable();
                $table->decimal('persen_management_fee', 15, 4)->nullable();
                $table->decimal('persen_bunga_bank', 15, 4)->nullable();
                $table->decimal('persen_insentif', 15, 4)->nullable();
                $table->decimal('grand_total', 20, 2)->nullable();
                $table->decimal('ppn', 20, 2)->nullable();
                $table->decimal('pph', 20, 2)->nullable();
                $table->decimal('total_invoice', 20, 2)->nullable();
                $table->decimal('pembulatan', 20, 2)->nullable();
                $table->string('is_pembulatan')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        Schema::create('sl_quotation_detail_tunjangan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->string('nama_tunjangan')->nullable();
            $table->decimal('nominal', 20, 2)->nullable();
            $table->decimal('nominal_coss', 20, 2)->nullable();
            $table->string('jenis')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['sl_quotation_kaporlap', 'sl_quotation_devices', 'sl_quotation_ohc'] as $barangTable) {
            Schema::create($barangTable, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_detail_id')->nullable();
                $table->unsignedInteger('quotation_site_id')->nullable();
                $table->unsignedInteger('quotation_id')->nullable();
                $table->unsignedInteger('barang_id')->nullable();
                $table->decimal('jumlah', 20, 2)->nullable();
                $table->decimal('harga', 20, 2)->nullable();
                $table->string('nama')->nullable();
                $table->unsignedInteger('jenis_barang_id')->nullable();
                $table->string('jenis_barang')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->string('deleted_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        Schema::create('sl_quotation_chemical', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('barang_id')->nullable();
            $table->decimal('jumlah', 20, 2)->nullable();
            $table->integer('masa_pakai')->nullable();
            $table->decimal('harga', 20, 2)->nullable();
            $table->string('nama')->nullable();
            $table->unsignedInteger('jenis_barang_id')->nullable();
            $table->string('jenis_barang')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_management_fee', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->boolean('is_thr')->nullable();
            $table->boolean('is_kompensasi')->nullable();
            $table->boolean('is_thl')->nullable();
            $table->boolean('is_lembur')->nullable();
            $table->boolean('is_bpjs_kes')->nullable();
            $table->boolean('is_bpjs_tk')->nullable();
            $table->boolean('is_chemical')->nullable();
            $table->boolean('is_kaporlap')->nullable();
            $table->boolean('is_device')->nullable();
            $table->boolean('is_ohc')->nullable();
            $table->boolean('is_tunjangan_lain')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
