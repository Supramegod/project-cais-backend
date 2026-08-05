<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationLead8484Test extends TestCase
{
    private const QUOTATION_ID = 848400;
    private const LEADS_ID = 8484;
    private const KEBUTUHAN_ID = 3;

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
    }

    private function seedUser(): User
    {
        DB::table('m_user')->insert([
            'id' => 1,
            'username' => 'tester8484',
            'password' => bcrypt('secret'),
            'full_name' => 'Tester 8484',
            'email' => 'tester8484@example.com',
            'cais_role_id' => 2,
            'branch_id' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail(1);
    }

    private function seedBaseData(): void
    {
        DB::table('m_kebutuhan')->insert([
            'id' => self::KEBUTUHAN_ID, 'nama' => 'Cleaning Service', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('m_leads')->insert([
            'id' => self::LEADS_ID,
            'tipe' => 'Baru',
            'company_id' => 1,
            'branch_id' => 1,
            'pic_nama' => 'Budi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('m_leads_kebutuhan')->insert([
            'leads_id' => self::LEADS_ID,
            'kebutuhan_id' => self::KEBUTUHAN_ID,
        ]);
        
        // Minimal lookup tables
        DB::table('m_management_fee')->insert(['id' => 1, 'nama' => 'Dari Base Manpower', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('m_salary_rule')->insert(['id' => 1, 'nama_salary_rule' => 'Rule 1', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_quotation_lead_8484_costing_pricing_finalization_steps(): void
    {
        $this->seedBaseData();
        $user = $this->seedUser();
        $this->actingAs($user, 'web');

        // --- SETUP: Create a mock Quotation for Leads 8484 ---
        DB::table('sl_quotation')->insert([
            'id' => self::QUOTATION_ID,
            'leads_id' => self::LEADS_ID,
            'kebutuhan_id' => self::KEBUTUHAN_ID,
            'kebutuhan' => 'Cleaning Service',
            'nomor' => 'Q-LEAD-8484',
            'status_quotation_id' => 3, // Draft
            'step' => 3, // Simulate user is at step 3
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('sl_quotation_site')->insert([
            'id' => 1, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => self::LEADS_ID, 'nama_site' => 'Site Pusat', 'nominal_upah' => 5000000, 'umk' => 4500000, 'ump' => 4200000, 'created_at' => now(), 'updated_at' => now()
        ]);
        
        DB::table('sl_quotation_detail')->insert([
            'id' => 1, 'quotation_id' => self::QUOTATION_ID, 'quotation_site_id' => 1, 'nama_site' => 'Site Pusat', 'position_id' => 10, 'jabatan_kebutuhan' => 'Cleaner', 'jumlah_hc' => 5, 'nominal_upah' => 5000000, 'penjamin_kesehatan' => 'BPJS', 'created_at' => now(), 'updated_at' => now()
        ]);
        
        DB::table('sl_quotation_detail_wages')->insert([
            'quotation_detail_id' => 1, 'quotation_id' => self::QUOTATION_ID, 'lembur' => 'Tidak Ada', 'nominal_lembur' => 0, 'lembur_ditagihkan' => 'Tidak Ditagihkan', 'kompensasi' => 'Diprovisikan', 'thr' => 'Diprovisikan', 'tunjangan_holiday' => 'Tidak Ada', 'created_at' => now(), 'updated_at' => now()
        ]);
        
        DB::table('sl_quotation_detail_hpp')->insert(['quotation_detail_id' => 1, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => self::LEADS_ID]);
        DB::table('sl_quotation_detail_coss')->insert(['quotation_detail_id' => 1, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => self::LEADS_ID]);

        // 1. Test Step 4 (Costing)
        // Even though current step is 3, Step 4 should succeed due to independence
        $responseStep4 = $this->postJson('/api/quotations-step/' . self::QUOTATION_ID . '/step/4', [
            'is_ppn' => 1,
            'ppn_pph_dipotong' => 'Management Fee',
            'management_fee_id' => 1,
            'persentase' => 10,
            'position_data' => [
                [
                    'quotation_detail_id' => 1,
                    'upah' => 'Custom',
                    'hitungan_upah' => 'Per Bulan',
                    'nominal_upah' => 5000000,
                    'lembur' => 'Tidak Ada',
                    'kompensasi' => 'Diprovisikan',
                    'thr' => 'Diprovisikan',
                    'tunjangan_holiday' => 'Tidak Ada',
                ]
            ],
            'komponen_management_fee' => [
                'is_thr' => true,
                'is_kompensasi' => true,
                'is_bpjs_kes' => true,
            ]
        ]);
        $responseStep4->assertStatus(200);

        // 2. Test Step 11 (Pricing)
        $responseStep11 = $this->postJson('/api/quotations-step/' . self::QUOTATION_ID . '/step/11', [
            'penagihan' => 'Transfer',
            'persentase' => 10,
            'wage_data' => [
                1 => ['lembur' => 'Tidak Ada', 'nominal_lembur' => 0, 'lembur_ditagihkan' => 'Tidak Ditagihkan']
            ],
            'hpp_editable_data' => [
                1 => ['provisi_peralatan' => 0]
            ]
        ]);
        $responseStep11->assertStatus(200);

        // Verify calculation persisted
        $this->assertNotNull(DB::table('sl_quotation')->find(self::QUOTATION_ID)->calculated_at);

        // 3. Test Step 12 (Finalization)
        $responseStep12 = $this->postJson('/api/quotations-step/' . self::QUOTATION_ID . '/step/12', [
            'waktu_kerja' => 'Senin - Jumat',
            'waktu_istirahat' => '1 Jam',
            'hari_libur' => 'Sabtu & Minggu',
            'pekerjaan_lain' => 'Sesuai SOP',
            'biaya_lain' => 'Tidak Ada',
            'tagihan' => 'Sesuai BAST',
            'tempo_pembayaran' => '14 Hari Kerja',
            'is_draft' => true // Don't trigger finalization jobs that need queues/mails
        ]);
        $responseStep12->assertStatus(200);
    }

    private function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'm_management_fee', 'm_barang', 'm_kebutuhan', 'm_leads', 'm_leads_kebutuhan',
            'sl_quotation', 'sl_quotation_site', 'sl_quotation_detail', 'sl_quotation_detail_requirement',
            'sl_quotation_detail_wages', 'sl_quotation_detail_hpp', 'sl_quotation_detail_coss',
            'sl_quotation_detail_tunjangan', 'sl_quotation_kaporlap', 'sl_quotation_devices',
            'sl_quotation_ohc', 'sl_quotation_chemical', 'sl_quotation_management_fee',
            'm_salary_rule', 'sl_quotation_aplikasi', 'sl_quotation_kerjasama', 'm_umk', 'm_ump', 'm_umsk', 'm_umsp'
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('m_kebutuhan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->timestamps();
        });

        Schema::create('m_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipe')->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('branch_id')->nullable();
            $table->string('pic_nama')->nullable();
            $table->timestamps();
        });

        Schema::create('m_leads_kebutuhan', function (Blueprint $table) {
            $table->integer('leads_id')->nullable();
            $table->integer('kebutuhan_id')->nullable();
        });

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

        Schema::create('m_umsk', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('city_id')->nullable();
            $table->string('city_name')->nullable();
            $table->decimal('umsk', 20, 2)->nullable();
            $table->date('tgl_berlaku')->nullable();
            $table->string('sumber')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('m_umsp', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id')->nullable();
            $table->string('province_name')->nullable();
            $table->decimal('umsp', 20, 2)->nullable();
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
            $table->integer('step')->nullable();
            $table->integer('version')->default(1);
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
            $table->boolean('is_aktif')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation_site', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_id')->nullable();
            $table->unsignedInteger('leads_id')->nullable();
            $table->string('nama_site')->nullable();
            $table->string('jenis_perusahaan')->nullable();
            $table->integer('jenis_perusahaan_id')->nullable();
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

        Schema::create('sl_quotation_detail_requirement', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('quotation_detail_id')->nullable();
            $table->unsignedInteger('quotation_id')->nullable();
            $table->string('nama')->nullable();
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
