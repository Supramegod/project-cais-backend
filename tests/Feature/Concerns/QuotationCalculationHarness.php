<?php

namespace Tests\Feature\Concerns;

use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotation\QuotationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Harness bersama untuk test kalkulasi quotation.
 *
 * Tabel sl_quotation* TIDAK dibuat oleh migrasi (produksi mengimpor dump MySQL,
 * migrasi hanya MENAMBAH kolom), sehingga skemanya dibangun manual di sini —
 * mengikuti tests/Feature/QuotationCalculationCharacterizationTest.php.
 */
trait QuotationCalculationHarness
{
    protected const QUOTATION_ID = 500;

    protected const SITE_A = 600;

    protected const DETAIL_1 = 700; // HC 2, pemilik item kaporlap

    protected const DETAIL_2 = 701; // HC 1, polos

    protected ?string $tempDbPath = null;

    protected function bootQuotationHarness(): void
    {
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
        $this->actingAs($this->seedUser());

        $this->beforeApplicationDestroyed(function (): void {
            if ($this->tempDbPath === null) {
                return;
            }

            DB::purge('sqlite');
            DB::purge('mysqlhris');
            DB::purge('mysql');
            @unlink($this->tempDbPath);
            $this->tempDbPath = null;
        });
    }

    protected function calculate(): \App\DTO\QuotationCalculationResult
    {
        return app(QuotationService::class)->calculateQuotation(Quotation::findOrFail(self::QUOTATION_ID));
    }

    protected function seedUser(): User
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
     * Satu site, dua detail (HC 2 + 1 = 3), belum ada item provisi.
     *
     * @param  array<string, mixed>  $quotationOverrides
     * @param  array<string, mixed>  $siteOverrides
     * @param  array<string, mixed>  $detailOverrides
     */
    protected function seedQuotation(string $jenisKontrak, array $quotationOverrides = [], array $siteOverrides = [], array $detailOverrides = []): void
    {
        DB::table('m_management_fee')->insert([
            'id' => 1, 'nama' => 'Dari Base Manpower', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('sl_quotation')->insert(array_merge([
            'id' => self::QUOTATION_ID,
            'leads_id' => 10,
            'kebutuhan_id' => 3,
            'kebutuhan' => 'Cleaning Service',
            'nomor' => 'Q-GC-001',
            'status_quotation_id' => 3,
            'company_id' => 13,
            'salary_rule_id' => 1,
            'rule_thr_id' => 2,
            'management_fee_id' => 1,
            'persentase' => 10,
            'durasi_kerjasama' => '12 bulan',
            'jenis_kontrak' => $jenisKontrak,
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
        ], $quotationOverrides));

        DB::table('sl_quotation_site')->insert(array_merge([
            'id' => self::SITE_A, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => 10,
            'nama_site' => 'Site A', 'nominal_upah' => 200000, 'umk' => 4000000, 'ump' => 3800000,
            'created_at' => now(), 'updated_at' => now(),
        ], $siteOverrides));

        /*
         * Flag BPJS Ketenagakerjaan sengaja diisi 1 karena kalkulasi selalu
         * berjalan SETELAH step 5 menetapkannya. is_bpjs_kes sengaja TIDAK
         * diisi supaya jatuh ke default kolom 0 — itulah kondisi nyata sebuah
         * detail yang baru dibuat dan belum disentuh sales.
         */
        $bpjsTkAktif = ['is_bpjs_jkk' => 1, 'is_bpjs_jkm' => 1, 'is_bpjs_jht' => 1, 'is_bpjs_jp' => 1];

        DB::table('sl_quotation_detail')->insert([
            array_merge(['id' => self::DETAIL_1, 'quotation_id' => self::QUOTATION_ID, 'quotation_site_id' => self::SITE_A, 'nama_site' => 'Site A', 'position_id' => 10, 'jabatan_kebutuhan' => 'Cleaner', 'jumlah_hc' => 2, 'nominal_upah' => 200000, 'penjamin_kesehatan' => 'BPJS', 'created_at' => now(), 'updated_at' => now()], $bpjsTkAktif, $detailOverrides),
            array_merge(['id' => self::DETAIL_2, 'quotation_id' => self::QUOTATION_ID, 'quotation_site_id' => self::SITE_A, 'nama_site' => 'Site A', 'position_id' => 11, 'jabatan_kebutuhan' => 'Supervisor', 'jumlah_hc' => 1, 'nominal_upah' => 220000, 'penjamin_kesehatan' => 'BPJS', 'created_at' => now(), 'updated_at' => now()], $bpjsTkAktif, $detailOverrides),
        ]);

        foreach ([self::DETAIL_1, self::DETAIL_2] as $detailId) {
            DB::table('sl_quotation_detail_wages')->insert([
                'quotation_detail_id' => $detailId, 'quotation_id' => self::QUOTATION_ID,
                'lembur' => 'Tidak Ada', 'nominal_lembur' => 0, 'jenis_bayar_lembur' => null,
                'jam_per_bulan_lembur' => 0, 'lembur_ditagihkan' => 'Tidak Ditagihkan',
                'kompensasi' => 'Tidak Ada', 'thr' => 'Tidak Ada', 'tunjangan_holiday' => 'Tidak Ada',
                'nominal_tunjangan_holiday' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            // Mostly-null so the engine computes provisi values from the item rows.
            DB::table('sl_quotation_detail_hpp')->insert([
                'quotation_detail_id' => $detailId, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => 10, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('sl_quotation_detail_coss')->insert([
                'quotation_detail_id' => $detailId, 'quotation_id' => self::QUOTATION_ID, 'leads_id' => 10, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('sl_quotation_management_fee')->insert([
            'quotation_id' => self::QUOTATION_ID,
            'is_thr' => 1, 'is_kompensasi' => 1, 'is_thl' => 1, 'is_lembur' => 1,
            'is_bpjs_kes' => 1, 'is_bpjs_tk' => 1, 'is_chemical' => 1, 'is_kaporlap' => 1,
            'is_device' => 1, 'is_ohc' => 1, 'is_tunjangan_lain' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function seedChemical(?int $jenisBarangId, float $jumlah, float $harga, ?int $masaPakai): void
    {
        DB::table('sl_quotation_chemical')->insert([
            'quotation_site_id' => self::SITE_A,
            'quotation_id' => self::QUOTATION_ID,
            'barang_id' => 1,
            'jumlah' => $jumlah,
            'harga' => $harga,
            'masa_pakai' => $masaPakai,
            'nama' => 'Chemical Test',
            'jenis_barang_id' => $jenisBarangId,
            'jenis_barang' => 'Chemical',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function seedKaporlapDevicesAndOhc(): void
    {
        DB::table('sl_quotation_kaporlap')->insert([
            'quotation_detail_id' => self::DETAIL_1, 'quotation_id' => self::QUOTATION_ID,
            'barang_id' => 1, 'jumlah' => 2, 'harga' => 60000, 'nama' => 'Seragam',
            'jenis_barang_id' => 1, 'jenis_barang' => 'Kaporlap', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('sl_quotation_devices')->insert([
            'quotation_site_id' => self::SITE_A, 'quotation_id' => self::QUOTATION_ID,
            'barang_id' => 2, 'jumlah' => 2, 'harga' => 600000, 'nama' => 'Vacuum',
            'jenis_barang_id' => 8, 'jenis_barang' => 'Devices', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('sl_quotation_ohc')->insert([
            'quotation_site_id' => self::SITE_A, 'quotation_id' => self::QUOTATION_ID,
            'barang_id' => 3, 'jumlah' => 1, 'harga' => 360000, 'nama' => 'Helm',
            'jenis_barang_id' => 6, 'jenis_barang' => 'OHC', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function rebuildSchema(): void
    {
        foreach ([
            'm_user', 'm_management_fee', 'm_barang',
            'sl_quotation', 'sl_quotation_site', 'sl_quotation_detail',
            'sl_quotation_detail_wages', 'sl_quotation_detail_hpp', 'sl_quotation_detail_coss',
            'sl_quotation_detail_tunjangan', 'sl_quotation_kaporlap', 'sl_quotation_devices',
            'sl_quotation_ohc', 'sl_quotation_chemical', 'sl_quotation_management_fee',
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

        Schema::create('m_management_fee', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
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
            /*
             * Produksi: tinyint(1) NULL DEFAULT '0'. Default 0 ini penting —
             * detail yang baru dibuat sudah bernilai 0, bukan NULL, sehingga
             * jalur "pertahankan nilai tersimpan" di Step5Service yang aktif,
             * bukan jalur fallback NULL.
             */
            $table->tinyInteger('is_bpjs_jkk')->nullable()->default(0);
            $table->tinyInteger('is_bpjs_jkm')->nullable()->default(0);
            $table->tinyInteger('is_bpjs_jht')->nullable()->default(0);
            $table->tinyInteger('is_bpjs_jp')->nullable()->default(0);
            $table->tinyInteger('is_bpjs_kes')->nullable()->default(0);
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
