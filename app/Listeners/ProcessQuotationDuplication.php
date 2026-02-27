<?php

namespace App\Listeners;

use App\Events\QuotationCreated;
use App\Models\Quotation;
use App\Services\QuotationDuplicationService;
use App\Services\QuotationBusinessService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessQuotationDuplication implements ShouldQueue
{
    use InteractsWithQueue;

    protected $quotationDuplicationService;
    protected $quotationBusinessService;

    public function __construct(
        QuotationDuplicationService $quotationDuplicationService,
        QuotationBusinessService $quotationBusinessService
    ) {
        $this->quotationDuplicationService = $quotationDuplicationService;
        $this->quotationBusinessService = $quotationBusinessService;
    }

    public function handle(QuotationCreated $event): void
    {
        $quotation = $event->quotation;
        $request = (object) $event->requestData;
        $tipeQuotation = $event->tipeQuotation;
        $quotationReferensi = $event->quotationReferensi;
        $user = $event->user;

        try {
            Log::info('=== STARTING QUOTATION DUPLICATION PROCESS ===', [
                'quotation_id' => $quotation->id,
                'nomor' => $quotation->nomor,
                'tipe_quotation' => $tipeQuotation,
                'has_referensi' => $quotationReferensi !== null,
            ]);

            // ✅ Sites sudah dibuat secara synchronous di controller.
            // Listener hanya perlu handle duplikasi detail & activity.
            $existingSitesCount = $quotation->quotationSites()->count();

            Log::info('Sites status on queue start', [
                'existing_sites_count' => $existingSitesCount,
            ]);

            // ✅ LOGIC DUPLIKASI BERDASARKAN ADA/TIDAKNYA REFERENSI
            if ($quotationReferensi) {
                $this->handleWithReference($quotation, $request, $tipeQuotation, $quotationReferensi, $user);
            } else {
                $this->handleWithoutReference($quotation, $request, $tipeQuotation, $user);
            }

            // ✅ BUAT ACTIVITY
            $this->createActivity($quotation, $tipeQuotation, $quotationReferensi, $user);

            // ✅ VERIFIKASI FINAL
            Log::info('=== QUOTATION DUPLICATION COMPLETED ===', [
                'quotation_id' => $quotation->id,
                'final_sites_count' => $quotation->quotationSites()->count(),
                'final_details_count' => $quotation->quotationDetails()->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Quotation duplication failed', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $quotation->update([
                'error_message' => $e->getMessage(),
                'is_error' => 1,
            ]);

            throw $e;
        }
    }

    /**
     * Handle dengan referensi
     */
    private function handleWithReference($quotation, $request, $tipeQuotation, $quotationReferensi, $user): void
    {
        Log::info('Starting duplication FROM reference', [
            'quotation_id' => $quotation->id,
            'referensi_id' => $quotationReferensi->id,
            'tipe_quotation' => $tipeQuotation,
        ]);

        $jumlahSiteReferensi = $quotationReferensi->quotationSites->count();
        $currentSiteCount = $quotation->quotationSites()->count();

        Log::info('Site count comparison', [
            'referensi_count' => $jumlahSiteReferensi,
            'current_count' => $currentSiteCount,
        ]);

        if ($currentSiteCount === 0) {
            // ✅ Belum ada site → copy semua dari referensi termasuk sites-nya
            $this->quotationDuplicationService->duplicateQuotationData($quotation, $quotationReferensi);
            Log::info('No sites exist, copied ALL data including sites from reference');

        } elseif ($currentSiteCount === $jumlahSiteReferensi) {
            // ✅ Jumlah site sama → mapping detail per site
            $this->quotationDuplicationService->duplicateQuotationWithSiteMapping($quotation, $quotationReferensi);
            Log::info('Same site count, used site mapping');

        } else {
            // ✅ Jumlah site beda → duplikasi semua detail ke semua site
            $this->quotationDuplicationService->duplicateQuotationWithoutSites($quotation, $quotationReferensi);
            Log::info('Different site count, duplicated all details to all sites');
        }
    }

    /**
     * Handle tanpa referensi — hanya buat PIC awal
     */
    private function handleWithoutReference($quotation, $request, $tipeQuotation, $user): void
    {
        Log::info('Creating quotation WITHOUT reference', [
            'quotation_id' => $quotation->id,
            'tipe_quotation' => $tipeQuotation,
        ]);

        try {
            $this->quotationBusinessService->createInitialPic($quotation, $user->full_name);
            Log::info('Created initial PIC for new quotation');
        } catch (\Exception $e) {
            Log::warning('Failed to create initial PIC, continuing', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Buat activity
     */
    private function createActivity($quotation, $tipeQuotation, $quotationReferensi, $user): void
    {
        try {
            $activityType = match (true) {
                $tipeQuotation === 'revisi' => 'revisi',
                $tipeQuotation === 'rekontrak' => 'rekontrak',
                $tipeQuotation === 'adendum' => 'adendum',
                $tipeQuotation === 'baru' && $quotationReferensi !== null => 'baru_dengan_referensi',
                default => 'baru',
            };

            $this->quotationBusinessService->createInitialActivity(
                $quotation,
                $user->full_name,
                $user->id,
                $activityType,
                $quotationReferensi
            );

            Log::info('Created activity', ['activity_type' => $activityType]);
        } catch (\Exception $e) {
            Log::warning('Failed to create activity', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessQuotationDuplication job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}