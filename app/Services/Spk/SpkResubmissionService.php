<?php

namespace App\Services\Spk;

use App\Models\Company;
use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\Quotation;
use App\Models\QuotationAplikasi;
use App\Models\QuotationChemical;
use App\Models\QuotationDetail;
use App\Models\QuotationDetailCoss;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationDetailRequirement;
use App\Models\QuotationDetailTunjangan;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;
use App\Models\QuotationKerjasama;
use App\Models\QuotationOhc;
use App\Models\QuotationPic;
use App\Models\QuotationSite;
use App\Models\QuotationTraining;
use App\Models\Spk;
use App\Models\SpkSite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SpkResubmissionService
{
    use SpkActivityTrait;

    public function ajukanUlangQuotation(int $spkId, array $quotationSiteIds, string $alasan): array
    {
        return DB::transaction(function () use ($spkId, $quotationSiteIds, $alasan) {
            $spk = Spk::with(['spkSites.quotation', 'spkSites.quotationSite'])->find($spkId);
            if (!$spk) throw new \Exception('SPK not found');
            $allSpkSites = $spk->spkSites;
            if ($allSpkSites->isEmpty()) throw new \Exception('Tidak ada site yang terkait dengan SPK ini.');
            $spkSitesToResubmit = $allSpkSites->whereIn('quotation_site_id', $quotationSiteIds);
            if ($spkSitesToResubmit->isEmpty()) throw new \Exception('Tidak ada site yang valid untuk diajukan ulang.');

            $quotationGroups = $spkSitesToResubmit->groupBy('quotation_id');
            $newQuotations = [];
            $deletedSpkSiteIds = [];
            $deletedQuotationSiteIds = [];
            $quotationAsal = null;

            foreach ($quotationGroups as $quotationId => $spkSites) {
                $quotationAsal = $spkSites->first()->quotation;
                if (!$quotationAsal) continue;
                $nomorBaru = $this->generateNomorQuotation($quotationAsal->leads_id, $quotationAsal->company_id, $quotationAsal->id);
                $newQuotation = $this->createNewQuotation($quotationAsal, $nomorBaru, $alasan);
                $newQuotations[] = $newQuotation;
                $this->copyQuotationRelatedData($quotationAsal->id, $newQuotation->id);
                foreach ($spkSites as $spkSite) {
                    $deletedSpkSiteIds[] = $spkSite->id;
                    $deletedQuotationSiteIds[] = $spkSite->quotation_site_id;
                }
                $quotationAsal->update(['deleted_at' => now(), 'deleted_by' => Auth::user()->full_name]);
                QuotationSite::whereIn('id', $deletedQuotationSiteIds)->update(['deleted_at' => now(), 'deleted_by' => Auth::user()->full_name]);
            }

            SpkSite::whereIn('id', $deletedSpkSiteIds)->update(['deleted_at' => now(), 'deleted_by' => Auth::user()->full_name]);
            $remainingSpkSites = SpkSite::where('spk_id', $spk->id)->whereNull('deleted_at')->count();
            $spkDeleted = false;
            if ($remainingSpkSites === 0) {
                $spk->update(['deleted_at' => now(), 'deleted_by' => Auth::user()->full_name]);
                $spkDeleted = true;
            }

            $this->createResubmissionActivities($quotationAsal, $newQuotation ?? null, $spk, $spkDeleted, $deletedSpkSiteIds, $deletedQuotationSiteIds);
            $newQuotation = $newQuotations[0] ?? null;
            return [
                'quotation_baru_id' => $newQuotation?->id, 'quotation_baru_nomor' => $newQuotation?->nomor,
                'spk_id' => $spk->id, 'spk_dihapus' => $spkDeleted,
                'spk_sites_dihapus' => $deletedSpkSiteIds, 'quotation_sites_dihapus' => $deletedQuotationSiteIds,
                'all_sites_resubmitted' => $spkDeleted,
            ];
        });
    }

    /**
     * Pengajuan ulang menghasilkan REVISI dari quotation asal, bukan dokumen
     * baru. Sebelumnya tipe di-hardcode 'baru' sehingga quotation revisi
     * mendapat nomor QUOT/ORG/... dan urutan revisinya tidak terlihat di nomor.
     */
    private function generateNomorQuotation(int $leadsId, ?int $companyId, int $referensiId): string
    {
        return app(\App\Services\Quotation\QuotationNumberingService::class)
            ->generate($leadsId, $companyId ?? 0, 'revisi', $referensiId);
    }

    private function createNewQuotation($quotationAsal, string $nomorQuotationBaru, string $alasan)
    {
        $data = $quotationAsal->toArray();
        unset($data['id'], $data['nomor'], $data['created_at'], $data['updated_at'], $data['deleted_at']);
        $data['nomor'] = $nomorQuotationBaru;
        $data['revisi'] = ($quotationAsal->revisi ?? 0) + 1;
        $data['alasan_revisi'] = $alasan;
        $data['quotation_asal_id'] = $quotationAsal->id;
        // Selaraskan data dengan nomor yang digenerate: dokumen ini adalah
        // revisi, dan rantai versinya ditelusuri lewat quotation_referensi_id.
        // Tanpa ini, toArray() akan mewarisi referensi milik quotation asal.
        $data['quotation_referensi_id'] = $quotationAsal->id;
        $data['tipe_quotation'] = 'revisi';
        $data['created_at'] = now();
        $data['created_by'] = Auth::user()->full_name;
        $data['updated_at'] = null;
        $data['updated_by'] = null;
        $data['ot1'] = null;
        $data['ot2'] = null;
        $data['ot3'] = null;
        $data['tgl_quotation'] = now()->format('Y-m-d');
        $data['tgl_penempatan'] = null;
        $isAktif = 1;
        $statusQuotation = 1;
        if ($quotationAsal->top == 'Lebih Dari 7 Hari') {
            $isAktif = 0;
            $statusQuotation = 2;
        }
        if ($quotationAsal->persentase < 7) {
            $isAktif = 0;
            $statusQuotation = 2;
        }
        $data['status_quotation_id'] = $statusQuotation;
        $data['is_aktif'] = $isAktif;
        $data['step'] = 1;
        return Quotation::create($data);
    }

    private function copyQuotationRelatedData(int $quotationAsalId, int $quotationBaruId): void
    {
        foreach ([
            QuotationSite::class, QuotationDetail::class, QuotationDetailRequirement::class,
            QuotationDetailHpp::class, QuotationDetailCoss::class, QuotationDetailTunjangan::class,
            QuotationKaporlap::class, QuotationDevices::class, QuotationChemical::class,
            QuotationOhc::class, QuotationAplikasi::class, QuotationKerjasama::class,
            QuotationPic::class, QuotationTraining::class,
        ] as $model) {
            $this->copyModelData($model, $quotationAsalId, $quotationBaruId);
        }
    }

    private function copyModelData(string $modelClass, int $quotationAsalId, int $quotationBaruId): void
    {
        foreach ($modelClass::where('quotation_id', $quotationAsalId)->whereNull('deleted_at')->get() as $record) {
            $newRecord = $record->replicate();
            $newRecord->quotation_id = $quotationBaruId;
            $newRecord->created_at = now();
            $newRecord->created_by = Auth::user()->full_name;
            $newRecord->save();
        }
    }

    private function createResubmissionActivities($quotationAsal, $newQuotation, $spk, bool $spkDeleted, array $deletedSpkSiteIds, array $deletedQuotationSiteIds): void
    {
        $leads = Leads::find($quotationAsal->leads_id);
        CustomerActivity::create([
            'leads_id' => $leads->id, 'quotation_id' => $quotationAsal->id, 'branch_id' => $leads->branch_id,
            'tgl_activity' => now(), 'nomor' => $this->generateActivityNomor($leads->id),
            'tipe' => 'Quotation', 'notes' => 'Quotation dengan nomor : ' . $quotationAsal->nomor . ' di ajukan ulang',
            'is_activity' => 0, 'user_id' => Auth::user()->id, 'created_by' => Auth::user()->full_name, 'created_by_user_id' => Auth::user()->id,
        ]);
        if ($newQuotation) {
            CustomerActivity::create([
                'leads_id' => $leads->id, 'quotation_id' => $newQuotation->id, 'branch_id' => $leads->branch_id,
                'tgl_activity' => now(), 'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'Quotation', 'notes' => 'Quotation dengan nomor : ' . $newQuotation->nomor . ' terbentuk dari ajukan ulang quotation dengan nomor : ' . $quotationAsal->nomor,
                'is_activity' => 0, 'user_id' => Auth::user()->id, 'created_by' => Auth::user()->full_name, 'created_by_user_id' => Auth::user()->id,
            ]);
        }
        if (!empty($deletedSpkSiteIds)) {
            CustomerActivity::create([
                'leads_id' => $leads->id, 'spk_id' => $spk->id, 'branch_id' => $leads->branch_id,
                'tgl_activity' => now(), 'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'SPK Site', 'notes' => count($deletedSpkSiteIds) . ' SPK site dihapus karena quotation diajukan ulang',
                'is_activity' => 0, 'user_id' => Auth::user()->id, 'created_by' => Auth::user()->full_name, 'created_by_user_id' => Auth::user()->id,
            ]);
        }
        if (!empty($deletedQuotationSiteIds)) {
            CustomerActivity::create([
                'leads_id' => $leads->id, 'quotation_id' => $quotationAsal->id, 'branch_id' => $leads->branch_id,
                'tgl_activity' => now(), 'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'Quotation Site', 'notes' => count($deletedQuotationSiteIds) . ' Quotation site dihapus karena diajukan ulang',
                'is_activity' => 0, 'user_id' => Auth::user()->id, 'created_by' => Auth::user()->full_name, 'created_by_user_id' => Auth::user()->id,
            ]);
        }
        if ($spkDeleted) {
            CustomerActivity::create([
                'leads_id' => $leads->id, 'spk_id' => $spk->id, 'branch_id' => $leads->branch_id,
                'tgl_activity' => now(), 'nomor' => $this->generateActivityNomor($leads->id),
                'tipe' => 'SPK', 'notes' => 'SPK dengan nomor : ' . $spk->nomor . ' dihapus karena semua quotation site diajukan ulang',
                'is_activity' => 0, 'user_id' => Auth::user()->id, 'created_by' => Auth::user()->full_name, 'created_by_user_id' => Auth::user()->id,
            ]);
        }
        if ($leads) {
            $leads->tgl_leads = now()->toDateString();
            $leads->save();
        }
    }
}
