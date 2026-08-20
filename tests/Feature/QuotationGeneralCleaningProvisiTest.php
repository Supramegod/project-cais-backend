<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\QuotationCalculationHarness;
use Tests\TestCase;

/**
 * Mengunci aturan item provisi untuk jenis_kontrak = GENERAL CLEANING.
 *
 * Pada GC keempat item provisi tidak lagi dibagi hari_kerja. Selain itu provisi
 * tidak mengikuti durasi kerjasama: kaporlap selalu dibagi 12, devices dan OHC
 * dibebankan penuh (provisi 1), sedangkan chemical mengabaikan kolom masa_pakai
 * dan memakai masa pakai tetap per jenis barang — 36 bulan untuk mesin
 * (jenis_barang_id 13) dan 1 untuk selainnya.
 */
class QuotationGeneralCleaningProvisiTest extends TestCase
{
    use QuotationCalculationHarness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootQuotationHarness();
    }

    // ============================ TESTS ============================

    public function test_gc_chemical_mesin_amortized_over_36_months_ignoring_masa_pakai_column(): void
    {
        $this->seedQuotation('GENERAL CLEANING');
        $this->seedChemical(13, 2, 3_600_000, 12);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // (2 * 3.600.000) / 36 / 3 HC = 66.666,67 — kolom masa_pakai (12) dan
        // hari_kerja (25) sama-sama diabaikan.
        $this->assertEqualsWithDelta(66666.6667, (float) $hpp['provisi_chemical'], 0.01);
    }

    public function test_gc_chemical_non_mesin_charged_in_full_ignoring_masa_pakai_column(): void
    {
        $this->seedQuotation('GENERAL CLEANING');
        $this->seedChemical(14, 1, 300_000, 12);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // (1 * 300.000) / 1 / 3 HC = 100.000
        $this->assertEqualsWithDelta(100000.0, (float) $hpp['provisi_chemical'], 0.01);
    }

    public function test_gc_chemical_without_jenis_barang_id_falls_back_to_masa_pakai_one(): void
    {
        $this->seedQuotation('GENERAL CLEANING');
        $this->seedChemical(null, 1, 300_000, 12);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        $this->assertEqualsWithDelta(100000.0, (float) $hpp['provisi_chemical'], 0.01);
    }

    public function test_gc_chemical_mixes_mesin_and_consumable_rules_on_hpp_and_coss(): void
    {
        $this->seedQuotation('GENERAL CLEANING');
        $this->seedChemical(13, 2, 3_600_000, 12);
        $this->seedChemical(14, 1, 300_000, 12);

        $calculation = $this->calculate()->detail_calculations[self::DETAIL_1];

        // 66.666,67 (mesin) + 100.000 (habis pakai)
        $this->assertEqualsWithDelta(166666.6667, (float) $calculation->hpp_data['provisi_chemical'], 0.01);
        $this->assertEqualsWithDelta(166666.6667, (float) $calculation->coss_data['provisi_chemical'], 0.01);
    }

    public function test_non_gc_chemical_still_uses_masa_pakai_column(): void
    {
        $this->seedQuotation('TERPADU');
        $this->seedChemical(13, 2, 3_600_000, 12);
        $this->seedChemical(14, 1, 300_000, 12);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // (7.200.000 / 12 / 3) + (300.000 / 12 / 3) = 200.000 + 8.333,33
        $this->assertEqualsWithDelta(208333.3333, (float) $hpp['provisi_chemical'], 0.01);
    }

    public function test_non_gc_chemical_with_null_masa_pakai_does_not_blow_up(): void
    {
        $this->seedQuotation('TERPADU');
        $this->seedChemical(14, 1, 300_000, null);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // masa_pakai null dulu memicu DivisionByZeroError (Error, jadi lolos dari
        // catch (\Exception) di sekitar pemrosesan detail). Sekarang turun ke 1:
        // 300.000 / 1 / 3 HC.
        $this->assertEqualsWithDelta(100000.0, (float) $hpp['provisi_chemical'], 0.01);
    }

    public function test_gc_kaporlap_always_divided_by_twelve_regardless_of_durasi(): void
    {
        // Durasi 1 bulan -> provisi = 1, sehingga kaporlap GC (selalu /12) dan
        // non-GC (/provisi) tidak mungkin kebetulan sama.
        $this->seedQuotation('GENERAL CLEANING', ['durasi_kerjasama' => '1 bulan']);
        $this->seedKaporlapDevicesAndOhc();

        $gc = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        DB::table('sl_quotation')->where('id', self::QUOTATION_ID)->update(['jenis_kontrak' => 'TERPADU']);
        $nonGc = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // GC: 2 * 60.000 / 12 = 10.000
        $this->assertEqualsWithDelta(10000.0, (float) $gc['provisi_seragam'], 0.01);
        // Non-GC: 2 * 60.000 / 1 provisi = 120.000
        $this->assertEqualsWithDelta(120000.0, (float) $nonGc['provisi_seragam'], 0.01);
    }

    public function test_gc_devices_and_ohc_charged_in_full_regardless_of_durasi(): void
    {
        // Durasi bawaan 12 bulan -> provisi = 12, sehingga selisih GC (provisi 1)
        // dan non-GC (provisi 12) terbukti.
        $this->seedQuotation('GENERAL CLEANING');
        $this->seedKaporlapDevicesAndOhc();

        $gc = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        DB::table('sl_quotation')->where('id', self::QUOTATION_ID)->update(['jenis_kontrak' => 'TERPADU']);
        $nonGc = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // GC: 2 * 600.000 / 1 / 3 HC = 400.000 dan 1 * 360.000 / 1 / 3 HC = 120.000
        $this->assertEqualsWithDelta(400000.0, (float) $gc['provisi_peralatan'], 0.01);
        $this->assertEqualsWithDelta(120000.0, (float) $gc['provisi_ohc'], 0.01);

        // Non-GC: dibagi 12 provisi -> 33.333,33 dan 10.000
        $this->assertEqualsWithDelta(33333.3333, (float) $nonGc['provisi_peralatan'], 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $nonGc['provisi_ohc'], 0.01);
    }

    public function test_gc_devices_and_ohc_apply_on_coss_too(): void
    {
        $this->seedQuotation('GENERAL CLEANING');
        $this->seedKaporlapDevicesAndOhc();

        $coss = $this->calculate()->detail_calculations[self::DETAIL_1]->coss_data;

        $this->assertEqualsWithDelta(10000.0, (float) $coss['provisi_seragam'], 0.01);
        $this->assertEqualsWithDelta(400000.0, (float) $coss['provisi_peralatan'], 0.01);
        $this->assertEqualsWithDelta(120000.0, (float) $coss['provisi_ohc'], 0.01);
    }

    public function test_gc_manual_provisi_chemical_override_is_used_verbatim(): void
    {
        $this->seedQuotation('GENERAL CLEANING');
        $this->seedChemical(13, 2, 3_600_000, 12);

        DB::table('sl_quotation_detail_hpp')
            ->where('quotation_detail_id', self::DETAIL_1)
            ->update(['provisi_chemical' => 50000]);

        $hpp = $this->calculate()->detail_calculations[self::DETAIL_1]->hpp_data;

        // Dulu nilai manual masih ikut dibagi hari_kerja (50.000 / 25).
        $this->assertEqualsWithDelta(50000.0, (float) $hpp['provisi_chemical'], 0.01);
    }
}
