<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\QuotationCalculationHarness;
use Tests\TestCase;

/**
 * Mengunci perlakuan nilai 0 pada baris sl_quotation_detail_hpp.
 *
 * Baris HPP dipakai sebagai override manual oleh calculateBpjs. Nilai 0 di sana
 * BUKAN override, melainkan sisa perhitungan lama — satu-satunya cara sah
 * menihilkan iuran adalah flag is_bpjs_*, dan itu diproses di cabang opt-out
 * yang mendahului cabang HPP. Reset ke NULL hanya terjadi di jalur simpan
 * (PricingService), jadi tanpa aturan ini jalur baca akan menempel di 0.
 *
 * Fixture: upah 200.000, kontrak Reguler (tanpa konversi harian), UMP 3.800.000,
 * UMK 4.000.000, resiko "Rendah". Basis TK = UMP, basis Kesehatan = UMK.
 */
class QuotationBpjsHppZeroTest extends TestCase
{
    use QuotationCalculationHarness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootQuotationHarness();
    }

    public function test_zero_in_hpp_is_recomputed_instead_of_sticking(): void
    {
        $this->seedQuotation('Reguler');
        $this->setHpp(['bpjs_jht' => 0]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // UMP 3.800.000 * 3,70% = 140.600 — bukan 0 yang tersimpan.
        $this->assertEqualsWithDelta(140600.0, (float) $hpp['bpjs_jht'], 0.01);
    }

    public function test_positive_value_in_hpp_is_still_honoured_as_override(): void
    {
        $this->seedQuotation('Reguler');
        $this->setHpp(['bpjs_jht' => 99999]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        $this->assertEqualsWithDelta(99999.0, (float) $hpp['bpjs_jht'], 0.01);
    }

    public function test_opt_out_still_wins_over_recomputation(): void
    {
        $this->seedQuotation('Reguler');
        DB::table('sl_quotation_detail')->update(['is_bpjs_jht' => 0]);
        $this->setHpp(['bpjs_jht' => 0]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // Flag mati -> tetap 0, tidak ikut dihitung ulang jadi 140.600.
        $this->assertEqualsWithDelta(0.0, (float) $hpp['bpjs_jht'], 0.01);
    }

    public function test_reguler_with_kes_opt_out_and_zero_hpp_is_still_charged(): void
    {
        // Di luar GC/PKHL, is_bpjs_kes = 0 sengaja diabaikan karena BPJS
        // Kesehatan wajib, jadi cabang opt-out TIDAK menyala dan alirannya
        // jatuh ke cabang HPP. Nilai 0 di sana dulu membuat iuran hilang.
        $this->seedQuotation('Reguler');
        DB::table('sl_quotation_detail')->update(['is_bpjs_kes' => 0, 'penjamin_kesehatan' => 'BPJS']);
        $this->setHpp(['bpjs_ks' => 0]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // UMK 4.000.000 * 4% = 160.000.
        $this->assertEqualsWithDelta(160000.0, (float) $hpp['bpjs_ks'], 0.01);
    }

    public function test_gc_with_kes_opt_out_and_zero_hpp_stays_zero(): void
    {
        // Pada GC opt-out dihormati, jadi cabang pertama menang dan nilai 0
        // tetap 0 — aturan baru ini tidak menyentuhnya.
        $this->seedQuotation('General Cleaning');
        DB::table('sl_quotation_detail')->update(['is_bpjs_kes' => 0, 'penjamin_kesehatan' => 'BPJS']);
        $this->setHpp(['bpjs_ks' => 0]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        $this->assertEqualsWithDelta(0.0, (float) $hpp['bpjs_ks'], 0.01);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function setHpp(array $values): void
    {
        DB::table('sl_quotation_detail_hpp')
            ->where('quotation_detail_id', self::DETAIL_1)
            ->update($values);
    }
}
