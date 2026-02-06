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
use Illuminate\Support\Facades\Log;

class ProcessQuotationDuplication
{
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
        $request = $event->request;
        $tipeQuotation = $event->tipeQuotation;
        $quotationReferensi = $event->quotationReferensi;
        $user = $event->user;

        // Hitung $hasNewSiteRequest dan $hasExistingSite
        $hasNewSiteRequest = false;
        $hasExistingSite = false;

        if ($request->jumlah_site == "Multi Site") {
            if ($request->has('multisite') && !empty($request->multisite)) {
                foreach ($request->multisite as $key => $namaSite) {
                    $isExisting = $this->checkSiteExists(
                        $request->perusahaan_id,
                        $namaSite,
                        $request->provinsi_multi[$key] ?? null,
                        $request->kota_multi[$key] ?? null
                    );

                    if ($isExisting) {
                        $hasExistingSite = true;
                        Log::info('Site sudah existing', [
                            'nama_site' => $namaSite,
                            'perusahaan_id' => $request->perusahaan_id
                        ]);
                    } else {
                        $hasNewSiteRequest = true;
                    }
                }
            }
        } else {
            if ($request->has('nama_site') && !empty($request->nama_site)) {
                $isExisting = $this->checkSiteExists(
                    $request->perusahaan_id,
                    $request->nama_site,
                    $request->provinsi,
                    $request->kota
                );

                if ($isExisting) {
                    $hasExistingSite = true;
                    Log::info('Site sudah existing', [
                        'nama_site' => $request->nama_site,
                        'perusahaan_id' => $request->perusahaan_id
                    ]);
                } else {
                    $hasNewSiteRequest = true;
                }
            }
        }

        Log::info('Site request check in listener', [
            'jumlah_site' => $request->jumlah_site,
            'has_new_site_request' => $hasNewSiteRequest,
            'has_existing_site' => $hasExistingSite,
            'has_referensi' => $quotationReferensi !== null,
            'tipe_quotation' => $tipeQuotation
        ]);

        // Logic berdasarkan status site
        if ($quotationReferensi) {
            Log::info('Starting duplication process from reference for ' . $tipeQuotation, [
                'has_new_site_request' => $hasNewSiteRequest,
                'has_existing_site' => $hasExistingSite,
                'ref_site_count' => $quotationReferensi->quotationSites->count()
            ]);

            if (!$hasNewSiteRequest && !$hasExistingSite) {
                // Kasus 1: TIDAK ADA SITE BARU & TIDAK ADA SITE EXISTING
                $this->quotationDuplicationService->duplicateQuotationData($quotation, $quotationReferensi);
                Log::info('Copied ALL data including sites from reference quotation');

            } elseif ($hasExistingSite && !$hasNewSiteRequest) {
                // Kasus 2: ADA SITE EXISTING, TIDAK ADA SITE BARU
                $this->linkExistingSites($quotation, $request, $user->full_name);
                $this->quotationDuplicationService->duplicateQuotationWithoutSites($quotation, $quotationReferensi);
                Log::info('Linked to existing sites and copied other data');

            } else {
                // Kasus 3: ADA SITE BARU (dengan atau tanpa existing site)
                $this->createNewSitesOnly($quotation, $request, $user->full_name);

                $jumlahSiteRequest = 0;
                if ($request->jumlah_site == "Multi Site") {
                    $jumlahSiteRequest = is_array($request->multisite) ? count($request->multisite) : 0;
                } else {
                    $jumlahSiteRequest = 1;
                }

                $jumlahSiteReferensi = $quotationReferensi->quotationSites->count();

                Log::info('Site comparison for new sites', [
                    'jumlah_site_request' => $jumlahSiteRequest,
                    'jumlah_site_referensi' => $jumlahSiteReferensi,
                    'has_existing_site' => $hasExistingSite
                ]);

                if ($jumlahSiteRequest === $jumlahSiteReferensi && !$hasExistingSite) {
                    $this->quotationDuplicationService->duplicateQuotationWithSiteMapping($quotation, $quotationReferensi);
                    Log::info('Copied data with site mapping (same count, no existing)');
                } else {
                    $this->quotationDuplicationService->duplicateQuotationWithoutSites($quotation, $quotationReferensi);
                    Log::info('Copied all data to new sites (different count or with existing)');
                }
            }
        } else {
            // Kasus 4: TIDAK ADA REFERENSI (hanya untuk tipe 'baru')
            Log::info('Creating quotation WITHOUT reference (brand new)');

            if (!$hasNewSiteRequest && !$hasExistingSite) {
                throw new \Exception('Data site wajib diisi untuk quotation baru tanpa referensi');
            }

            $this->createNewSitesOnly($quotation, $request, $user->full_name);
            $this->quotationBusinessService->createInitialPic($quotation, $request, $user->full_name);
        }

        // Create initial activity
        $this->createActivity($quotation, $tipeQuotation, $quotationReferensi, $user);
    }

    /**
     * Helper methods
     */
    private function checkSiteExists($leadsId, $namaSite, $provinsiId, $kotaId)
    {
        return QuotationSite::where('leads_id', $leadsId)
            ->where('nama_site', $namaSite)
            ->where('provinsi_id', $provinsiId)
            ->where('kota_id', $kotaId)
            ->exists();
    }

    private function linkExistingSites(Quotation $quotation, $request, $createdBy)
    {
        $leadsId = $request->perusahaan_id;

        if ($request->jumlah_site == "Multi Site") {
            foreach ($request->multisite as $key => $namaSite) {
                $site = QuotationSite::where('leads_id', $leadsId)
                    ->where('nama_site', $namaSite)
                    ->where('provinsi_id', $request->provinsi_multi[$key])
                    ->where('kota_id', $request->kota_multi[$key])
                    ->first();

                if ($site) {
                    $site->update(['quotation_id' => $quotation->id]);
                    Log::info('Linked existing site to new quotation', [
                        'site_id' => $site->id,
                        'quotation_id' => $quotation->id
                    ]);
                }
            }
        } else {
            $site = QuotationSite::where('leads_id', $leadsId)
                ->where('nama_site', $request->nama_site)
                ->where('provinsi_id', $request->provinsi)
                ->where('kota_id', $request->kota)
                ->first();

            if ($site) {
                $site->update(['quotation_id' => $quotation->id]);
                Log::info('Linked existing site to new quotation', [
                    'site_id' => $site->id,
                    'quotation_id' => $quotation->id
                ]);
            }
        }
    }

    private function createNewSitesOnly(Quotation $quotation, $request, $createdBy)
    {
        $leadsId = $request->perusahaan_id;

        if ($request->jumlah_site == "Multi Site") {
            foreach ($request->multisite as $key => $namaSite) {
                $isExisting = $this->checkSiteExists(
                    $leadsId,
                    $namaSite,
                    $request->provinsi_multi[$key],
                    $request->kota_multi[$key]
                );

                if (!$isExisting) {
                    $this->createNewSite($quotation, $request, $key, true, $createdBy);
                }
            }
        } else {
            $isExisting = $this->checkSiteExists(
                $leadsId,
                $request->nama_site,
                $request->provinsi,
                $request->kota
            );

            if (!$isExisting) {
                $this->createNewSite($quotation, $request, null, false, $createdBy);
            }
        }
    }

    private function createNewSite(Quotation $quotation, $request, $index, $isMulti, $createdBy)
    {
        $provinceId = $isMulti ? $request->provinsi_multi[$index] : $request->provinsi;
        $cityId = $isMulti ? $request->kota_multi[$index] : $request->kota;

        $province = Province::findOrFail($provinceId);
        $city = City::findOrFail($cityId);

        $ump = Ump::where('province_id', $province->id)
            ->active()
            ->first();

        $umk = Umk::where('city_id', $city->id)
            ->active()
            ->first();

        QuotationSite::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'nama_site' => $isMulti ? $request->multisite[$index] : $request->nama_site,
            'provinsi_id' => $provinceId,
            'provinsi' => $province->nama,
            'kota_id' => $cityId,
            'kota' => $city->name,
            'ump' => $ump ? $ump->ump : 0,
            'umk' => $umk ? $umk->umk : 0,
            'penempatan' => $isMulti ? $request->penempatan_multi[$index] : $request->penempatan,
            'created_by' => $createdBy
        ]);

        Log::info('Created new site for quotation', [
            'quotation_id' => $quotation->id,
            'site_name' => $isMulti ? $request->multisite[$index] : $request->nama_site
        ]);
    }

    private function createActivity(Quotation $quotation, $tipeQuotation, $quotationReferensi, $user)
    {
        $activityType = 'baru';

        if ($tipeQuotation === 'revisi') {
            $activityType = 'revisi';
        } elseif ($tipeQuotation === 'rekontrak') {
            $activityType = 'rekontrak';
        } elseif ($tipeQuotation === 'addendum') {
            $activityType = 'addendum';
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
    }
}