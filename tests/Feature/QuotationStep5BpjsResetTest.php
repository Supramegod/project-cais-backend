<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Services\Quotation\Steps\Step5Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\QuotationCalculationHarness;
use Tests\TestCase;

/**
 * Mengunci pengosongan nominal BPJS tersimpan saat step 5 mengubah faktor
 * penentunya.
 *
 * Nominal di sl_quotation_detail_hpp dipakai calculateBpjs sebagai override
 * manual — sales bisa mengetiknya lewat hpp_editable_data di step 10/11. Karena
 * itu nominal lama TIDAK boleh dibuang sembarangan; hanya detail yang faktor
 * penentunya berubah di step 5 yang dikosongkan.
 */
class QuotationStep5BpjsResetTest extends TestCase
{
    use QuotationCalculationHarness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootQuotationHarness();
        $this->extendSchemaForStep5();
    }

    public function test_manual_override_survives_when_no_bpjs_factor_changes(): void
    {
        $this->seedQuotation('Reguler', ['is_aktif' => 1]);
        // Samakan kondisi tersimpan dengan apa yang akan ditulis step 5:
        // keempat flag TK sudah 1 dari harness, kes disetel 1 karena Reguler
        // memang selalu aktif, penjamin dan takaful tidak berubah.
        DB::table('sl_quotation_detail')->update(['is_bpjs_kes' => 1]);
        $this->setStoredBpjs(['bpjs_jht' => 12345, 'bpjs_jkk' => 0]);

        $this->runStep5(['kes' => [self::DETAIL_1 => true, self::DETAIL_2 => true]]);

        // Inilah jaminannya: nominal manual (termasuk 0 yang disengaja) tetap utuh.
        $this->assertNotNull($this->storedBpjs('bpjs_jht'));
        $this->assertEqualsWithDelta(12345.0, (float) $this->storedBpjs('bpjs_jht'), 0.01);

        // Nol yang disengaja juga harus selamat, bukan cuma nilai non-nol.
        $this->assertNotNull($this->storedBpjs('bpjs_jkk'));
        $this->assertEqualsWithDelta(0.0, (float) $this->storedBpjs('bpjs_jkk'), 0.01);
    }

    public function test_stored_bpjs_is_cleared_when_a_flag_changes(): void
    {
        $this->seedQuotation('Reguler', ['is_aktif' => 1]);
        $this->setStoredBpjs(['bpjs_jht' => 12345]);

        // jht dimatikan: 1 -> 0.
        $this->runStep5(['jht' => [self::DETAIL_1 => false, self::DETAIL_2 => false]]);

        $this->assertNull($this->storedBpjs('bpjs_jht'));
    }

    public function test_stored_bpjs_is_cleared_when_penjamin_changes(): void
    {
        $this->seedQuotation('Reguler', ['is_aktif' => 1]);
        $this->setStoredBpjs(['bpjs_ks' => 999]);

        $this->runStep5(['penjamin' => [self::DETAIL_1 => 'BPU', self::DETAIL_2 => 'BPU']]);

        $this->assertNull($this->storedBpjs('bpjs_ks'));
    }

    public function test_percentage_override_is_never_touched(): void
    {
        $this->seedQuotation('Reguler', ['is_aktif' => 1]);
        $this->setStoredBpjs(['bpjs_jht' => 12345, 'persen_bpjs_jht' => 9.99]);

        $this->runStep5(['jht' => [self::DETAIL_1 => false, self::DETAIL_2 => false]]);

        $this->assertNull($this->storedBpjs('bpjs_jht'));
        $this->assertEqualsWithDelta(9.99, (float) $this->storedBpjs('persen_bpjs_jht'), 0.001);
    }

    public function test_cleared_value_is_recomputed_on_next_calculation(): void
    {
        // Skenario nyata: kalkulasi tersimpan 0 karena flag masih mati, lalu
        // step 5 menyalakannya. Tanpa pengosongan, 0 itu menempel di jalur baca.
        $this->seedQuotation('Reguler', ['is_aktif' => 1]);
        DB::table('sl_quotation_detail')->update(['is_bpjs_jht' => 0]);
        $this->setStoredBpjs(['bpjs_jht' => 0]);

        $this->runStep5();

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // UMP 3.800.000 * 3,70% = 140.600, bukan 0 yang tersimpan.
        $this->assertEqualsWithDelta(140600.0, (float) $hpp['bpjs_jht'], 0.01);
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
     * @param  array<string, mixed>  $values
     */
    private function setStoredBpjs(array $values): void
    {
        DB::table('sl_quotation_detail_hpp')
            ->where('quotation_detail_id', self::DETAIL_1)->update($values);
    }

    private function storedBpjs(string $column): ?string
    {
        $value = DB::table('sl_quotation_detail_hpp')
            ->where('quotation_detail_id', self::DETAIL_1)->value($column);

        return $value === null ? null : (string) $value;
    }
}
