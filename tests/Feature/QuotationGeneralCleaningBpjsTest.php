<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\QuotationCalculationHarness;
use Tests\TestCase;

/**
 * Mengunci aturan BPJS untuk jenis_kontrak = GENERAL CLEANING.
 *
 * Dua aturan yang diuji di sini:
 * 1. Basis iuran BPJS Ketenagakerjaan memakai batas bawah UMK pada GC, sedangkan
 *    kontrak lain tetap memakai UMP.
 * 2. BPJS Kesehatan tidak pernah dipaksa nol. Bedanya, pada GC dan PKHL opt-out
 *    is_bpjs_kes dihormati walau penjamin BPJS — pada kontrak reguler tidak.
 *
 * Fixture memakai upah harian 150.000 x 25 hari kerja = 3.750.000, yang jatuh di
 * bawah UMK (4.000.000) maupun UMP (3.800.000) sehingga kedua batas bawah aktif
 * dan perbedaannya terlihat.
 */
class QuotationGeneralCleaningBpjsTest extends TestCase
{
    use QuotationCalculationHarness;

    private const UPAH_DI_BAWAH_UMK = ['nominal_upah' => 150000];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootQuotationHarness();
    }

    // ============================ TESTS ============================

    public function test_gc_bpjs_tk_uses_umk_as_floor(): void
    {
        $this->seedQuotation('GENERAL CLEANING', [], [], self::UPAH_DI_BAWAH_UMK);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // Upah bulanan 3.750.000 < UMK 4.000.000, jadi basis naik ke UMK.
        // Resiko "Rendah" -> JKK 0,54%; JKM 0,30%; JHT 3,70%; JP 2,00%.
        $this->assertEqualsWithDelta(21600.0, (float) $hpp['bpjs_jkk'], 0.01);
        $this->assertEqualsWithDelta(12000.0, (float) $hpp['bpjs_jkm'], 0.01);
        $this->assertEqualsWithDelta(148000.0, (float) $hpp['bpjs_jht'], 0.01);
        $this->assertEqualsWithDelta(80000.0, (float) $hpp['bpjs_jp'], 0.01);
    }

    public function test_non_gc_bpjs_tk_still_uses_ump_as_floor(): void
    {
        $this->seedQuotation('Borongan', [], [], self::UPAH_DI_BAWAH_UMK);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // Non-GC: upah 150.000 jauh di bawah UMP, jadi basis = UMP 3.800.000.
        $this->assertEqualsWithDelta(20520.0, (float) $hpp['bpjs_jkk'], 0.01);
        $this->assertEqualsWithDelta(11400.0, (float) $hpp['bpjs_jkm'], 0.01);
        $this->assertEqualsWithDelta(140600.0, (float) $hpp['bpjs_jht'], 0.01);
        $this->assertEqualsWithDelta(76000.0, (float) $hpp['bpjs_jp'], 0.01);
    }

    public function test_gc_bpjs_tk_uses_actual_wage_when_above_umk(): void
    {
        // Upah bawaan 200.000 x 25 = 5.000.000, di atas UMK maupun UMP.
        $this->seedQuotation('GENERAL CLEANING');

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // 5.000.000 * 3,70% = 185.000 — batas bawah tidak aktif.
        $this->assertEqualsWithDelta(185000.0, (float) $hpp['bpjs_jht'], 0.01);
    }

    public function test_gc_honours_bpjs_kes_opt_out_even_when_penjamin_is_bpjs(): void
    {
        $this->seedQuotation('GENERAL CLEANING', [], [], self::UPAH_DI_BAWAH_UMK);
        DB::table('sl_quotation_detail')->update(['is_bpjs_kes' => 0]);

        $calculation = $this->calculate()->detail_calculations[self::DETAIL_1];

        $this->assertEqualsWithDelta(0.0, (float) $calculation->hpp_data['bpjs_ks'], 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $calculation->coss_data['bpjs_ks'], 0.01);
    }

    public function test_pkhl_honours_bpjs_kes_opt_out_even_when_penjamin_is_bpjs(): void
    {
        $this->seedQuotation('PKHL', [], [], self::UPAH_DI_BAWAH_UMK);
        DB::table('sl_quotation_detail')->update(['is_bpjs_kes' => 0]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        $this->assertEqualsWithDelta(0.0, (float) $hpp['bpjs_ks'], 0.01);
    }

    public function test_reguler_still_ignores_bpjs_kes_opt_out_when_penjamin_is_bpjs(): void
    {
        $this->seedQuotation('Reguler', [], [], self::UPAH_DI_BAWAH_UMK);
        DB::table('sl_quotation_detail')->update(['is_bpjs_kes' => 0]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // Reguler: penjamin BPJS mengunci iuran kesehatan, 4% * UMK 4.000.000.
        $this->assertEqualsWithDelta(160000.0, (float) $hpp['bpjs_ks'], 0.01);
    }

    public function test_gc_bpjs_kes_is_not_forced_to_zero_when_left_active(): void
    {
        $this->seedQuotation('GENERAL CLEANING', [], [], self::UPAH_DI_BAWAH_UMK);
        DB::table('sl_quotation_detail')->update(['is_bpjs_kes' => 1]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // Sales tetap bisa menyalakannya: 4% * UMK 4.000.000.
        $this->assertEqualsWithDelta(160000.0, (float) $hpp['bpjs_ks'], 0.01);
    }
}
