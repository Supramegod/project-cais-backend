<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\Pks;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Services\Pks\Template\PksPerjanjianTemplateService;
use Tests\TestCase;

/**
 * Verifies the FALLBACK PKS agreement template (used for companies NOT in the
 * factory MAP) fills its dynamic placeholders from the available data — first
 * party address/PIC, service type, salary-rule schedule, THR schedule, contract
 * dates (Pasal 7 jangka waktu) and management fee — instead of leaving hardcoded
 * placeholder text ("Bapak/Ibu", a hardcoded Jakarta address) or omitting the
 * contract period entirely.
 *
 * Pure in-memory: the template only reads model attributes, so no DB is needed.
 */
class PksFallbackTemplateFillTest extends TestCase
{
    private function makeModels(): array
    {
        $leads = new Leads();
        $leads->nama_perusahaan = 'PT ABC Sejahtera';
        $leads->alamat = 'Jl. Melati No. 1';
        $leads->kota = 'Bandung';
        $leads->provinsi = 'Jawa Barat';
        $leads->pic = 'budi santoso';

        // Company id 99 is NOT in the factory MAP [13,14,16,17], so the fallback
        // template is the one that would be selected for it.
        $company = new Company();
        $company->id = 99;
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

    public function test_fallback_template_fills_dynamic_placeholders(): void
    {
        $m = $this->makeModels();
        $service = new PksPerjanjianTemplateService(
            $m['leads'], $m['company'], $m['kebutuhan'], $m['ruleThr'], $m['salaryRule'],
            'PKS/FALLBACK/ABC-072026-00001', $m['pks'], 12.5
        );

        $html = collect($service->generateAllSections())->pluck('raw_text')->implode("\n");

        // First party (leads) address, city, province, PIC:
        $this->assertStringContainsString('Jl. Melati No. 1', $html);
        $this->assertStringContainsString('Kota Bandung', $html);
        $this->assertStringContainsString('Provinsi Jawa Barat', $html);
        $this->assertStringContainsString('BUDI SANTOSO', $html); // PIC uppercased, null-safe

        // Service / jenis pekerjaan:
        $this->assertStringContainsString('Cleaning Service', $html);

        // Salary schedule (Pasal 4):
        $this->assertStringContainsString('26-25', $html);   // cutoff
        $this->assertStringContainsString('14 Hari', $html); // pembayaran invoice
        $this->assertStringContainsString('Tanggal 25', $html); // rilis payroll

        // Contract period (Pasal 7 jangka waktu):
        $this->assertStringContainsString('1 Juli 2026', $html);
        $this->assertStringContainsString('30 Juni 2027', $html);

        // Management fee (dynamic percentage):
        $this->assertStringContainsString('12,5% dari nilai invoice', $html);

        // Hardcoded placeholder text is gone:
        $this->assertStringNotContainsString('Bapak/Ibu', $html);
        // hardcoded Jakarta address for the first party removed:
        $this->assertStringNotContainsString('Ps. Baru, Kecamatan Sawah Besar', $html);

        // Pasal 7 is now the contract period, not a duplicated force majeure:
        $this->assertStringContainsString('JANGKA WAKTU PERJANJIAN', $html);
        $this->assertStringContainsString('Masa berlakunya Perjanjian ini', $html);
    }

    public function test_fallback_blank_when_data_missing_uses_dash_not_crash(): void
    {
        $leads = new Leads();
        $leads->nama_perusahaan = 'PT Kosong';
        $leads->pic = 'x'; // pic used raw (strtoupper) in signature block

        $company = new Company();
        $company->id = 99;
        $company->name = 'PT SIG';
        $company->nama_direktur = 'Dir';

        $service = new PksPerjanjianTemplateService(
            $leads, $company, new Kebutuhan(), new RuleThr(), new SalaryRule(),
            'PKS/X', null, null
        );

        $html = collect($service->generateAllSections())->pluck('raw_text')->implode("\n");

        // No crash, contract dates fall back to '-', management fee to '-'.
        $this->assertStringContainsString('- dari nilai invoice', $html);
        $this->assertStringNotContainsString('Bapak/Ibu', $html);
    }
}
