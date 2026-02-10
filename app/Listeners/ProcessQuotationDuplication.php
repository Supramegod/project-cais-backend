<?php

namespace App\Listeners;

use App\Events\QuotationCreated;
use App\Models\Quotation;
use App\Services\QuotationDuplicationService;
use App\Services\QuotationBusinessService;
use App\Models\QuotationSite;
use App\Models\Province;
use App\Models\City;
use App\Models\Ump;
use App\Models\Umk;
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

    public function handle(QuotationCreated $event)
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
                'has_referensi' => $quotationReferensi !== null
            ]);

            // ✅ 1. HITUNG JUMLAH SITE YANG DIMINTA
            $requestedSiteCount = $this->countRequestedSites($request);
            Log::info('Requested sites count', [
                'count' => $requestedSiteCount,
                'jumlah_site' => $request->jumlah_site ?? 'N/A'
            ]);

            // ✅ 2. CEK APAKAH SITE SUDAH DIBUAT
            $existingSitesCount = $quotation->quotationSites()->count();

            if ($existingSitesCount === 0 && $requestedSiteCount > 0) {
                // ✅ Belum ada site DAN ada request site, buat dari request
                $this->createSitesFromRequest($quotation, $request, $user->full_name);
                Log::info('Sites created from request', [
                    'created_count' => $quotation->quotationSites()->count()
                ]);
            } else {
                Log::info('Sites already exist or no site requested', [
                    'existing_count' => $existingSitesCount,
                    'requested_count' => $requestedSiteCount
                ]);
            }

            // ✅ 3. LOGIC BERDASARKAN ADA/TIDAKNYA REFERENSI
            if ($quotationReferensi) {
                $this->handleWithReference($quotation, $request, $tipeQuotation, $quotationReferensi, $user);
            } else {
                $this->handleWithoutReference($quotation, $request, $tipeQuotation, $user);
            }

            // ✅ 4. BUAT ACTIVITY
            $this->createActivity($quotation, $tipeQuotation, $quotationReferensi, $user);

            // ✅ 5. VERIFIKASI FINAL
            $finalSiteCount = $quotation->quotationSites()->count();
            $finalDetailCount = $quotation->quotationDetails()->count();

            Log::info('=== QUOTATION DUPLICATION COMPLETED ===', [
                'quotation_id' => $quotation->id,
                'final_sites_count' => $finalSiteCount,
                'final_details_count' => $finalDetailCount,
                'expected_sites' => $requestedSiteCount
            ]);

            if ($requestedSiteCount > 0 && $finalSiteCount !== $requestedSiteCount) {
                Log::warning('SITE COUNT MISMATCH!', [
                    'expected' => $requestedSiteCount,
                    'actual' => $finalSiteCount
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Quotation duplication failed', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $quotation->update([
                'error_message' => $e->getMessage(),
                'is_error' => 1
            ]);

            throw $e;
        }
    }

    /**
     * ✅ Buat site dari request - SELALU BUAT BARU (TANPA PENGECEKAN EXISTING)
     */
    private function createSitesFromRequest(Quotation $quotation, $request, string $createdBy): void
    {
        Log::info('Creating sites from request (ALWAYS CREATE NEW)', [
            'quotation_id' => $quotation->id,
            'jumlah_site' => $request->jumlah_site ?? 'N/A'
        ]);

        if ($request->jumlah_site == "Multi Site") {
            // Multi site
            if (!isset($request->multisite) || !is_array($request->multisite)) {
                throw new \Exception('Multisite data not found in request');
            }

            $siteCount = count($request->multisite);
            Log::info('Processing multi-site request', ['site_count' => $siteCount]);

            for ($i = 0; $i < $siteCount; $i++) {
                $this->createSingleSite(
                    $quotation,
                    $request->multisite[$i] ?? "Site $i",
                    $request->provinsi_multi[$i] ?? null,
                    $request->kota_multi[$i] ?? null,
                    $request->penempatan_multi[$i] ?? null,
                    $createdBy,
                    $i
                );
            }
        } else {
            // Single site
            $this->createSingleSite(
                $quotation,
                $request->nama_site ?? 'Site',
                $request->provinsi ?? null,
                $request->kota ?? null,
                $request->penempatan ?? null,
                $createdBy,
                0
            );
        }
    }

    /**
     * ✅ Buat single site - LANGSUNG BUAT TANPA PENGECEKAN DUPLIKASI
     */
    private function createSingleSite(Quotation $quotation, string $namaSite, $provinsiId, $kotaId, $penempatan, string $createdBy, int $index): void
    {
        try {
            // Default values jika data tidak lengkap
            $defaultProvinceId = $provinsiId ?? 31; // DKI Jakarta
            $defaultCityId = $kotaId ?? 3171; // Jakarta Pusat

            $province = Province::find($defaultProvinceId);
            $city = City::find($defaultCityId);

            if (!$province) {
                $province = Province::first();
                Log::warning('Province not found, using default', [
                    'requested_province_id' => $provinsiId,
                    'default_province_id' => $province->id ?? null
                ]);
            }

            if (!$city) {
                $city = City::first();
                Log::warning('City not found, using default', [
                    'requested_city_id' => $kotaId,
                    'default_city_id' => $city->id ?? null
                ]);
            }

            // Cari UMP/UMK
            $ump = null;
            $umk = null;

            if ($province) {
                $ump = Ump::where('province_id', $province->id)
                    ->active()
                    ->first();
            }

            if ($city) {
                $umk = Umk::where('city_id', $city->id)
                    ->active()
                    ->first();
            }

            // ✅ LANGSUNG CREATE TANPA CEK DUPLIKASI
            $site = QuotationSite::create([
                'quotation_id' => $quotation->id,
                'leads_id' => $quotation->leads_id,
                'nama_site' => $namaSite,
                'provinsi_id' => $province->id ?? null,
                'provinsi' => $province->nama ?? 'Unknown',
                'kota_id' => $city->id ?? null,
                'kota' => $city->name ?? 'Unknown',
                'ump' => $ump ? $ump->ump : 0,
                'umk' => $umk ? $umk->umk : 0,
                'penempatan' => $penempatan ?? 'Tidak Ditentukan',
                'created_by' => $createdBy
            ]);

            Log::info('Site created successfully', [
                'quotation_id' => $quotation->id,
                'site_id' => $site->id,
                'site_name' => $namaSite,
                'index' => $index,
                'total_sites_now' => $quotation->quotationSites()->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create site', [
                'error' => $e->getMessage(),
                'quotation_id' => $quotation->id,
                'site_name' => $namaSite,
                'index' => $index
            ]);

            // Fallback minimal
            QuotationSite::create([
                'quotation_id' => $quotation->id,
                'leads_id' => $quotation->leads_id,
                'nama_site' => $namaSite . " (Fallback)",
                'provinsi_id' => null,
                'provinsi' => 'Error',
                'kota_id' => null,
                'kota' => 'Error',
                'ump' => 0,
                'umk' => 0,
                'penempatan' => 'Error',
                'created_by' => $createdBy
            ]);
        }
    }

    /**
     * ✅ Handle dengan referensi
     */
    private function handleWithReference($quotation, $request, $tipeQuotation, $quotationReferensi, $user): void
    {
        Log::info('Starting duplication FROM reference', [
            'quotation_id' => $quotation->id,
            'referensi_id' => $quotationReferensi->id,
            'tipe_quotation' => $tipeQuotation
        ]);

        // Hitung jumlah site
        $jumlahSiteRequest = $this->countRequestedSites($request);
        $jumlahSiteReferensi = $quotationReferensi->quotationSites->count();
        $currentSiteCount = $quotation->quotationSites()->count();

        Log::info('Site count comparison', [
            'request_count' => $jumlahSiteRequest,
            'referensi_count' => $jumlahSiteReferensi,
            'current_count' => $currentSiteCount
        ]);

        // ✅ LOGIC DUPLIKASI BERDASARKAN KONDISI SITE
        if ($currentSiteCount === 0 && $jumlahSiteRequest === 0) {
            // ✅ TIDAK ADA SITE: Copy semua termasuk sites dari referensi
            $this->quotationDuplicationService->duplicateQuotationData($quotation, $quotationReferensi);
            Log::info('No sites exist, copied ALL data including sites from reference');

        } elseif ($currentSiteCount === $jumlahSiteReferensi) {
            // ✅ JUMLAH SITE SAMA: Mapping per site
            $this->quotationDuplicationService->duplicateQuotationWithSiteMapping($quotation, $quotationReferensi);
            Log::info('Same site count, used site mapping');

        } else {
            // ✅ JUMLAH SITE BERBEDA: Duplikasi semua detail ke semua site
            $this->quotationDuplicationService->duplicateQuotationWithoutSites($quotation, $quotationReferensi);
            Log::info('Different site count, duplicated all details to all sites');
        }
    }

    /**
     * ✅ Handle tanpa referensi
     */
    private function handleWithoutReference($quotation, $request, $tipeQuotation, $user): void
    {
        Log::info('Creating quotation WITHOUT reference', [
            'quotation_id' => $quotation->id,
            'tipe_quotation' => $tipeQuotation
        ]);

        // Hanya buat PIC awal jika diperlukan
        try {
            $this->quotationBusinessService->createInitialPic($quotation, $user->full_name);
            Log::info('Created initial PIC for new quotation');
        } catch (\Exception $e) {
            Log::warning('Failed to create initial PIC, continuing', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Hitung jumlah site yang diminta dalam request
     */
    private function countRequestedSites($request): int
    {
        if ($request->jumlah_site == "Multi Site") {
            return isset($request->multisite) && is_array($request->multisite)
                ? count($request->multisite)
                : 0;
        }

        return (isset($request->nama_site) && !empty($request->nama_site)) ? 1 : 0;
    }

    /**
     * Buat activity
     */
    private function createActivity($quotation, $tipeQuotation, $quotationReferensi, $user): void
    {
        try {
            $activityType = 'baru';

            if ($tipeQuotation === 'revisi') {
                $activityType = 'revisi';
            } elseif ($tipeQuotation === 'rekontrak') {
                $activityType = 'rekontrak';
            } elseif ($tipeQuotation === 'adendum') {
                $activityType = 'adendum';
            } elseif ($tipeQuotation === 'baru' && $quotationReferensi) {
                $activityType = 'baru_dengan_referensi';
            }

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
                'error' => $e->getMessage()
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
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
