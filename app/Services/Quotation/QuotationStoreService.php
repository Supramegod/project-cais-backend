<?php

namespace App\Services\Quotation;

use App\Models\Company;
use App\Models\CustomerActivity;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\Quotation;
use App\Models\QuotationSite;
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
    protected QuotationNumberingService $numberingService;

    public function __construct(
        QuotationSiteService $siteService,
        QuotationNumberingService $numberingService
    ) {
        $this->siteService = $siteService;
        $this->numberingService = $numberingService;
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

        $quotationData['nomor'] = $this->numberingService->generate(
            $request->perusahaan_id,
            $request->entitas,
            $tipe_quotation,
            $quotationReferensi?->id
        );

        $quotationData['created_by'] = $user->full_name;
        $quotationData['created_by_user_id'] = $user->id;
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

    // ======================== NUMBER GENERATION ==============================

    public function generateNomorByType(int $leadsId, int $companyId, string $tipeQuotation, ?Quotation $quotationReferensi = null): string
    {
        return $this->numberingService->generate(
            $leadsId,
            $companyId,
            $tipeQuotation,
            $quotationReferensi?->id
        );
    }

    /**
     * Legacy — delegasi ke QuotationNumberingService.
     */
    public function generateNomor(int $leadsId, int $companyId): string
    {
        return $this->numberingService->generate($leadsId, $companyId, 'baru');
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

    public function validateMultiSiteData(Request $request): void
    {
        $this->siteService->validateMultiSiteData($request);
    }
}
