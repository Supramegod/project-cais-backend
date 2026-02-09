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
            Log::info('Starting quotation duplication process', [
                'quotation_id' => $quotation->id,
                'tipe_quotation' => $tipeQuotation,
                'has_referensi' => $quotationReferensi !== null
            ]);

            // ✅ 1. BUAT SEMUA SITE DARI REQUEST (TANPA BATASAN APAPUN)
            $sitesCreated = $this->createAllSitesNoRestrictions($quotation, $request, $user->full_name);

            Log::info('Sites created from request (no restrictions)', [
                'quotation_id' => $quotation->id,
                'sites_created_count' => count($sitesCreated),
                'requested_sites_count' => $this->countRequestedSites($request)
            ]);

            // ✅ 2. LOGIC BERDASARKAN ADA/TIDAKNYA REFERENSI
            if ($quotationReferensi) {
                $this->handleWithReference($quotation, $request, $tipeQuotation, $quotationReferensi, $user, $sitesCreated);
            } else {
                $this->handleWithoutReference($quotation, $request, $tipeQuotation, $user);
            }

            // ✅ 3. BUAT ACTIVITY
            $this->createActivity($quotation, $tipeQuotation, $quotationReferensi, $user);

            Log::info('Quotation duplication process completed', [
                'quotation_id' => $quotation->id,
                'sites_count' => $quotation->quotationSites()->count(),
                'details_count' => $quotation->quotationDetails()->count()
            ]);

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
     * Buat semua site dari request TANPA BATASAN APAPUN
     * - Duplikat dalam request? BUAT!
     * - Sudah ada di database? BUAT LAGI!
     * - Data tidak lengkap? BUAT DENGAN DATA DEFAULT!
     */
    private function createAllSitesNoRestrictions(Quotation $quotation, $request, string $createdBy): array
    {
        $createdSites = [];
        $leadsId = $request->perusahaan_id;

        if ($request->jumlah_site == "Multi Site") {
            foreach ($request->multisite as $key => $namaSite) {
                $provinsiId = $request->provinsi_multi[$key] ?? null;
                $kotaId = $request->kota_multi[$key] ?? null;
                $penempatan = $request->penempatan_multi[$key] ?? null;

                // ✅ BUAT SITE TANPA PENGECEKAN APAPUN
                $site = $this->createSiteWithoutChecks($quotation, $leadsId, $namaSite, $provinsiId, $kotaId, $penempatan, $createdBy);
                
                $createdSites[] = $site;
                
                Log::info('Created site (no restrictions)', [
                    'quotation_id' => $quotation->id,
                    'site_id' => $site->id,
                    'site_name' => $namaSite,
                    'key' => $key,
                    'total_created' => count($createdSites)
                ]);
            }
        } else {
            // Single site
            $namaSite = $request->nama_site;
            $provinsiId = $request->provinsi;
            $kotaId = $request->kota;
            $penempatan = $request->penempatan;

            // ✅ BUAT SITE TANPA PENGECEKAN APAPUN
            $site = $this->createSiteWithoutChecks($quotation, $leadsId, $namaSite, $provinsiId, $kotaId, $penempatan, $createdBy);
            
            $createdSites[] = $site;
            
            Log::info('Created single site (no restrictions)', [
                'quotation_id' => $quotation->id,
                'site_id' => $site->id,
                'site_name' => $namaSite
            ]);
        }

        return $createdSites;
    }

    /**
     * Buat site tanpa pengecekan apapun
     */
    private function createSiteWithoutChecks(Quotation $quotation, $leadsId, $namaSite, $provinsiId, $kotaId, $penempatan, string $createdBy): QuotationSite
    {
        try {
            // Default values jika data tidak lengkap
            $defaultProvinceId = $provinsiId ?? 31; // Default DKI Jakarta
            $defaultCityId = $kotaId ?? 3171; // Default Jakarta Pusat
            
            $province = Province::find($defaultProvinceId);
            $city = City::find($defaultCityId);

            // Jika province/city tidak ditemukan, gunakan default
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

            // Cari UMP/UMK jika ada
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

            $site = QuotationSite::create([
                'quotation_id' => $quotation->id,
                'leads_id' => $quotation->leads_id,
                'nama_site' => $namaSite ?? 'Site Tanpa Nama',
                'provinsi_id' => $province->id ?? null,
                'provinsi' => $province->nama ?? 'Unknown',
                'kota_id' => $city->id ?? null,
                'kota' => $city->name ?? 'Unknown',
                'ump' => $ump ? $ump->ump : 0,
                'umk' => $umk ? $umk->umk : 0,
                'penempatan' => $penempatan ?? 'Tidak Ditentukan',
                'created_by' => $createdBy
            ]);

            return $site;
            
        } catch (\Exception $e) {
            Log::error('Failed to create site, trying fallback', [
                'error' => $e->getMessage(),
                'quotation_id' => $quotation->id,
                'site_name' => $namaSite
            ]);
            
            // Fallback: buat site dengan data minimal
            return QuotationSite::create([
                'quotation_id' => $quotation->id,
                'leads_id' => $quotation->leads_id,
                'nama_site' => $namaSite ?? 'Site Error',
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
     * Handle dengan referensi
     */
    private function handleWithReference($quotation, $request, $tipeQuotation, $quotationReferensi, $user, $sitesCreated): void
    {
        Log::info('Starting duplication from reference', [
            'quotation_id' => $quotation->id,
            'referensi_id' => $quotationReferensi->id,
            'tipe_quotation' => $tipeQuotation,
            'sites_created_count' => count($sitesCreated),
            'ref_sites_count' => $quotationReferensi->quotationSites->count()
        ]);

        $jumlahSiteRequest = count($sitesCreated);
        $jumlahSiteReferensi = $quotationReferensi->quotationSites->count();

        // Tentukan metode duplikasi berdasarkan jumlah site
        if ($jumlahSiteRequest === $jumlahSiteReferensi) {
            // ✅ JUMLAH SITE SAMA: Gunakan mapping per site
            $this->quotationDuplicationService->duplicateQuotationWithSiteMapping($quotation, $quotationReferensi);
            Log::info('Used site mapping duplication (same count)', [
                'request_sites' => $jumlahSiteRequest,
                'ref_sites' => $jumlahSiteReferensi
            ]);
        } else {
            // ✅ JUMLAH SITE BERBEDA: Copy semua detail ke semua site
            $this->quotationDuplicationService->duplicateQuotationWithoutSites($quotation, $quotationReferensi);
            Log::info('Used no-site duplication (different count)', [
                'request_sites' => $jumlahSiteRequest,
                'ref_sites' => $jumlahSiteReferensi
            ]);
        }
    }

    /**
     * Handle tanpa referensi
     */
    private function handleWithoutReference($quotation, $request, $tipeQuotation, $user): void
    {
        Log::info('Creating quotation without reference', [
            'quotation_id' => $quotation->id,
            'tipe_quotation' => $tipeQuotation
        ]);

        // ✅ BUAT PIC AWAL (jika diperlukan)
        try {
            $this->quotationBusinessService->createInitialPic($quotation, $request, $user->full_name);
            Log::info('Created initial PIC for new quotation', [
                'quotation_id' => $quotation->id
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create initial PIC, continuing', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);
            // Lanjutkan tanpa PIC
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
        
        return isset($request->nama_site) && !empty($request->nama_site) ? 1 : 0;
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
            
            Log::info('Created activity for quotation', [
                'quotation_id' => $quotation->id,
                'activity_type' => $activityType
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create activity, continuing', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);
            // Lanjutkan tanpa activity
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