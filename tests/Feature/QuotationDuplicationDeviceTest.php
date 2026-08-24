<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotation\QuotationBusinessService;
use App\Services\Quotation\QuotationDuplicationService;
use App\Services\Quotation\Steps\Step6Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi untuk duplikasi barang quotation, khususnya device "Aplikasi Pendukung"
 * (jenis_barang_id = 8) yang sebelumnya selalu bertambah tiap simpan step 6 dan
 * tidak terikat ke QuotationAplikasi milik quotation baru saat revisi.
 *
 * Tabel sl_quotation* tidak dibuat migrasi (prod impor dump MySQL), jadi skema
 * dibangun manual seperti tests/Feature/QuotationCalculationCharacterizationTest.php.
 */
class QuotationDuplicationDeviceTest extends TestCase
{
    private const REF_ID = 100;
    private const REF_SITE_A = 200;
    private const REF_SITE_B = 201;
    private const REF_DETAIL_A = 300;
    private const REF_DETAIL_B = 301;

    private const BARANG_APLIKASI = 900;
    private const BARANG_DEVICE = 901;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = storage_path('framework/testing-'.Str::random(8).'.sqlite');
        touch($databasePath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));
        Config::set('database.connections.mysqlhris', array_merge(Config::get('database.connections.sqlite'), ['database' => $databasePath]));

        foreach (['sqlite', 'mysql', 'mysqlhris'] as $connection) {
            DB::purge($connection);
            DB::reconnect($connection);
        }

        $this->rebuildSchema();
        $this->seedMasterData();
        $this->actingAs($this->seedUser());
    }

    /**
     * Bug: Step6Service menghapus jenis_barang_id 17 tapi meng-insert 8, jadi tiap
     * simpan step 6 menumpuk satu set device aplikasi pendukung baru.
     */
    public function test_simpan_step6_dua_kali_tidak_menggandakan_device_aplikasi(): void
    {
        $this->seedReferenceQuotation();
        $quotation = Quotation::findOrFail(self::REF_ID);
        $step6 = app(Step6Service::class);
        $request = Request::create('/', 'POST', ['aplikasi_pendukung' => [1]]);

        $step6->execute($quotation, $request);
        $step6->execute($quotation->fresh(), $request);

        $aktif = DB::table('sl_quotation_devices')
            ->where('quotation_id', self::REF_ID)
            ->where('jenis_barang_id', 8)
            ->whereNull('deleted_at')
            ->get();

        // 1 aplikasi × 2 site ber-HC, berapa kali pun step 6 disimpan.
        $this->assertCount(2, $aktif);
        $this->assertEqualsCanonicalizing(
            [self::REF_SITE_A, self::REF_SITE_B],
            $aktif->pluck('quotation_site_id')->all()
        );
    }

    /**
     * Device aplikasi warisan (quotation_aplikasi_id null, hasil duplikasi versi lama)
     * juga harus tersapu, bukan menumpuk dengan set baru.
     */
    public function test_step6_menyapu_device_aplikasi_warisan_tanpa_relasi(): void
    {
        $this->seedReferenceQuotation();

        DB::table('sl_quotation_devices')->insert([
            'quotation_id' => self::REF_ID,
            'quotation_site_id' => self::REF_SITE_A,
            'quotation_aplikasi_id' => null,
            'barang_id' => self::BARANG_APLIKASI,
            'nama' => 'Aplikasi Absensi',
            'jenis_barang_id' => 8,
            'jenis_barang' => 'Aplikasi Pendukung',
            'jumlah' => 2,
            'harga' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(Step6Service::class)->execute(
            Quotation::findOrFail(self::REF_ID),
            Request::create('/', 'POST', ['aplikasi_pendukung' => [1]])
        );

        $aktif = DB::table('sl_quotation_devices')
            ->where('quotation_id', self::REF_ID)
            ->where('jenis_barang_id', 8)
            ->whereNull('deleted_at')
            ->get();

        $this->assertCount(2, $aktif);
        $this->assertTrue($aktif->every(fn ($row) => $row->quotation_aplikasi_id !== null));
    }

    /**
     * Revisi: jumlah device harus sama persis dengan referensi, dan device aplikasi
     * harus menunjuk QuotationAplikasi milik quotation baru (bukan milik referensi).
     */
    public function test_duplikasi_revisi_menyalin_device_apa_adanya_dan_remap_aplikasi(): void
    {
        $this->seedReferenceQuotation();
        $this->seedReferenceBarang();

        $new = $this->makeTargetQuotation('revisi', [self::REF_SITE_A => 'Site A', self::REF_SITE_B => 'Site B']);

        app(QuotationDuplicationService::class)
            ->duplicateQuotationWithSiteMapping($new, Quotation::findOrFail(self::REF_ID));

        $refDevices = DB::table('sl_quotation_devices')->where('quotation_id', self::REF_ID)->whereNull('deleted_at')->count();
        $newDevices = DB::table('sl_quotation_devices')->where('quotation_id', $new->id)->whereNull('deleted_at')->get();

        $this->assertSame($refDevices, $newDevices->count(), 'Jumlah device hasil copy harus sama dengan referensi');

        $newAplikasiIds = DB::table('sl_quotation_aplikasi')
            ->where('quotation_id', $new->id)
            ->pluck('id')
            ->all();

        $aplikasiDevice = $newDevices->firstWhere('jenis_barang_id', 8);
        $this->assertNotNull($aplikasiDevice, 'Device aplikasi pendukung harus ikut tersalin');
        $this->assertContains(
            $aplikasiDevice->quotation_aplikasi_id,
            $newAplikasiIds,
            'quotation_aplikasi_id harus dipetakan ke QuotationAplikasi quotation baru'
        );

        // Chemical & OHC ikut, dan HPP/COSS detail tersalin.
        $this->assertSame(1, DB::table('sl_quotation_chemical')->where('quotation_id', $new->id)->count());
        $this->assertSame(1, DB::table('sl_quotation_ohc')->where('quotation_id', $new->id)->count());
        $this->assertSame(2, DB::table('sl_quotation_detail_hpp')->where('quotation_id', $new->id)->count());
        $this->assertSame(2, DB::table('sl_quotation_detail_coss')->where('quotation_id', $new->id)->count());
    }

    /**
     * Bug: duplicateQuotationWithoutSites tidak pernah mengisi siteIdMapping,
     * sehingga seluruh device/chemical/ohc di-skip.
     */
    public function test_duplikasi_tanpa_site_match_tetap_menyalin_barang(): void
    {
        $this->seedReferenceQuotation();
        $this->seedReferenceBarang();

        $new = $this->makeTargetQuotation('rekontrak', [self::REF_SITE_A => 'Site Baru 1', self::REF_SITE_B => 'Site Baru 2']);

        app(QuotationDuplicationService::class)
            ->duplicateQuotationWithoutSites($new, Quotation::findOrFail(self::REF_ID));

        $this->assertSame(
            DB::table('sl_quotation_devices')->where('quotation_id', self::REF_ID)->count(),
            DB::table('sl_quotation_devices')->where('quotation_id', $new->id)->count()
        );
        $this->assertSame(1, DB::table('sl_quotation_chemical')->where('quotation_id', $new->id)->count());
        $this->assertSame(1, DB::table('sl_quotation_ohc')->where('quotation_id', $new->id)->count());
    }

    /** Command cleanup menyisakan satu baris per kombinasi site + barang. */
    public function test_command_cleanup_menyisakan_satu_baris_per_kombinasi(): void
    {
        $this->seedReferenceQuotation();

        foreach (range(1, 3) as $i) {
            DB::table('sl_quotation_devices')->insert([
                'quotation_id' => self::REF_ID,
                'quotation_site_id' => self::REF_SITE_A,
                'barang_id' => self::BARANG_APLIKASI,
                'nama' => 'Aplikasi Absensi',
                'jenis_barang_id' => 8,
                'jenis_barang' => 'Aplikasi Pendukung',
                'jumlah' => 2,
                'harga' => 50000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('quotation:cleanup-duplicate-barang', ['--quotation' => self::REF_ID])
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('sl_quotation_devices')
            ->where('quotation_id', self::REF_ID)
            ->whereNull('deleted_at')
            ->count());
    }

    // =====================================================================
    // SEED HELPERS
    // =====================================================================

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

    private function seedMasterData(): void
    {
        DB::table('m_aplikasi_pendukung')->insert([
            'id' => 1,
            'nama' => 'Aplikasi Absensi',
            'barang_id' => self::BARANG_APLIKASI,
            'harga' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Quotation referensi: 2 site, masing-masing 1 detail ber-HC. */
    private function seedReferenceQuotation(): void
    {
        DB::table('sl_quotation')->insert([
            'id' => self::REF_ID,
            'leads_id' => 10,
            'nomor' => 'Q-REF-001',
            'kebutuhan_id' => 3,
            'kebutuhan' => 'Cleaning Service',
            'company_id' => 13,
            'status_quotation_id' => 3,
            'tipe_quotation' => 'baru',
            'jenis_kontrak' => 'TERPADU',
            'created_by' => 'Tester',
            'created_by_user_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sl_quotation_site')->insert([
            ['id' => self::REF_SITE_A, 'quotation_id' => self::REF_ID, 'leads_id' => 10, 'nama_site' => 'Site A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::REF_SITE_B, 'quotation_id' => self::REF_ID, 'leads_id' => 10, 'nama_site' => 'Site B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('sl_quotation_detail')->insert([
            ['id' => self::REF_DETAIL_A, 'quotation_id' => self::REF_ID, 'quotation_site_id' => self::REF_SITE_A, 'nama_site' => 'Site A', 'position_id' => 1, 'jabatan_kebutuhan' => 'Cleaner', 'jumlah_hc' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => self::REF_DETAIL_B, 'quotation_id' => self::REF_ID, 'quotation_site_id' => self::REF_SITE_B, 'nama_site' => 'Site B', 'position_id' => 1, 'jabatan_kebutuhan' => 'Cleaner', 'jumlah_hc' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach ([self::REF_DETAIL_A, self::REF_DETAIL_B] as $detailId) {
            DB::table('sl_quotation_detail_hpp')->insert([
                'quotation_id' => self::REF_ID, 'quotation_detail_id' => $detailId,
                'jumlah_hc' => 2, 'gaji_pokok' => 5000000, 'total_hpp' => 6000000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('sl_quotation_detail_coss')->insert([
                'quotation_id' => self::REF_ID, 'quotation_detail_id' => $detailId,
                'jumlah_hc' => 2, 'gaji_pokok' => 5000000, 'total_coss' => 7000000,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** Barang milik referensi: device aplikasi (via step 6), device biasa, chemical, ohc. */
    private function seedReferenceBarang(): void
    {
        app(Step6Service::class)->execute(
            Quotation::findOrFail(self::REF_ID),
            Request::create('/', 'POST', ['aplikasi_pendukung' => [1]])
        );

        DB::table('sl_quotation_devices')->insert([
            'quotation_id' => self::REF_ID,
            'quotation_site_id' => self::REF_SITE_A,
            'barang_id' => self::BARANG_DEVICE,
            'nama' => 'Vacuum Cleaner',
            'jenis_barang_id' => 9,
            'jenis_barang' => 'Alat Kerja',
            'jumlah' => 1,
            'harga' => 1500000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sl_quotation_chemical')->insert([
            'quotation_id' => self::REF_ID, 'quotation_site_id' => self::REF_SITE_A,
            'barang_id' => 910, 'nama' => 'Karbol', 'jenis_barang_id' => 13, 'jenis_barang' => 'Chemical',
            'jumlah' => 4, 'harga' => 25000, 'masa_pakai' => 12,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('sl_quotation_ohc')->insert([
            'quotation_id' => self::REF_ID, 'quotation_site_id' => self::REF_SITE_B,
            'barang_id' => 920, 'nama' => 'Helm', 'jenis_barang_id' => 6, 'jenis_barang' => 'OHC',
            'jumlah' => 2, 'harga' => 75000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Quotation tujuan beserta site-nya (site dibuat lebih dulu, seperti alur listener).
     *
     * @param  array<int, string>  $siteNames  refSiteId → nama site di quotation baru
     */
    private function makeTargetQuotation(string $tipe, array $siteNames): Quotation
    {
        $quotation = Quotation::create([
            'leads_id' => 10,
            'nomor' => 'Q-NEW-'.strtoupper($tipe),
            'kebutuhan_id' => 3,
            'kebutuhan' => 'Cleaning Service',
            'company_id' => 13,
            'status_quotation_id' => 1,
            'tipe_quotation' => $tipe,
            'quotation_referensi_id' => self::REF_ID,
            'created_by' => 'Tester',
            'created_by_user_id' => 1,
        ]);

        foreach ($siteNames as $namaSite) {
            $quotation->quotationSites()->create([
                'leads_id' => 10,
                'nama_site' => $namaSite,
                'created_by' => 'Tester',
                'created_by_user_id' => 1,
            ]);
        }

        return $quotation->fresh(['quotationSites']);
    }

    // =====================================================================
    // SCHEMA
    // =====================================================================

    private function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'm_aplikasi_pendukung', 'sl_quotation', 'sl_quotation_site',
            'sl_quotation_detail', 'sl_quotation_detail_wages', 'sl_quotation_detail_hpp',
            'sl_quotation_detail_coss', 'sl_quotation_detail_tunjangan', 'sl_quotation_detail_requirement',
            'sl_quotation_aplikasi', 'sl_quotation_devices', 'sl_quotation_chemical',
            'sl_quotation_ohc', 'sl_quotation_kaporlap', 'sl_quotation_training',
            'sl_quotation_kerjasama', 'sl_quotation_pic',
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
            $table->boolean('is_active')->nullable();
            $table->string('remember_token')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_aplikasi_pendukung', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('deskripsi')->nullable();
            $table->unsignedInteger('barang_id')->nullable();
            $table->decimal('harga', 20, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nomor')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->string('kebutuhan')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->string('company')->nullable();
            $table->unsignedInteger('status_quotation_id')->nullable();
            $table->unsignedInteger('quotation_referensi_id')->nullable();
            $table->string('tipe_quotation')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->string('jumlah_site')->nullable();
            $table->date('tgl_quotation')->nullable();

            // Kolom yang disalin duplicateBasicQuotationData()
            $table->string('jenis_kontrak')->nullable();
            $table->date('mulai_kontrak')->nullable();
            $table->date('kontrak_selesai')->nullable();
            $table->date('tgl_penempatan')->nullable();
            $table->unsignedInteger('salary_rule_id')->nullable();
            $table->string('top')->nullable();
            $table->string('jumlah_hari_invoice')->nullable();
            $table->string('tipe_hari_invoice')->nullable();
            $table->string('upah')->nullable();
            $table->decimal('nominal_upah', 20, 2)->nullable();
            $table->string('hitungan_upah')->nullable();
            $table->unsignedInteger('management_fee_id')->nullable();
            $table->decimal('persentase', 15, 4)->nullable();
            $table->string('thr')->nullable();
            $table->string('kompensasi')->nullable();
            $table->string('lembur')->nullable();
            $table->decimal('nominal_lembur', 20, 2)->nullable();
            $table->string('jenis_bayar_lembur')->nullable();
            $table->string('lembur_ditagihkan')->nullable();
            $table->decimal('jam_per_bulan_lembur', 20, 2)->nullable();
            $table->string('tunjangan_holiday')->nullable();
            $table->decimal('nominal_tunjangan_holiday', 20, 2)->nullable();
            $table->string('jenis_bayar_tunjangan_holiday')->nullable();
            $table->string('is_ppn')->nullable();
            $table->string('ppn_pph_dipotong')->nullable();
            $table->string('cuti')->nullable();
            $table->string('hari_cuti_kematian')->nullable();
            $table->string('hari_istri_melahirkan')->nullable();
            $table->string('hari_cuti_menikah')->nullable();
            $table->string('gaji_saat_cuti')->nullable();
            $table->string('prorate')->nullable();
            $table->string('shift_kerja')->nullable();
            $table->string('hari_kerja')->nullable();
            $table->string('jam_kerja')->nullable();
            $table->string('evaluasi_kontrak')->nullable();
            $table->string('durasi_kerjasama')->nullable();
            $table->string('durasi_karyawan')->nullable();
            $table->string('evaluasi_karyawan')->nullable();
            $table->unsignedInteger('jenis_perusahaan_id')->nullable();
            $table->string('jenis_perusahaan')->nullable();
            $table->unsignedInteger('bidang_perusahaan_id')->nullable();
            $table->string('bidang_perusahaan')->nullable();
            $table->string('resiko')->nullable();
            $table->string('kunjungan_operasional')->nullable();
            $table->string('kunjungan_tim_crm')->nullable();
            $table->text('keterangan_kunjungan_operasional')->nullable();
            $table->text('keterangan_kunjungan_tim_crm')->nullable();
            $table->string('training')->nullable();
            $table->decimal('persen_bunga_bank', 15, 4)->nullable();
            $table->decimal('persen_insentif', 15, 4)->nullable();
            $table->string('penagihan')->nullable();
            $table->text('note_harga_jual')->nullable();
            $table->boolean('is_aktif')->nullable();
            $table->integer('revisi')->nullable();
            $table->text('alasan_revisi')->nullable();
            $table->integer('step')->nullable();
            $table->boolean('materai')->nullable();

            $table->timestamp('calculated_at')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_error')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
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
            $table->unsignedInteger('created_by_user_id')->nullable();
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
            $table->decimal('nominal_takaful', 20, 2)->nullable();
            $table->decimal('biaya_monitoring_kontrol', 20, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail_wages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_detail_id')->nullable();
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
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['sl_quotation_detail_hpp', 'sl_quotation_detail_coss'] as $costTable) {
            Schema::create($costTable, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_id')->nullable();
                $table->unsignedInteger('quotation_detail_id')->nullable();
                $table->integer('jumlah_hc')->nullable();
                $table->decimal('gaji_pokok', 20, 2)->nullable();
                $table->decimal('tunjangan_hari_raya', 20, 2)->nullable();
                $table->decimal('kompensasi', 20, 2)->nullable();
                $table->decimal('tunjangan_hari_libur_nasional', 20, 2)->nullable();
                $table->decimal('lembur', 20, 2)->nullable();
                $table->decimal('bpjs_jkk', 20, 2)->nullable();
                $table->decimal('bpjs_jkm', 20, 2)->nullable();
                $table->decimal('bpjs_jht', 20, 2)->nullable();
                $table->decimal('bpjs_jp', 20, 2)->nullable();
                $table->decimal('bpjs_ks', 20, 2)->nullable();
                $table->decimal('takaful', 20, 2)->nullable();
                $table->decimal('provisi_seragam', 20, 2)->nullable();
                $table->decimal('provisi_peralatan', 20, 2)->nullable();
                $table->decimal('provisi_chemical', 20, 2)->nullable();
                $table->decimal('provisi_ohc', 20, 2)->nullable();
                $table->decimal('bunga_bank', 20, 2)->nullable();
                $table->decimal('insentif', 20, 2)->nullable();
                $table->decimal('management_fee', 20, 2)->nullable();
                $table->decimal('ppn', 20, 2)->nullable();
                $table->decimal('pph', 20, 2)->nullable();
                $table->decimal('total_hpp', 20, 2)->nullable();
                $table->decimal('total_coss', 20, 2)->nullable();
                $table->string('created_by')->nullable();
                $table->unsignedInteger('created_by_user_id')->nullable();
                $table->string('updated_by')->nullable();
                $table->string('deleted_by')->nullable();
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
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_detail_requirement', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->text('requirement')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_aplikasi', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('aplikasi_pendukung_id')->nullable();
            $table->string('aplikasi_pendukung')->nullable();
            $table->decimal('harga', 20, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['sl_quotation_devices', 'sl_quotation_ohc', 'sl_quotation_kaporlap'] as $barangTable) {
            Schema::create($barangTable, function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('quotation_id')->nullable();
                $table->unsignedInteger('quotation_site_id')->nullable();
                $table->unsignedInteger('quotation_detail_id')->nullable();
                $table->unsignedInteger('quotation_aplikasi_id')->nullable();
                $table->unsignedInteger('barang_id')->nullable();
                $table->string('nama')->nullable();
                $table->unsignedInteger('jenis_barang_id')->nullable();
                $table->string('jenis_barang')->nullable();
                $table->decimal('jumlah', 20, 2)->nullable();
                $table->decimal('harga', 20, 2)->nullable();
                $table->integer('masa_pakai')->nullable();
                $table->string('created_by')->nullable();
                $table->unsignedInteger('created_by_user_id')->nullable();
                $table->string('updated_by')->nullable();
                $table->string('deleted_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        Schema::create('sl_quotation_chemical', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('quotation_site_id')->nullable();
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->unsignedInteger('barang_id')->nullable();
            $table->string('nama')->nullable();
            $table->unsignedInteger('jenis_barang_id')->nullable();
            $table->string('jenis_barang')->nullable();
            $table->decimal('jumlah', 20, 2)->nullable();
            $table->decimal('harga', 20, 2)->nullable();
            $table->integer('masa_pakai')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_training', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('training_id')->nullable();
            $table->string('nama')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_kerjasama', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->text('perjanjian')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_pic', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama')->nullable();
            $table->unsignedInteger('jabatan_id')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_kuasa')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
