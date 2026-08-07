<?php

namespace Tests\Unit\Services\Numbering;

use App\Services\Pks\PksNumberingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi penomoran PKS — bug counter & penumpukan segmen yang sama seperti
 * Quotation, plus penanganan prefix `draft/` milik PKS wizard.
 *
 * @group numbering
 */
class PksNumberingServiceTest extends TestCase
{
    private PksNumberingService $service;
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
            'id' => $this->companyId, 'name' => 'PT Ion', 'code' => 'ION',
        ]);

        $this->leadsId = DB::table('sl_leads')->insertGetId([
            'nomor' => 'LS001',
            'nama_perusahaan' => 'PT Uji PKS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service = new PksNumberingService();
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
        foreach (['sl_pks', 'sl_leads', 'm_company'] as $table) {
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
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sl_pks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('leads_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('pks_induk_id')->nullable();
            $table->string('tipe_pks')->nullable();
            $table->string('nomor')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * @return int inserted id
     */
    private function simpan(string $nomor, string $tipe, ?int $indukId = null): int
    {
        return DB::table('sl_pks')->insertGetId([
            'leads_id' => $this->leadsId,
            'company_id' => $this->companyId,
            'pks_induk_id' => $indukId,
            'tipe_pks' => $tipe,
            'nomor' => $nomor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buat(string $tipe, ?int $indukId = null, bool $draft = false): int
    {
        $nomor = $this->service->generate($this->leadsId, $this->companyId, $tipe, $indukId);

        return $this->simpan($draft ? 'draft/' . $nomor : $nomor, $tipe, $indukId);
    }

    private function nomorDari(int $id): string
    {
        return DB::table('sl_pks')->where('id', $id)->value('nomor');
    }

    private function bulanIni(): string
    {
        return now()->format('mY');
    }

    // -------------------------------------------------------------------------

    public function test_rekontrak_berantai_menaikkan_counter_bukan_menumpuk(): void
    {
        $mY = $this->bulanIni();

        $org = $this->buat('baru');
        $this->assertSame("PKS/ORG/ION/LS001-{$mY}-00001", $this->nomorDari($org));

        $current = $org;
        foreach (['K01', 'K02', 'K03'] as $expected) {
            $current = $this->buat('rekontrak', $current);

            $this->assertSame(
                "PKS/RKT/ION/LS001-{$mY}-00001-{$expected}",
                $this->nomorDari($current)
            );
        }

        $this->assertStringNotContainsString('K02-K02', $this->nomorDari($current));
    }

    public function test_addendum_bersarang_di_bawah_rekontrak(): void
    {
        $mY = $this->bulanIni();

        $org = $this->buat('baru');
        $rekontrak = $this->buat('rekontrak', $org);

        $addendum1 = $this->buat('addendum', $rekontrak);
        $this->assertSame("PKS/ADD/ION/LS001-{$mY}-00001-K01-A01", $this->nomorDari($addendum1));

        $addendum2 = $this->buat('addendum', $addendum1);
        $this->assertSame("PKS/ADD/ION/LS001-{$mY}-00001-K01-A02", $this->nomorDari($addendum2));
    }

    // ------------------------------------------------------------- skenario 4

    public function test_turunan_dari_pks_draft_terparse_benar(): void
    {
        $mY = $this->bulanIni();

        $draftOrg = $this->buat('baru', null, draft: true);
        $this->assertSame("draft/PKS/ORG/ION/LS001-{$mY}-00001", $this->nomorDari($draftOrg));

        // Addendum dari PKS yang masih draft: prefix draft/ dibuang saat parsing,
        // dateSeq tetap terbaca, dan nomor turunan tidak ikut ber-prefix.
        $addendum = $this->buat('addendum', $draftOrg);

        $this->assertSame("PKS/ADD/ION/LS001-{$mY}-00001-A01", $this->nomorDari($addendum));
    }

    public function test_sequence_memperhitungkan_pks_draft(): void
    {
        $mY = $this->bulanIni();

        $this->buat('baru', null, draft: true);

        // Nomor draft sudah memakai slot 00001, jadi PKS berikutnya harus 00002.
        $berikutnya = $this->buat('baru');

        $this->assertSame("PKS/ORG/ION/LS001-{$mY}-00002", $this->nomorDari($berikutnya));
    }

    // -------------------------------------------------------------------------

    public function test_sequence_tidak_dipakai_ulang_setelah_soft_delete(): void
    {
        $mY = $this->bulanIni();

        $this->buat('baru');
        $kedua = $this->buat('baru');

        DB::table('sl_pks')->where('id', $kedua)->update(['deleted_at' => now()]);

        $ketiga = $this->buat('baru');

        $this->assertSame("PKS/ORG/ION/LS001-{$mY}-00003", $this->nomorDari($ketiga));
    }

    public function test_turunan_tanpa_induk_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PKS induk wajib');

        $this->service->generate($this->leadsId, $this->companyId, 'rekontrak');
    }
}
