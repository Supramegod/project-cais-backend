<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\Pks;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Services\PksTemplate\PksGsuTemplateService;
use App\Services\PksTemplate\PksIonTemplateService;
use App\Services\PksTemplate\PksRciTemplateService;
use App\Services\PksTemplate\PksSigTemplateService;
use App\Services\PksTemplate\PksTemplateFactory;
use Tests\TestCase;

/**
 * Verifies the PKS agreement template fills its dynamic placeholders from the
 * available data (leads address, service type, salary-rule schedule, contract
 * dates, management fee) instead of leaving literal "titik-titik" (………) blanks.
 *
 * Pure in-memory: the template only reads model attributes, so no DB is needed.
 */
class PksTemplateFillTest extends TestCase
{
    private function makeModels(): array
    {
        $leads = new Leads();
        $leads->nama_perusahaan = 'PT ABC Sejahtera';
        $leads->alamat = 'Jl. Melati No. 1';
        $leads->kota = 'Bandung';
        $leads->provinsi = 'Jawa Barat';
        $leads->pic = 'budi santoso';

        $company = new Company();
        $company->id = 13; // SIG
        $company->name = 'PT SHELTER INDONESIA GEMILANG';
        $company->nama_direktur = 'Direktur Utama';

        $kebutuhan = new Kebutuhan();
        $kebutuhan->nama = 'Cleaning Service';

        $ruleThr = new RuleThr();
        $ruleThr->hari_penagihan_invoice = 'H-30';
        $ruleThr->hari_pembayaran_invoice = 'H-14';
        $ruleThr->hari_rilis_thr = 'H-7';

        $salaryRule = new SalaryRule();
        $salaryRule->cutoff = '26-25';
        $salaryRule->crosscheck_absen = 'Tanggal 26';
        $salaryRule->pengiriman_invoice = 'Tanggal 1';
        $salaryRule->pembayaran_invoice = '14 Hari';
        $salaryRule->rilis_payroll = 'Tanggal 25';

        $pks = new Pks();
        $pks->kontrak_awal = '2026-07-01';
        $pks->kontrak_akhir = '2027-06-30';

        return compact('leads', 'company', 'kebutuhan', 'ruleThr', 'salaryRule', 'pks');
    }

    /** Same models but with a specific company id (to exercise factory routing). */
    private function makeModelsForCompany(int $companyId): array
    {
        $m = $this->makeModels();
        $m['company']->id = $companyId;

        return $m;
    }

    // ── Company 14 → GSU ──────────────────────────────────────────────────
    public function test_factory_routes_company_14_to_gsu_template(): void
    {
        $m = $this->makeModelsForCompany(14);
        $service = (new PksTemplateFactory)->make(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/GSU/ABC-072026-00001', $m['pks'], 12.5
        );

        $this->assertInstanceOf(PksGsuTemplateService::class, $service);
    }

    public function test_gsu_template_fills_dynamic_placeholders(): void
    {
        $m = $this->makeModelsForCompany(14);
        $service = new PksGsuTemplateService(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/GSU/ABC-072026-00001', $m['pks'], 12.5
        );

        $html = collect($service->generateAllSections())->pluck('raw_text')->implode("\n");

        $this->assertStringContainsString('Kota Bandung', $html);
        $this->assertStringContainsString('Provinsi Jawa Barat', $html);
        $this->assertStringContainsString('BUDI SANTOSO', $html);
        $this->assertStringContainsString('12,5% dari nilai invoice', $html);
        $this->assertStringContainsString('26-25', $html);            // cutoff (Pasal 4 tabel)
        $this->assertStringContainsString('14 Hari', $html);          // release pembayaran
        $this->assertStringContainsString('Tanggal 25', $html);       // release gaji
        $this->assertStringContainsString('1 Juli 2026', $html);      // kontrak awal
        $this->assertStringContainsString('30 Juni 2027', $html);     // kontrak akhir

        $this->assertStringNotContainsString('berkedudukan di ……………', $html);
        $this->assertStringNotContainsString('Tanggal….Bulan', $html);
        $this->assertStringNotContainsString('ditetapkan sebesar ….%', $html);
    }

    // ── Company 16 → RCI ──────────────────────────────────────────────────
    public function test_factory_routes_company_16_to_rci_template(): void
    {
        $m = $this->makeModelsForCompany(16);
        $service = (new PksTemplateFactory)->make(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/RCI/ABC-072026-00001', $m['pks'], 12.5
        );

        $this->assertInstanceOf(PksRciTemplateService::class, $service);
    }

    public function test_rci_template_fills_dynamic_placeholders(): void
    {
        $m = $this->makeModelsForCompany(16);
        $service = new PksRciTemplateService(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/RCI/ABC-072026-00001', $m['pks'], 12.5
        );

        $html = collect($service->generateAllSections())->pluck('raw_text')->implode("\n");

        $this->assertStringContainsString('Kota Bandung', $html);
        $this->assertStringContainsString('BUDI SANTOSO', $html);
        $this->assertStringContainsString('Alih Daya sebagai <strong>Cleaning Service</strong>', $html);
        $this->assertStringContainsString('12,5% dari nilai invoice', $html);
        $this->assertStringContainsString('26-25', $html);            // cutoff (Pasal 4 tabel)
        $this->assertStringContainsString('14 Hari', $html);          // Rrelease Invoice
        $this->assertStringContainsString('Tanggal 25', $html);       // release gaji
        $this->assertStringContainsString('1 Juli 2026', $html);      // kontrak awal
        $this->assertStringContainsString('30 Juni 2027', $html);     // kontrak akhir

        $this->assertStringNotContainsString('berkedudukan di ……………', $html);
        $this->assertStringNotContainsString('sebagai<strong>……..', $html);
        $this->assertStringNotContainsString('03 Januari 2026', $html);
    }

    // ── Company 17 → ION ──────────────────────────────────────────────────
    public function test_factory_routes_company_17_to_ion_template(): void
    {
        $m = $this->makeModelsForCompany(17);
        $service = (new PksTemplateFactory)->make(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/ION/ABC-072026-00001', $m['pks'], 12.5
        );

        $this->assertInstanceOf(PksIonTemplateService::class, $service);
    }

    public function test_ion_template_fills_dynamic_placeholders(): void
    {
        $m = $this->makeModelsForCompany(17);
        $service = new PksIonTemplateService(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/ION/ABC-072026-00001', $m['pks'], 12.5
        );

        $html = collect($service->generateAllSections())->pluck('raw_text')->implode("\n");

        $this->assertStringContainsString('Kota Bandung', $html);
        $this->assertStringContainsString('Provinsi Jawa Barat', $html);
        $this->assertStringContainsString('BUDI SANTOSO', $html);
        $this->assertStringContainsString('12,5% dari nilai invoice', $html);
        $this->assertStringContainsString('1 Juli 2026', $html);      // kontrak awal (Pasal 7)
        $this->assertStringContainsString('30 Juni 2027', $html);     // kontrak akhir

        $this->assertStringNotContainsString('berkedudukan di ……………', $html);
        $this->assertStringNotContainsString('10% (Sepuluh  Persen)', $html);
        $this->assertStringNotContainsString('….(bulan)', $html);
    }

    public function test_factory_routes_company_13_to_sig_template(): void
    {
        $m = $this->makeModels();
        $service = (new PksTemplateFactory)->make(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/SIG/ABC-072026-00001', $m['pks'], 10.0
        );

        $this->assertInstanceOf(PksSigTemplateService::class, $service);
    }

    public function test_sig_template_fills_dynamic_placeholders(): void
    {
        $m = $this->makeModels();
        $service = new PksSigTemplateService(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/SIG/ABC-072026-00001', $m['pks'], 12.5
        );

        $html = collect($service->generateAllSections())->pluck('raw_text')->implode("\n");

        // Data terisi:
        $this->assertStringContainsString('Jl. Melati No. 1', $html);           // alamat pihak pertama
        $this->assertStringContainsString('Kota Bandung', $html);
        $this->assertStringContainsString('Provinsi Jawa Barat', $html);
        $this->assertStringContainsString('BUDI SANTOSO', $html);               // pic (uppercased, null-safe)
        $this->assertStringContainsString('Alih Daya sebagai <strong>Cleaning Service</strong>', $html);
        $this->assertStringContainsString('26-25', $html);                      // cutoff (Pasal 4 tabel)
        $this->assertStringContainsString('14 Hari', $html);                    // pembayaran invoice
        $this->assertStringContainsString('Tanggal 25', $html);                 // rilis payroll
        $this->assertStringContainsString('1 Juli 2026', $html);                // kontrak awal (Pasal 7)
        $this->assertStringContainsString('30 Juni 2027', $html);               // kontrak akhir
        $this->assertStringContainsString('12,5% dari nilai invoice', $html);   // management fee dinamis

        // Placeholder "titik-titik" yang punya sumber data sudah TIDAK ada lagi:
        $this->assertStringNotContainsString('berkedudukan di ……………', $html);
        $this->assertStringNotContainsString('Alih Daya sebagai <strong>………', $html);
        $this->assertStringNotContainsString('…-…..', $html);                   // cut off blank
        $this->assertStringNotContainsString('......Hari', $html);              // release pembayaran blank
        $this->assertStringNotContainsString('Tanggal ….(bulan)', $html);       // kontrak blank
        $this->assertStringNotContainsString('10% (Sepuluh  Persen)', $html);   // mgmt fee hardcoded lama
    }

    public function test_blank_when_data_missing_uses_dash_not_dots(): void
    {
        // Tanpa pks & persentase & alamat: harus '-' (bukan crash, bukan titik-titik).
        $leads = new Leads();
        $leads->nama_perusahaan = 'PT Kosong';
        // alamat/kota/provinsi/pic sengaja null

        $company = new Company();
        $company->id = 13;
        $company->name = 'PT SIG';
        $company->nama_direktur = 'Dir';

        $kebutuhan = new Kebutuhan();
        $service = new PksSigTemplateService(
            $leads, $company, $kebutuhan, new RuleThr(), new SalaryRule(),
            'PKS/X', null, null
        );

        $html = collect($service->generateAllSections())->pluck('raw_text')->implode("\n");

        $this->assertStringContainsString('berkedudukan di - Kota - Provinsi -', $html);
        $this->assertStringNotContainsString('Tanggal ….(bulan)', $html);
    }
}
