<?php

namespace App\Jobs;

use App\Models\LeadsKebutuhan;
use App\Models\LogNotification;
use App\Models\Pks;
use App\Models\Quotation;
use App\Models\QuotationDetailRequirement;
use App\Models\QuotationSite;
use App\Models\Site;
use App\Models\Spk;
use App\Models\SpkSite;
use App\Services\Pks\AddendumService;
use App\Services\Pks\KerjasamaService;
use App\Services\Quotation\QuotationBusinessService;
use App\Services\Quotation\QuotationNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProcessQuotationFinalization implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $quotationId,
        public ?array $kerjasamaData,
        public string $user,
        public int $statusQuotationId,
        public string $tipeQuotation,
        public ?int $oldQuotationId,
        public ?int $userId = null,
    ) {}

    public function handle(
        QuotationBusinessService $businessService,
        QuotationNotificationService $notificationService,
        KerjasamaService $kerjasamaService,
    ): void {
        $quotation = Quotation::with([
            'quotationDetails.quotationDetailRequirements',
        ])->find($this->quotationId);

        if (! $quotation) {
            Log::warning('ProcessQuotationFinalization: Quotation not found', [
                'id' => $this->quotationId,
            ]);

            return;
        }

        $now = Carbon::now();

        $kerjasamaService->syncFromFinalization($quotation, $this->kerjasamaData, $now, $this->user);
        $this->insertRequirements($quotation, $now);

        if ($this->statusQuotationId == 2) {
            $this->notifyDirSales($quotation, $notificationService, $now);
        }

        if (in_array($this->statusQuotationId, [2, 3]) && $this->tipeQuotation === 'revisi' && $this->oldQuotationId) {
            $oldQuotation = Quotation::withTrashed()->find($this->oldQuotationId);
            if ($oldQuotation) {
                $this->updateDownstreamReferences($oldQuotation, $quotation, $businessService);
            }
        }

        if ($this->statusQuotationId == 3 && $this->tipeQuotation === 'revisi') {
            $this->updateRevisionStatuses($quotation);
        }

        if ($this->statusQuotationId == 8 && $this->tipeQuotation === 'revisi') {
            $this->revertRevisionStatuses($quotation);
        }

        if ($this->statusQuotationId == 3 && $this->tipeQuotation === 'addendum') {
            $this->updateDownstreamReferencesForAddendum($quotation, $businessService);
            app(AddendumService::class)->process(
                $quotation,
                $this->user,
                $this->userId,
            );
        }

        Log::info('ProcessQuotationFinalization: completed', [
            'quotation_id' => $quotation->id,
            'status_quotation_id' => $this->statusQuotationId,
        ]);
    }

    private function insertRequirements(Quotation $quotation, Carbon $now): void
    {
        $detailsWithoutReqs = $quotation->quotationDetails->filter(function ($detail) {
            return $detail->quotationDetailRequirements->count() == 0;
        });

        if ($detailsWithoutReqs->isEmpty()) {
            return;
        }

        $positionIds = $detailsWithoutReqs->pluck('id')->unique()->toArray();

        $allRequirements = QuotationDetailRequirement::whereNull('deleted_at')
            ->whereIn('quotation_detail_id', $positionIds)
            ->get()
            ->groupBy('quotation_detail_id');

        $batchInsert = [];
        foreach ($detailsWithoutReqs as $detail) {
            $requirements = $allRequirements[$detail->id] ?? collect();
            foreach ($requirements as $req) {
                $batchInsert[] = [
                    'quotation_id' => $quotation->id,
                    'quotation_detail_id' => $detail->id,
                    'requirement' => $req->requirement,
                    'created_at' => $now,
                    'created_by' => $this->user,
                    'created_by_user_id' => $this->user,
                ];
            }
        }

        if (! empty($batchInsert)) {
            QuotationDetailRequirement::insert($batchInsert);
        }
    }

    private function notifyDirSales(Quotation $quotation, QuotationNotificationService $notificationService, Carbon $now): void
    {
        $dirSales = [27927, 127822];

        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        $creatorName = $leadsKebutuhan?->timSalesD?->nama ?? $this->user;
        $msg = "Quotation dengan nomor: {$quotation->nomor} telah selesai dibuat oleh {$creatorName} dan membutuhkan persetujuan Direktur sales.";

        foreach ($dirSales as $userId) {
            LogNotification::create([
                'user_id' => $userId,
                'doc_id' => $quotation->id,
                'transaksi' => 'Quotation',
                'tabel' => 'sl_quotation',
                'pesan' => $msg,
                'is_read' => 0,
                'created_at' => $now,
                'created_by' => $creatorName,
                'created_by_user_id' => Auth::id(),
            ]);
        }

        $approvalUrl = 'https://cais2.shelterapp2.co.id/quotation/view/'.$quotation->id;
        $notificationService->sendApprovalNotification(
            quotation: $quotation,
            creatorName: $creatorName,
            approvalUrl: $approvalUrl,
            overrideRecipients: QuotationNotificationService::dirSales(),
        );

        dispatch(new EscalateQuotationJob($quotation->id, 'Sales', $now))
            ->delay(now()->addDay());
    }

    private function updateDownstreamReferences(Quotation $oldQuotation, Quotation $newQuotation, QuotationBusinessService $businessService): void
    {
        $oldSites = QuotationSite::withTrashed()
            ->where('quotation_id', $oldQuotation->id)
            ->get()
            ->keyBy('id');

        $newSitesByNama = QuotationSite::where('quotation_id', $newQuotation->id)
            ->get()
            ->keyBy('nama_site');

        SpkSite::where('quotation_id', $oldQuotation->id)
            ->each(function (SpkSite $spkSite) use ($oldSites, $newSitesByNama, $newQuotation) {
                $oldSite = $oldSites->get($spkSite->quotation_site_id);
                if (! $oldSite) {
                    return;
                }

                $newSite = $newSitesByNama->get($oldSite->nama_site);
                if (! $newSite) {
                    return;
                }

                $spkSite->update([
                    'quotation_id' => $newQuotation->id,
                    'quotation_site_id' => $newSite->id,
                    'updated_by' => $this->user,
                ]);
            });

        Site::where('quotation_id', $oldQuotation->id)
            ->each(function (Site $site) use ($oldSites, $newSitesByNama, $newQuotation) {
                $oldSite = $oldSites->get($site->quotation_site_id);
                if (! $oldSite) {
                    return;
                }

                $newSite = $newSitesByNama->get($oldSite->nama_site);
                if (! $newSite) {
                    return;
                }

                $site->update([
                    'quotation_id' => $newQuotation->id,
                    'quotation_site_id' => $newSite->id,
                    'updated_by' => $this->user,
                ]);
            });

        $businessService->softDeleteQuotationRelations($oldQuotation, $this->user);
    }

    private function updateRevisionStatuses(Quotation $quotation): void
    {
        Spk::whereHas('spkSites', fn ($q) => $q->where('quotation_id', $quotation->id)
        )->update(['status_spk_id' => 1]);

        Pks::whereHas('sites', fn ($q) => $q->where('quotation_id', $quotation->id)
        )->update(['status_pks_id' => 5]);
    }

    private function revertRevisionStatuses(Quotation $quotation): void
    {
        $quotationIds = array_filter([$quotation->id, $quotation->quotation_referensi_id]);

        Spk::whereHas('spkSites', fn ($q) => $q->whereIn('quotation_id', $quotationIds)
        )->update(['status_spk_id' => 6]);

        Pks::whereHas('sites', fn ($q) => $q->whereIn('quotation_id', $quotationIds)
        )->update(['status_pks_id' => 10]);
    }

    private function updateDownstreamReferencesForAddendum(Quotation $quotation, QuotationBusinessService $businessService): void
    {
        $oldQuotation = Quotation::find($quotation->quotation_referensi_id);
        if (! $oldQuotation) {
            return;
        }

        SpkSite::where('quotation_id', $oldQuotation->id)
            ->update(['quotation_id' => $quotation->id, 'updated_by' => $this->user]);

        Site::where('quotation_id', $oldQuotation->id)
            ->update(['quotation_id' => $quotation->id, 'updated_by' => $this->user]);
    }
}
