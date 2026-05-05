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
        $request = \Illuminate\Http\Request::create('/', 'POST', $event->requestData);
        $tipeQuotation = $event->tipeQuotation;
        $quotationReferensi = $event->quotationReferensi;
        $user = $event->user; // ✅ User object dari event — TIDAK pakai Auth::user()

        try {
            Log::info('=== STARTING QUOTATION DUPLICATION PROCESS ===', [
                'quotation_id' => $quotation->id,
                'nomor' => $quotation->nomor,
                'tipe_quotation' => $tipeQuotation,
                'has_referensi' => $quotationReferensi !== null,
                'user_id' => $user->id,
                'user_role' => $user->cais_role_id,
            ]);

            // Cek site yang sudah ada
            $existingSitesCount = $quotation->quotationSites()->count();

            Log::info('Sites status on queue start', ['existing_sites_count' => $existingSitesCount]);

            // Jika belum ada site, buat dari request
            if ($existingSitesCount === 0) {
                $this->quotationBusinessService->createQuotationSites($quotation, $request, $user->full_name);

                Log::info('Sites created from request', [
                    'created_count' => $quotation->quotationSites()->count(),
                ]);
            }

            // Logic duplikasi
            if ($quotationReferensi) {
                $this->handleWithReference($quotation, $request, $tipeQuotation, $quotationReferensi, $user);
            } else {
                $this->handleWithoutReference($quotation, $tipeQuotation, $user);
            }

            // Buat activity — ✅ user dipasskan secara eksplisit
            $this->createActivity($quotation, $tipeQuotation, $quotationReferensi, $user);

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
     * Handle dengan referensi — duplikasi detail berdasarkan name matching site
     */
    private function handleWithReference($quotation, $request, $tipeQuotation, $quotationReferensi, $user): void
    {
        // Ambil nama site referensi dan quotation baru
        $referensiSiteNames = $quotationReferensi->quotationSites->pluck('nama_site');
        $currentSiteNames = $quotation->quotationSites->pluck('nama_site');
        $hasMatchingSites = $currentSiteNames->intersect($referensiSiteNames)->isNotEmpty();

        Log::info('Starting duplication FROM reference', [
            'quotation_id' => $quotation->id,
            'referensi_id' => $quotationReferensi->id,
            'tipe_quotation' => $tipeQuotation,
            'referensi_sites' => $referensiSiteNames,
            'current_sites' => $currentSiteNames,
            'has_matching' => $hasMatchingSites,
        ]);

        if ($hasMatchingSites) {
            // Ada site yang namanya sama → mapping by name
            // Site yang dihapus di revisi otomatis di-skip beserta barang-barangnya
            $this->quotationDuplicationService->duplicateQuotationWithSiteMapping($quotation, $quotationReferensi);
            Log::info('Used site mapping by name');
        } else {
            // Tidak ada yang match → duplikasi semua detail ke semua site
            $this->quotationDuplicationService->duplicateQuotationWithoutSites($quotation, $quotationReferensi);
            Log::info('No matching sites, duplicated all details to all sites');
        }
    }

    /**
     * Handle tanpa referensi — hanya buat PIC awal
     */
    private function handleWithoutReference($quotation, $tipeQuotation, $user): void
    {
        Log::info('Creating quotation WITHOUT reference', [
            'quotation_id' => $quotation->id,
            'tipe_quotation' => $tipeQuotation,
        ]);

        try {
            $this->quotationBusinessService->createInitialPic($quotation, $user->full_name);
            Log::info('Created initial PIC for new quotation');
        } catch (\Exception $e) {
            Log::warning('Failed to create initial PIC, continuing', ['error' => $e->getMessage()]);
        }
    }

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
                $quotationReferensi,
                $user           // ← parameter baru
            );

            Log::info('Created activity', ['activity_type' => $activityType]);
        } catch (\Exception $e) {
            Log::warning('Failed to create activity', ['error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessQuotationDuplication job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}