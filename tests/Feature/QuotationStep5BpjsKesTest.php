<?php

namespace Tests\Feature;

use App\Http\Requests\Quotation\QuotationStepRequest;
use App\Models\Quotation;
use App\Services\Quotation\Steps\Step5Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\QuotationCalculationHarness;
use Tests\TestCase;

/**
 * Mengunci default input BPJS Kesehatan pada step 5.
 *
 * General Cleaning dan PKHL adalah pekerjaan sekali jalan berdurasi pendek, jadi
 * BPJS Kesehatan default mati ketika frontend tidak mengirim field kes. Sales
 * tetap bisa menyalakannya, dan kontrak lain tetap default menyala.
 */
class QuotationStep5BpjsKesTest extends TestCase
{
    use QuotationCalculationHarness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootQuotationHarness();
        $this->extendSchemaForStep5();
    }

    // ============================ TESTS ============================

    public function test_gc_defaults_bpjs_kes_to_off_when_field_is_absent(): void
    {
        $this->seedQuotation('General Cleaning', ['is_aktif' => 1]);

        $this->runStep5();

        $this->assertSame(['0', '0'], $this->storedBpjsKes());
    }

    public function test_pkhl_defaults_bpjs_kes_to_off_when_field_is_absent(): void
    {
        $this->seedQuotation('PKHL', ['is_aktif' => 1]);

        $this->runStep5();

        $this->assertSame(['0', '0'], $this->storedBpjsKes());
    }

    public function test_reguler_still_defaults_bpjs_kes_to_on_when_field_is_absent(): void
    {
        $this->seedQuotation('Reguler', ['is_aktif' => 1]);

        $this->runStep5();

        $this->assertSame(['1', '1'], $this->storedBpjsKes());
    }

    public function test_gc_keeps_bpjs_kes_on_when_sales_switches_it_on(): void
    {
        $this->seedQuotation('General Cleaning', ['is_aktif' => 1]);

        $this->runStep5(['kes' => [self::DETAIL_1 => true, self::DETAIL_2 => true]]);

        $this->assertSame(['1', '1'], $this->storedBpjsKes());
    }

    public function test_gc_keeps_bpjs_kes_off_when_sales_switches_it_off(): void
    {
        $this->seedQuotation('General Cleaning', ['is_aktif' => 1]);

        $this->runStep5(['kes' => [self::DETAIL_1 => false, self::DETAIL_2 => false]]);

        $this->assertSame(['0', '0'], $this->storedBpjsKes());
    }

    public function test_step5_request_validates_kes_like_the_other_bpjs_flags(): void
    {
        // Endpoint updateStep memakai QuotationStepRequest, bukan Step5Request.
        $request = QuotationStepRequest::create('/api/quotations-step/500/step/5', 'POST');
        $route = new Route('POST', '/api/quotations-step/{id}/step/{step}', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        $rules = $request->rules();

        $this->assertArrayHasKey('kes', $rules);
        $this->assertSame(
            $rules['jkk']->toArray(),
            $rules['kes']->toArray(),
            'kes harus divalidasi sama seperti jkk/jkm/jht/jp'
        );
    }

    // ============================ HELPERS ============================

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function runStep5(array $overrides = []): void
    {
        $request = new Request(array_merge([
            'resiko' => 'Rendah',
            'program_bpjs' => 'BPJS',
            'penjamin' => [self::DETAIL_1 => 'BPJS Kesehatan', self::DETAIL_2 => 'BPJS Kesehatan'],
            'jkk' => [self::DETAIL_1 => true, self::DETAIL_2 => true],
            'jkm' => [self::DETAIL_1 => true, self::DETAIL_2 => true],
            'jht' => [self::DETAIL_1 => true, self::DETAIL_2 => true],
            'jp' => [self::DETAIL_1 => true, self::DETAIL_2 => true],
        ], $overrides));

        app(Step5Service::class)->execute(Quotation::findOrFail(self::QUOTATION_ID), $request);
    }

    /**
     * @return array<int, string>
     */
    private function storedBpjsKes(): array
    {
        return DB::table('sl_quotation_detail')
            ->orderBy('id')
            ->pluck('is_bpjs_kes')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    private function extendSchemaForStep5(): void
    {
        Schema::table('sl_quotation', function (Blueprint $table) {
            $table->unsignedInteger('jenis_perusahaan_id')->nullable();
            $table->unsignedInteger('bidang_perusahaan_id')->nullable();
            $table->string('jenis_perusahaan')->nullable();
            $table->string('bidang_perusahaan')->nullable();
            $table->unsignedInteger('is_aktif')->nullable();
        });

        Schema::dropIfExists('sl_leads');
        Schema::create('sl_leads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_perusahaan')->nullable();
            $table->unsignedInteger('jenis_perusahaan_id')->nullable();
            $table->unsignedInteger('bidang_perusahaan_id')->nullable();
            $table->string('jenis_perusahaan')->nullable();
            $table->string('bidang_perusahaan')->nullable();
            $table->string('resiko')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
