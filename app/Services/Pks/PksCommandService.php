<?php

namespace App\Services\Pks;

use App\Models\Company;
use App\Models\KategoriSesuaiHc;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\Loyalty;
use App\Models\Pks;
use App\Models\Quotation;
use App\Models\QuotationSite;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\Site;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Services\Pks\Template\PksTemplateFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PksCommandService
{
    public function __construct(
        private PksHelperService $helperService
    ) {}

    public function createPks($request, $tipe): Pks
    {
        return DB::transaction(function () use ($request, $tipe) {
            return $this->processPksLogic($request, $tipe);
        });
    }

    public function processPksLogic($request, $tipe): Pks
    {
        $leads = Leads::findOrFail($request->leads_id);

        $pksInduk = null;
        $quotationId = null;

        if ($tipe === 'addendum') {
            $pksNomor = $this->helperService->generateNomorAddendum($request->pks_id);
            $pksInduk = Pks::findOrFail($request->pks_id);
            $quotationId = $pksInduk->quotation_id;
            $companyId = $pksInduk->company_id ?? $request->entitas;
        } else {
            $pksNomor = $this->helperService->generateNomor($leads->id, $request->entitas);
            $companyId = $request->entitas;

            if ($tipe === 'rekontrak') {
                $firstSite = QuotationSite::select('quotation_id')->findOrFail($request->quotation_site_ids[0]);
                $quotationId = $firstSite->quotation_id;
            } else {
                $quotation = Quotation::select('id', 'kebutuhan_id', 'persentase')->where('leads_id', $leads->id)->first();
                $quotationId = $quotation->id ?? null;
            }
        }

        if ($tipe !== 'addendum' && isset($quotation)) {
            $layananId = $quotation->kebutuhan_id;
        } else {
            $quotation = $quotationId ? Quotation::select('id', 'kebutuhan_id', 'persentase')->find($quotationId) : null;
            $layananId = $quotation ? $quotation->kebutuhan_id : $leads->kebutuhan_id;
        }

        [$kebutuhan, $kategoriHC, $loyalty, $company, $ruleThr, $salaryRule] = [
            $layananId ? Kebutuhan::find($layananId) : null,
            $request->kategoriHC ? KategoriSesuaiHc::find($request->kategoriHC) : null,
            $request->loyalty ? Loyalty::find($request->loyalty) : null,
            $companyId ? Company::find($companyId) : null,
            $request->rule_thr ? RuleThr::find($request->rule_thr) : null,
            $request->salary_rule ? SalaryRule::find($request->salary_rule) : null,
        ];

        $pks = Pks::create([
            'leads_id' => $leads->id,
            'quotation_id' => $quotationId,
            'branch_id' => $leads->branch_id,
            'nomor' => $pksNomor,
            'tgl_pks' => $request->tanggal_pks,
            'kode_perusahaan' => $leads->nomor,
            'nama_perusahaan' => $leads->nama_perusahaan,
            'alamat_perusahaan' => $leads->alamat,
            'layanan_id' => $layananId,
            'layanan' => $kebutuhan->nama ?? null,
            'bidang_usaha_id' => $leads->bidang_perusahaan_id,
            'bidang_usaha' => $leads->bidang_perusahaan,
            'jenis_perusahaan_id' => $leads->jenis_perusahaan_id,
            'jenis_perusahaan' => $leads->jenis_perusahaan,
            'kontrak_awal' => $request->tanggal_awal_kontrak,
            'kontrak_akhir' => $request->tanggal_akhir_kontrak,
            'status_pks_id' => 5,
            'sales_id' => Auth::id(),
            'company_id' => $companyId,
            'salary_rule_id' => $request->salary_rule,
            'rule_thr_id' => $request->rule_thr,
            'kategori_sesuai_hc_id' => $request->kategoriHC,
            'kategori_sesuai_hc' => $kategoriHC->nama ?? null,
            'loyalty_id' => $request->loyalty,
            'loyalty' => $loyalty->nama ?? null,
            'provinsi_id' => $leads->provinsi_id,
            'provinsi' => $leads->provinsi,
            'kota_id' => $leads->kota_id,
            'kota' => $leads->kota,
            'pma' => $leads->pma,
            'pks_induk_id' => ($tipe === 'addendum') ? $request->pks_id : null,
            'tipe_pks' => $tipe,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);

        $siteIds = ($tipe === 'baru') ? $request->site_ids : $request->quotation_site_ids;
        $syncType = ($tipe === 'baru') ? 'baru' : 'rekontrak';
        $this->syncPksSites($pks, $siteIds, $pksNomor, $kebutuhan, $leads, $syncType);

        $this->helperService->createInitialActivity($pks, $leads, $pksNomor);
        $this->createPksPerjanjian($pks, $leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pksNomor, $quotation->persentase ?? null);

        Spk::where('leads_id', $leads->id)
            ->whereNotIn('status_spk_id', [100])
            ->update([
                'status_spk_id' => 3,
                'updated_by' => Auth::user()->full_name,
            ]);

        if ($quotationId) {
            Quotation::where('id', $quotationId)
                ->where('status_quotation_id', '!=', 100)
                ->update([
                    'status_quotation_id' => 5,
                    'updated_by' => Auth::user()->full_name,
                ]);
        }

        $statusTerminalLeads = [100, 101];
        if (!in_array($leads->status_leads_id, $statusTerminalLeads)) {
            $leads->update([
                'status_leads_id' => 99,
                'updated_by' => Auth::user()->full_name,
            ]);
        }

        return $pks;
    }

    public function syncPksSites($pks, array $siteIds, $pksNomor, $kebutuhan, $leads, $type = 'baru'): void
    {
        $isBaru = ($type === 'baru');
        $model = $isBaru ? SpkSite::class : QuotationSite::class;
        $sourceSites = $model::whereIn('id', $siteIds)
            ->with('quotation:id,nomor')
            ->get()
            ->keyBy('id');

        foreach ($siteIds as $key => $id) {
            $sourceSite = $sourceSites->get($id);
            if (!$sourceSite) {
                continue;
            }

            $nomorSite = $pksNomor . '-' . sprintf('%04d', ($key + 1));

            $namaProyek = sprintf(
                '%s-%s.%s.%s',
                Carbon::parse($pks->kontrak_awal)->format('my'),
                Carbon::parse($pks->kontrak_akhir)->format('my'),
                strtoupper(substr($kebutuhan->nama, 0, 2)),
                strtoupper($leads->nama_perusahaan)
            );

            Site::create([
                'pks_id' => $pks->id,
                'leads_id' => $leads->id,
                'quotation_id' => $sourceSite->quotation_id,
                'nomor' => $nomorSite,
                'nomor_proyek' => $nomorSite,
                'nama_proyek' => $namaProyek,
                'nama_site' => $sourceSite->nama_site,
                'provinsi_id' => $sourceSite->provinsi_id,
                'provinsi' => $sourceSite->provinsi,
                'kota_id' => $sourceSite->kota_id,
                'kota' => $sourceSite->kota,
                'nominal_upah' => $sourceSite->nominal_upah,
                'penempatan' => $sourceSite->penempatan,
                'kebutuhan_id' => $kebutuhan->id,
                'kebutuhan' => $kebutuhan->nama ?? null,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
                'spk_id' => $isBaru ? $sourceSite->spk_id : null,
                'spk_site_id' => $isBaru ? $sourceSite->id : null,
                'quotation_site_id' => $isBaru ? $sourceSite->quotation_site_id : $sourceSite->id,
                'nomor_quotation' => $isBaru ? $sourceSite->nomor_quotation : ($sourceSite->quotation->nomor ?? null),
                'ump' => $sourceSite->ump ?? null,
                'umk' => $sourceSite->umk ?? null,
            ]);
        }
    }

    private function createPksPerjanjian($pks, $leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pksNomor, $persentase = null): void
    {
        $templateService = (new PksTemplateFactory)->make(
            $leads, $company, $kebutuhan, $ruleThr, $salaryRule, $pksNomor, $pks, $persentase
        );

        $templateService->insertAgreementSections($pks->id, Auth::user()->full_name);
    }

    public function updatePks($id, array $data): Pks
    {
        $pks = Pks::find($id);
        if (!$pks) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('PKS not found');
        }

        return DB::transaction(function () use ($pks, $data) {
            $oldIsAktif = $pks->is_aktif;
            $oldKontrakAkhir = $pks->kontrak_akhir;
            $pks->update($data);

            if ($oldIsAktif != $pks->is_aktif || $oldKontrakAkhir != $pks->kontrak_akhir) {
                $this->autoSyncCustomerActiveStatus();
            }

            return $pks;
        });
    }

    public function deletePks($id): void
    {
        $pks = Pks::find($id);
        if (!$pks) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('PKS not found');
        }
        $pks->delete();
    }

    public function autoSyncCustomerActiveStatus(): void
    {
        $activeLeadsIds = Pks::select('leads_id')
            ->where('is_aktif', 1)
            ->whereNull('deleted_at')
            ->where('kontrak_akhir', '>=', now()->toDateString())
            ->pluck('leads_id')
            ->unique();

        DB::table('sl_leads')
            ->whereNotNull('customer_id')
            ->whereIn('id', $activeLeadsIds)
            ->where('customer_active', '!=', 1)
            ->update(['customer_active' => 1]);

        DB::table('sl_leads')
            ->whereNotNull('customer_id')
            ->whereNotIn('id', $activeLeadsIds)
            ->where('customer_active', '!=', 0)
            ->update(['customer_active' => 0]);
    }
}
