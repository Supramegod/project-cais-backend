<?php

namespace App\Services\Quotation;

use App\Models\Company;
use App\Models\CustomerActivity;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\Quotation;
use App\Models\QuotationSite;
use App\Models\SalesActivity;
use App\Models\Pks;
use App\Models\Spk;
use App\Models\User;
use App\Events\QuotationCreated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuotationStoreService
{
    protected QuotationSiteService $siteService;

    public function __construct(QuotationSiteService $siteService)
    {
        $this->siteService = $siteService;
    }
    public function createQuotation(Request $request, string $tipe_quotation, User $user): array
    {
        if (!in_array($tipe_quotation, ['baru', 'revisi', 'rekontrak', 'addendum'])) {
            throw new \InvalidArgumentException('Tipe quotation tidak valid');
        }

        if (in_array($tipe_quotation, ['revisi', 'rekontrak', 'addendum'])) {
            if (!$request->has('quotation_referensi_id') || !$request->quotation_referensi_id) {
                throw new \InvalidArgumentException('Quotation referensi wajib dipilih untuk ' . $tipe_quotation);
            }
        }

        $quotationData = $this->prepareQuotationData($request);

        $quotationReferensi = null;
        if ($request->has('quotation_referensi_id') && $request->quotation_referensi_id) {
            $quotationReferensi = app(QuotationDataService::class)->loadQuotationReferensi($request->quotation_referensi_id);
            $quotationData['quotation_referensi_id'] = $quotationReferensi->id;
        }

        $quotationData['nomor'] = $this->generateNomorByType(
            $request->perusahaan_id,
            $request->entitas,
            $tipe_quotation,
            $quotationReferensi
        );

        $quotationData['created_by'] = $user->full_name;
        $quotationData['tipe_quotation'] = $tipe_quotation;

        $quotation = Quotation::create($quotationData);

        Log::info('New Quotation created', [
            'id' => $quotation->id, 'nomor' => $quotation->nomor,
            'tipe' => $tipe_quotation, 'has_referensi' => $quotationReferensi !== null,
        ]);

        if ($tipe_quotation === 'baru' && $quotationReferensi === null) {
            $this->createQuotationSites($quotation, $request, $user->full_name);
        }

        QuotationCreated::dispatch($quotation, $request->all(), $tipe_quotation, $quotationReferensi, $user);

        if ($tipe_quotation === 'revisi' && $quotationReferensi) {
            $this->updateRevisionStatuses($quotationReferensi);
        }

        $quotation->load([
            'quotationSites', 'quotationPics', 'quotationDetails', 'statusQuotation',
        ]);

        return compact('quotation', 'tipe_quotation') + ['sites_created' => $quotation->quotationSites->count()];
    }

    // ======================== DATA PREPARATION ==============================

    public function prepareQuotationData(Request $request): array
    {
        $leads = Leads::findOrFail($request->perusahaan_id);
        $kebutuhan = Kebutuhan::findOrFail($request->layanan);
        $company = Company::findOrFail($request->entitas);
        $statusTerminal = [99, 100, 101, 102];

        if (!in_array($leads->status_leads_id, $statusTerminal) && $request->tipe_quotation === 'baru') {
            $leads->update(['status_leads_id' => 4, 'updated_by' => Auth::user()?->full_name]);
        }

        return [
            'tgl_quotation' => Carbon::now()->toDateString(),
            'leads_id' => $request->perusahaan_id,
            'jumlah_site' => $request->jumlah_site,
            'nama_perusahaan' => $leads->nama_perusahaan,
            'kebutuhan_id' => $request->layanan,
            'kebutuhan' => $kebutuhan->nama,
            'company_id' => $request->entitas,
            'company' => $company->name,
            'step' => 1,
            'status_quotation_id' => 1,
            'tipe_quotation' => $request->tipe_quotation,
        ];
    }

    // ============================ SITE OPERATIONS (delegated) ============================

    public function createQuotationSites(Quotation $quotation, Request $request, string $createdBy): void
    {
        $this->siteService->createQuotationSites($quotation, $request, $createdBy);
    }

    public function createQuotationSiteFromReference(Quotation $quotation, QuotationSite $refSite, string $createdBy): QuotationSite
    {
        return $this->siteService->createQuotationSiteFromReference($quotation, $refSite, $createdBy);
    }

    public function createQuotationSite(Quotation $quotation, Request $request, ?int $index, bool $isMulti, string $createdBy): void
    {
        $this->siteService->createQuotationSite($quotation, $request, $index, $isMulti, $createdBy);
    }

    public function checkSiteExists(int $leadsId, string $namaSite, int $provinsiId, int $kotaId): bool
    {
        return $this->siteService->checkSiteExists($leadsId, $namaSite, $provinsiId, $kotaId);
    }

    public function createNewSitesOnly(Quotation $quotation, Request $request, string $createdBy): void
    {
        $this->siteService->createNewSitesOnly($quotation, $request, $createdBy);
    }

    // ======================== SOFT DELETE ================================

    public function softDeleteQuotationRelations(Quotation $quotation, string $deletedBy): void
    {
        $relations = [
            'quotationAplikasis', 'quotationDevices', 'quotationKaporlaps',
            'quotationChemicals', 'quotationOhcs', 'quotationDetails',
            'quotationDetailRequirements', 'quotationDetailHpps', 'quotationDetailCosses',
            'quotationDetailTunjangans', 'quotationPics', 'quotationTrainings',
            'quotationKerjasamas', 'quotationSites',
        ];

        foreach ($relations as $relation) {
            if ($quotation->$relation()->exists()) {
                $quotation->$relation()->update([
                    'deleted_at' => Carbon::now(),
                    'deleted_by' => $deletedBy,
                ]);
            }
        }
        $quotation->deleted_at = Carbon::now();
        $quotation->deleted_by = $deletedBy;
        $quotation->save();
    }

    // ======================== REVISION & ACTIVITY ===========================

    public function updateRevisionStatuses(Quotation $quotationReferensi): void
    {
        Spk::whereHas('spkSites', fn ($q) => $q->where('quotation_id', $quotationReferensi->id))
            ->update(['status_spk_id' => 5]);

        Pks::whereHas('sites', fn ($q) => $q->where('quotation_id', $quotationReferensi->id))
            ->update(['status_pks_id' => 8]);
    }

    public function createInitialPic(Quotation $quotation, string $createdBy): void
    {
        $leads = $quotation->leads;
        if (!$leads) {
            \Log::warning('createInitialPic: leads tidak ditemukan', ['quotation_id' => $quotation->id]);
            return;
        }

        if ($leads->pic || $leads->email || $leads->telp_perusahaan) {
            $quotation->quotationPics()->create([
                'leads_id' => $leads->id,
                'nama' => $leads->pic ?? 'Unknown',
                'jabatan' => $leads->jabatan ?? 'Contact Person',
                'email' => $leads->email ?? '',
                'no_hp' => $leads->telp_perusahaan ?? '',
                'created_by' => $createdBy,
                'created_by_user_id' => Auth::id(),
            ]);
        }
    }

    public function createInitialActivity(
        Quotation $quotation,
        string $createdBy,
        int $userId,
        string $tipe = 'baru',
        ?Quotation $quotationReferensi = null,
        $user = null
    ): void {
        $user = $user ?? Auth::user() ?? User::find($userId);

        if (!$user) {
            \Log::error('createInitialActivity: user tidak ditemukan', ['user_id' => $userId]);
            return;
        }

        $leads = $quotation->leads;
        $nomorActivity = $this->generateActivityNomor($quotation->leads_id);
        $notes = $this->generateActivityNotes($quotation, $tipe, $quotationReferensi);

        if (in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            $this->createSalesActivity($quotation, $createdBy, $user);
        } else {
            CustomerActivity::create([
                'leads_id' => $quotation->leads_id,
                'quotation_id' => $quotation->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => Carbon::now(),
                'nomor' => $nomorActivity,
                'tipe' => $this->getActivityType($tipe),
                'notes' => $notes,
                'is_activity' => 0,
                'user_id' => $userId,
                'created_by' => $createdBy,
                'created_by_user_id' => Auth::id(),
            ]);
        }

        if ($leads) {
            $leads->tgl_leads = Carbon::now()->toDateString();
            $leads->save();
        }
    }

    // ======================== NUMBER GENERATION ==============================

    public function generateNomorByType(int $leadsId, int $companyId, string $tipeQuotation, ?Quotation $quotationReferensi = null): string
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->format('m');

        if ($tipeQuotation === 'addendum' && $quotationReferensi) {
            $nomorRef = $quotationReferensi->nomor;
            $counter = Quotation::where('tipe_quotation', 'addendum')
                ->where('nomor', 'like', "ADD/{$nomorRef}/%")
                ->count() + 1;

            return 'ADD/' . $nomorRef . '/' . str_pad($counter, 5, '0', STR_PAD_LEFT);
        }

        $dataLeads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);

        $base = 'QUOT/';
        $base .= $company
            ? $company->code . '/' . $dataLeads->nomor . '-' . $month . $year . '-'
            : 'NN/NNNNN-' . $month . $year . '-';

        $counter = Quotation::where('tipe_quotation', $tipeQuotation)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $now->month)
            ->count() + 1;

        return $base . str_pad($counter, 5, '0', STR_PAD_LEFT);
    }

    public function generateNomor(int $leadsId, int $companyId): string
    {
        $now = Carbon::now();
        $dataLeads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);

        $nomor = 'QUOT/';
        if ($company) {
            $nomor .= $company->code . '/' . $dataLeads->nomor . '-';
        } else {
            $nomor .= 'NN/NNNNN-';
        }

        $month = $now->format('m');
        $jumlah = Quotation::where('nomor', 'like', $nomor . $month . $now->year . '-%')->count();
        $urutan = str_pad($jumlah + 1, 5, '0', STR_PAD_LEFT);

        return $nomor . $month . $now->year . '-' . $urutan;
    }

    public function generateActivityNomor(int $leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);
        $prefix = 'CAT/';

        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                2 => 'LS/', 1 => 'SG/', 3 => 'CS/', 4 => 'LL/',
                default => 'NN/',
            };
            $prefix .= $leads->nomor . '-';
        } else {
            $prefix .= 'NN/NNNNN-';
        }

        $month = $now->format('m');
        $year = $now->year;
        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . '-%')->count();
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . '-' . $sequence;
    }

    // ======================== PRIVATE HELPERS ================================

    private function createSalesActivity(Quotation $quotation, string $createdBy, $user): void
    {
        $leadsKebutuhan = LeadsKebutuhan::where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->where('tim_sales_d_id', $user->id)
            ->first();

        SalesActivity::create([
            'leads_id' => $quotation->leads_id,
            'leads_kebutuhan_id' => $leadsKebutuhan?->id,
            'tgl_activity' => Carbon::now(),
            'jenis_activity' => 'Quotation',
            'notulen' => "Quotation baru {$quotation->nomor} dibuat untuk kebutuhan {$quotation->kebutuhan}",
            'created_by' => $createdBy,
            'created_by_user_id' => Auth::id(),
        ]);
    }

    private function generateActivityNotes(Quotation $quotation, string $tipe, ?Quotation $quotationReferensi): string
    {
        if (!$quotationReferensi) return "Quotation baru {$quotation->nomor} dibuat dari awal";
        return match ($tipe) {
            'revisi' => "Quotation revisi {$quotation->nomor} dibuat dari referensi {$quotationReferensi->nomor}",
            'rekontrak' => "Quotation rekontrak {$quotation->nomor} dibuat dari kontrak sebelumnya {$quotationReferensi->nomor}",
            'addendum' => "Quotation addendum {$quotation->nomor} dibuat dari referensi {$quotationReferensi->nomor}",
            'baru_dengan_referensi' => "Quotation baru {$quotation->nomor} dibuat menggunakan data dari Quotation {$quotationReferensi->nomor}",
            default => "Quotation baru {$quotation->nomor} dibuat dari awal",
        };
    }

    private function getActivityType(string $tipe): string
    {
        return match ($tipe) {
            'revisi' => 'Quotation Revisi', 'rekontrak' => 'Quotation Rekontrak',
            'addendum' => 'Quotation addendum', 'baru_dengan_referensi' => 'Quotation copy',
            default => 'Quotation',
        };
    }

    public function validateMultiSiteData(Request $request): void
    {
        $this->siteService->validateMultiSiteData($request);
    }
}
