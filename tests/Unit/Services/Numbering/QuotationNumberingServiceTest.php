<?php

namespace Tests\Unit\Services\Numbering;

use App\Models\Quotation;
use App\Services\Quotation\QuotationNumberingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi untuk bug penomoran quotation.
 *
 * Bug yang dilaporkan dari produksi:
 *   QUOT/RVS/GSU/AABKJ-072025-00001-V02-V02-V02-V02
 *
 * @group numbering
 */
class QuotationNumberingServiceTest extends TestCase
{
    private QuotationNumberingService $service;
    private string $tempDbPath;
    private int $leadsId;
    private int $companyId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDbPath = storage_path('framework/testing-' . Str::random(8) . '.sqlite');
        touch($this->tempDbPath);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->tempDbPath);
        Config::set('database.connections.mysqlhris', array_merge(Config::get('database.connections.sqlite'), ['database' => $this->tempDbPath]));
        Config::set('database.connections.mysql', array_merge(Config::get('database.connections.sqlite'), ['database' => $this->tempDbPath]));

        foreach (['sqlite', 'mysqlhris', 'mysql'] as $conn) {
            DB::purge($conn);
            DB::reconnect($conn);
        }

        $this->rebuildSchema();

        DB::connection('mysqlhris')->table('m_company')->insert([
            'id' => $this->companyId, 'name' => 'PT Garuda Sentra Utama', 'code' => 'GSU',
        ]);

        $this->leadsId = DB::table('sl_leads')->insertGetId([
            'nomor' => 'AABKJ',
            'nama_perusahaan' => 'PT Uji Penomoran',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service = new QuotationNumberingService();
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDbPath) && file_exists($this->tempDbPath)) {
            @unlink($this->tempDbPath);
        }

        parent::tearDown();
    }

    private function rebuildSchema(): void
    {
        foreach (['sl_quotation', 'sl_leads', 'm_company'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('m_company', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->nullable();
        });

        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nomor')->nullable();
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('kebutuhan_id')->nullable();
            $table->unsignedInteger('status_leads_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_quotation', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('quotation_referensi_id')->nullable();
            $table->unsignedInteger('quotation_asal_id')->nullable();
            $table->string('tipe_quotation')->nullable();
            $table->string('nomor')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Simpan quotation persis seperti alur produksi: generate nomor lalu insert.
     */
    private function buat(string $tipe, ?int $referensiId = null): Quotation
    {
        $nomor = $this->service->generate($this->leadsId, $this->companyId, $tipe, $referensiId);

        return Quotation::create([
            'leads_id' => $this->leadsId,
            'company_id' => $this->companyId,
            'quotation_referensi_id' => $referensiId,
            'tipe_quotation' => $tipe,
            'nomor' => $nomor,
        ]);
    }

    private function bulanIni(): string
    {
        return now()->format('mY');
    }

    // ------------------------------------------------------------- skenario 1

    public function test_revisi_berantai_menaikkan_counter_bukan_menumpuk_segmen(): void
    {
        $mY = $this->bulanIni();

        $org = $this->buat('baru');
        $this->assertSame("QUOT/ORG/GSU/AABKJ-{$mY}-00001", $org->nomor);

        $current = $org;
        foreach (['V01', 'V02', 'V03', 'V04'] as $expected) {
            $current = $this->buat('revisi', $current->id);

            $this->assertSame(
                "QUOT/RVS/GSU/AABKJ-{$mY}-00001-{$expected}",
                $current->nomor,
                'Revisi berantai tidak boleh menumpuk segmen V'
            );
        }

        // Penjaga eksplisit terhadap bentuk nomor rusak yang dilaporkan.
        $this->assertStringNotContainsString('V02-V02', $current->nomor);
    }

    // ------------------------------------------------------------- skenario 2

    public function test_nesting_hanya_terjadi_antar_huruf_berbeda(): void
    {
        $mY = $this->bulanIni();

        $org = $this->buat('baru');

        $rekontrak = $this->buat('rekontrak', $org->id);
        $this->assertSame("QUOT/RKT/GSU/AABKJ-{$mY}-00001-K01", $rekontrak->nomor);

        // Huruf berbeda → segmen baru ditambahkan di bawah K01.
        $revisi1 = $this->buat('revisi', $rekontrak->id);
        $this->assertSame("QUOT/RVS/GSU/AABKJ-{$mY}-00001-K01-V01", $revisi1->nomor);

        // Huruf sama → segmen terakhir diganti, K01 tetap dipertahankan.
        $revisi2 = $this->buat('revisi', $revisi1->id);
        $this->assertSame("QUOT/RVS/GSU/AABKJ-{$mY}-00001-K01-V02", $revisi2->nomor);
    }

    public function test_revisi_di_cabang_berbeda_punya_counter_sendiri(): void
    {
        $mY = $this->bulanIni();

        $org = $this->buat('baru');
        $revisiAkar = $this->buat('revisi', $org->id);
        $this->assertSame("QUOT/RVS/GSU/AABKJ-{$mY}-00001-V01", $revisiAkar->nomor);

        $rekontrak = $this->buat('rekontrak', $org->id);
        $revisiCabang = $this->buat('revisi', $rekontrak->id);

        // Counter V di bawah K01 mulai dari 01 lagi, tidak terpengaruh V01 di akar.
        $this->assertSame("QUOT/RVS/GSU/AABKJ-{$mY}-00001-K01-V01", $revisiCabang->nomor);
    }

    // ------------------------------------------------------------- skenario 3

    public function test_sequence_tidak_dipakai_ulang_setelah_soft_delete(): void
    {
        $mY = $this->bulanIni();

        $this->buat('baru');
        $kedua = $this->buat('baru');
        $this->assertSame("QUOT/ORG/GSU/AABKJ-{$mY}-00002", $kedua->nomor);

        $kedua->delete();
        $this->assertSoftDeleted('sl_quotation', ['id' => $kedua->id]);

        // Dengan count()+1 yang lama, ini akan menghasilkan 00002 lagi.
        $ketiga = $this->buat('baru');
        $this->assertSame("QUOT/ORG/GSU/AABKJ-{$mY}-00003", $ketiga->nomor);
    }

    public function test_counter_versi_tidak_dipakai_ulang_setelah_soft_delete(): void
    {
        $mY = $this->bulanIni();

        $org = $this->buat('baru');
        $revisi1 = $this->buat('revisi', $org->id);
        $revisi1->delete();

        $revisi2 = $this->buat('revisi', $org->id);

        $this->assertSame("QUOT/RVS/GSU/AABKJ-{$mY}-00001-V02", $revisi2->nomor);
    }

    // ------------------------------------------------------------- skenario 5

    public function test_relasi_melingkar_tidak_menggantung(): void
    {
        $mY = $this->bulanIni();

        $a = $this->buat('baru');
        $b = $this->buat('revisi', $a->id);

        // Data rusak: A menunjuk balik ke B.
        $a->forceFill(['quotation_referensi_id' => $b->id])->save();

        $hasil = $this->service->generate($this->leadsId, $this->companyId, 'revisi', $b->id);

        $this->assertStringStartsWith("QUOT/RVS/GSU/AABKJ-{$mY}-00001-V", $hasil);
    }

    // ------------------------------------------------------------- skenario 6

    public function test_nomor_kepanjangan_melempar_exception(): void
    {
        DB::table('sl_leads')->where('id', $this->leadsId)->update([
            'nomor' => str_repeat('PANJANG', 20),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('melebihi batas');

        $this->service->generate($this->leadsId, $this->companyId, 'baru');
    }

    // ------------------------------------------------------------ validasi in

    public function test_tipe_tidak_dikenal_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->generate($this->leadsId, $this->companyId, 'entah');
    }

    public function test_turunan_tanpa_referensi_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Referensi ID wajib');

        $this->service->generate($this->leadsId, $this->companyId, 'revisi');
    }
}
