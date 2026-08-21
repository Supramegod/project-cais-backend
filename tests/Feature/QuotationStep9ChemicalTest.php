<?php

namespace Tests\Feature;

use App\Http\Requests\Quotation\QuotationStepRequest;
use App\Models\Quotation;
use App\Services\Quotation\Calculation\QuotationItemCalculationService;
use App\Services\Quotation\QuotationBarangService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Route;
use Tests\Feature\Concerns\QuotationCalculationHarness;
use Tests\TestCase;

/**
 * Mengunci perlakuan masa_pakai chemical pada step 9.
 *
 * Pada General Cleaning kolom masa_pakai tidak dipakai costing sama sekali —
 * divisornya ditetapkan per jenis barang (mesin 36, selainnya 1). Karena itu
 * field tersebut boleh dikosongkan saat input dan tidak ikut dikirim di
 * response GET. Kontrak lain tetap memakainya seperti biasa.
 */
class QuotationStep9ChemicalTest extends TestCase
{
    use QuotationCalculationHarness;

    private const JENIS_BARANG_MESIN = 13;

    private const JENIS_BARANG_HABIS_PAKAI = 14;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootQuotationHarness();
    }

    // ==================== VALIDASI ====================

    public function test_masa_pakai_boleh_dikosongkan(): void
    {
        $this->seedBarang();

        // Lewat pipeline FormRequest sungguhan: aturan each() baru dirakit
        // saat validator dibuat, jadi memeriksa rules() saja tidak membuktikan
        // apa-apa untuk field nested.
        $this->assertValidationPasses([
            'chemicals' => [['barang_id' => 1, 'jumlah' => 2, 'masa_pakai' => null, 'harga' => 1000]],
        ]);
    }

    public function test_masa_pakai_nol_tetap_ditolak(): void
    {
        $this->seedBarang();

        // Yang dilonggarkan hanya "boleh kosong"; 0 bulan tetap tidak bermakna.
        $this->assertValidationFails([
            'chemicals' => [['barang_id' => 1, 'jumlah' => 2, 'masa_pakai' => 0, 'harga' => 1000]],
        ], 'chemicals.0.masa_pakai');
    }

    public function test_masa_pakai_terisi_tetap_diterima(): void
    {
        $this->seedBarang();

        $this->assertValidationPasses([
            'chemicals' => [['barang_id' => 1, 'jumlah' => 2, 'masa_pakai' => 12, 'harga' => 1000]],
        ]);
    }

    // ==================== RESPONSE GET ====================

    public function test_gc_response_tidak_memuat_masa_pakai(): void
    {
        $item = $this->buildChemicalResponse('General Cleaning', self::JENIS_BARANG_HABIS_PAKAI);

        $this->assertArrayNotHasKey('masa_pakai', $item);
        $this->assertArrayNotHasKey('masa_pakai_formatted', $item);
        $this->assertArrayNotHasKey('jumlah_pertahun', $item);
    }

    public function test_non_gc_response_masih_memuat_masa_pakai(): void
    {
        $item = $this->buildChemicalResponse('Reguler', self::JENIS_BARANG_HABIS_PAKAI);

        $this->assertArrayHasKey('masa_pakai', $item);
        $this->assertArrayHasKey('masa_pakai_formatted', $item);
        $this->assertArrayHasKey('jumlah_pertahun', $item);
    }

    // ==================== KONSISTENSI DENGAN COSTING ====================

    public function test_gc_total_per_item_habis_pakai_dibebankan_penuh(): void
    {
        $item = $this->buildChemicalResponse('General Cleaning', self::JENIS_BARANG_HABIS_PAKAI);

        // 2 x 300.000 / 1 — kolom masa_pakai (12) diabaikan.
        $this->assertEqualsWithDelta(600000.0, (float) $item['total_per_item'], 0.01);
    }

    public function test_gc_total_per_item_mesin_diamortisasi_36_bulan(): void
    {
        $item = $this->buildChemicalResponse('General Cleaning', self::JENIS_BARANG_MESIN);

        // 2 x 300.000 / 36
        $this->assertEqualsWithDelta(16666.6667, (float) $item['total_per_item'], 0.01);
    }

    public function test_non_gc_total_per_item_tetap_pakai_kolom_masa_pakai(): void
    {
        $item = $this->buildChemicalResponse('Reguler', self::JENIS_BARANG_MESIN);

        // 2 x 300.000 / 12 (kolom masa_pakai)
        $this->assertEqualsWithDelta(50000.0, (float) $item['total_per_item'], 0.01);
    }

    public function test_divisor_response_sama_dengan_divisor_costing(): void
    {
        $mesin = (object) ['jenis_barang_id' => self::JENIS_BARANG_MESIN];
        $habisPakai = (object) ['jenis_barang_id' => self::JENIS_BARANG_HABIS_PAKAI];

        $this->assertSame(36, QuotationItemCalculationService::resolveGcChemicalMasaPakai($mesin));
        $this->assertSame(1, QuotationItemCalculationService::resolveGcChemicalMasaPakai($habisPakai));
    }

    // ==================== HELPERS ====================

    /**
     * @return array<string, mixed>
     */
    private function buildChemicalResponse(string $jenisKontrak, int $jenisBarangId): array
    {
        $this->seedQuotation($jenisKontrak);
        $this->seedChemical($jenisBarangId, 2, 300_000, 12);

        $quotation = Quotation::with('quotationChemicals', 'quotationSites')
            ->findOrFail(self::QUOTATION_ID);

        $result = app(QuotationBarangService::class)->prepareBarangData($quotation, 'chemicals');

        return $result['data'][0];
    }

    private function seedBarang(): void
    {
        \Illuminate\Support\Facades\DB::table('m_barang')->insert([
            'id' => 1, 'nama' => 'Chemical Uji', 'jenis_barang_id' => 14,
            'jenis_barang' => 'Chemical', 'harga' => 1000, 'masa_pakai' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertValidationPasses(array $payload): void
    {
        $this->assertNull($this->runStep9Validation($payload), 'validasi seharusnya lolos');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertValidationFails(array $payload, string $field): void
    {
        $errors = $this->runStep9Validation($payload);

        $this->assertNotNull($errors, 'validasi seharusnya gagal');
        $this->assertArrayHasKey($field, $errors);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null null bila lolos, daftar error bila gagal
     */
    private function runStep9Validation(array $payload): ?array
    {
        $request = QuotationStepRequest::create('/api/quotations-step/500/step/9', 'POST', $payload);
        $route = new Route('POST', '/api/quotations-step/{id}/step/{step}', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $request->setContainer($this->app);

        try {
            $request->validateResolved();

            return null;
        } catch (HttpResponseException $e) {
            return json_decode($e->getResponse()->getContent(), true)['message'] ?? [];
        }
    }
}
