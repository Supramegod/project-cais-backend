<?php
// app/Services/RekontrakService.php

namespace App\Services;

use App\Models\Spk;
use App\Models\SpkSite;
use App\Models\Pks;
use App\Models\Site;
use App\Models\Quotation;
use App\Models\Leads;
use App\Models\CustomerActivity;
use App\Models\LeadsKebutuhan;
use App\Models\SalesActivity;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RekontrakService
{
    /**
     * Proses rekontrak - buat SPK dan PKS baru (REFACTORED untuk support shared PKS)
     */
    public function process(Quotation $newQuot, Quotation $oldQuot): array
    {
        DB::beginTransaction();
        
        $results = [
            'old_quot_id' => $oldQuot->id,
            'old_quot_no' => $oldQuot->nomor,
            'new_quot_id' => $newQuot->id,
            'new_quot_no' => $newQuot->nomor,
            'new_spks' => [],
            'new_pks_list' => [],
            'activities' => 0,
            'errors' => [],
            'warnings' => []
        ];

        try {
            Log::info('Mulai proses rekontrak', [
                'old_quot' => $oldQuot->id,
                'new_quot' => $newQuot->id
            ]);
            
            // Debug: Cek SPK yang terkait dengan quotation lama
            $relatedSpks = Spk::where('quotation_id', $oldQuot->id)
                ->whereNull('deleted_at')
                ->get();
            
            Log::info('Related SPKs for old quotation', [
                'old_quot_id' => $oldQuot->id,
                'spk_count' => $relatedSpks->count(),
                'spk_details' => $relatedSpks->map(function($spk) {
                    return [
                        'id' => $spk->id,
                        'nomor' => $spk->nomor,
                        'quotation_id' => $spk->quotation_id
                    ];
                })->toArray()
            ]);

            // 1. Non-aktifkan quot lama
            $this->deactivateQuot($oldQuot);
            
            // 2. Cari SpkSite yang pakai quot lama
            $oldSpkSites = SpkSite::where('quotation_id', $oldQuot->id)
                ->whereNull('deleted_at')
                ->with(['spk.sites', 'leads'])
                ->get();
            
            Log::info('SpkSite search result', [
                'old_quot_id' => $oldQuot->id,
                'spk_sites_found' => $oldSpkSites->count(),
                'spk_sites_details' => $oldSpkSites->map(function($site) {
                    return [
                        'id' => $site->id,
                        'spk_id' => $site->spk_id,
                        'quotation_id' => $site->quotation_id,
                        'nama_site' => $site->nama_site
                    ];
                })->toArray()
            ]);
            
            if ($oldSpkSites->isEmpty()) {
                // Coba cari dengan cara lain - melalui SPK yang punya quotation_id sama
                $alternativeSpkSites = SpkSite::whereHas('spk', function($query) use ($oldQuot) {
                    $query->where('quotation_id', $oldQuot->id);
                })->whereNull('deleted_at')
                ->with(['spk.sites', 'leads'])
                ->get();
                
                Log::info('Alternative SpkSite search', [
                    'old_quot_id' => $oldQuot->id,
                    'alternative_sites_found' => $alternativeSpkSites->count()
                ]);
                
                if ($alternativeSpkSites->isNotEmpty()) {
                    $oldSpkSites = $alternativeSpkSites;
                } else {
                    $results['warnings'][] = 'Tidak ada SpkSite yang pakai quot lama';
                    Log::warning('Tidak ada SpkSite untuk quot lama', ['quot_id' => $oldQuot->id]);
                }
            }
            
            if ($oldSpkSites->isNotEmpty()) {
                // 3. Group by PKS (bukan SPK) untuk support shared PKS
                $pksGroups = $this->groupSpkSitesByPks($oldSpkSites);
                
                foreach ($pksGroups as $pksId => $pksData) {
                    Log::info('Processing PKS group', ['pks_id' => $pksId, 'spk_count' => count($pksData['spks'])]);
                    
                    // 4. Buat PKS baru dulu
                    $newPks = $this->createPks($pksData['oldPks'], null, $newQuot);
                    
                    if ($newPks) {
                        $newSpks = [];
                        
                        // 5. Process semua SPK dalam PKS ini
                        foreach ($pksData['spks'] as $spkData) {
                            $oldSpk = $spkData['oldSpk'];
                            
                            // 6. Buat SPK baru
                            $newSpk = $this->createSpk($oldSpk, $newQuot, $spkData['leads']);
                            
                            if ($newSpk) {
                                // 7. Link SPK ke PKS yang sama
                                $this->linkSpkToPks($newSpk, $newPks);
                                
                                // 8. Buat SpkSite untuk SPK ini
                                $sitesCreated = $this->createMixedSpkSites($oldSpk, $newSpk, $newQuot, collect($spkData['sites']));
                                
                                $newSpks[] = [
                                    'id' => $newSpk->id,
                                    'nomor' => $newSpk->nomor,
                                    'old_spk_id' => $oldSpk->id,
                                    'old_spk_no' => $oldSpk->nomor,
                                    'sites_created' => $sitesCreated
                                ];
                                
                                // 9. Buat Activity untuk SPK
                                $this->createSpkActivity($newSpk, $spkData['leads']);
                                $results['activities']++;
                            }
                        }
                        
                        // 10. Buat PKS Sites dari semua SPK baru
                        $pksSitesCreated = $this->createPksSitesFromMultipleSpks($pksData['oldPks'], $newPks, $newSpks, $newQuot);
                        
                        $results['new_spks'] = array_merge($results['new_spks'], $newSpks);
                        $results['new_pks_list'][] = [
                            'id' => $newPks->id,
                            'nomor' => $newPks->nomor,
                            'old_pks_id' => $pksData['oldPks']->id,
                            'old_pks_no' => $pksData['oldPks']->nomor,
                            'sites_created' => $pksSitesCreated,
                            'linked_spks' => count($newSpks)
                        ];
                        
                        // 11. Buat Activity untuk PKS
                        $this->createPksActivity($newPks, $pksData['leads']);
                        $results['activities']++;
                    }
                }
            }
            
            DB::commit();
            
            Log::info('Proses rekontrak selesai', [
                'new_spks' => count($results['new_spks']),
                'new_pks' => count($results['new_pks_list']),
                'activities' => $results['activities']
            ]);
            
            return $results;

        } catch (\Exception $e) {
            DB::rollBack();
            
            $results['errors'][] = $e->getMessage();
            Log::error('Error proses rekontrak: ' . $e->getMessage(), [
                'old_quot' => $oldQuot->id,
                'new_quot' => $newQuot->id
            ]);
            
            return $results;
        }
    }

    /**
     * Group SpkSites by PKS untuk support shared PKS
     */
    private function groupSpkSitesByPks($oldSpkSites): array
    {
        $pksGroups = [];
        
        foreach ($oldSpkSites as $spkSite) {
            $spk = $spkSite->spk;
            if (!$spk) {
                Log::warning('SpkSite tanpa SPK', ['spk_site_id' => $spkSite->id]);
                continue;
            }
            
            Log::debug('Processing SpkSite', [
                'spk_site_id' => $spkSite->id,
                'spk_id' => $spk->id,
                'spk_nomor' => $spk->nomor,
                'pks_relation_count' => $spk->sites ? $spk->sites->count() : 0
            ]);
            
            // Ambil PKS dari Site yang terkait dengan SPK
            $site = $spk->sites ? $spk->sites->first() : null;
            if (!$site) {
                Log::warning('SPK tanpa Site', [
                    'spk_id' => $spk->id,
                    'spk_nomor' => $spk->nomor,
                    'spk_site_id' => $spkSite->id
                ]);
                continue;
            }
            
            $pks = $site->pks;
            if (!$pks) {
                Log::warning('Site tanpa PKS', [
                    'spk_id' => $spk->id,
                    'spk_nomor' => $spk->nomor,
                    'site_id' => $site->id,
                    'spk_site_id' => $spkSite->id
                ]);
                continue;
            }
            
            $pksId = $pks->id;
            
            // Initialize PKS group jika belum ada
            if (!isset($pksGroups[$pksId])) {
                $pksGroups[$pksId] = [
                    'oldPks' => $pks,
                    'leads' => $spkSite->leads,
                    'spks' => []
                ];
            }
            
            $spkId = $spk->id;
            
            // Initialize SPK dalam PKS group jika belum ada
            if (!isset($pksGroups[$pksId]['spks'][$spkId])) {
                $pksGroups[$pksId]['spks'][$spkId] = [
                    'oldSpk' => $spk,
                    'leads' => $spkSite->leads,
                    'sites' => []
                ];
            }
            
            // Tambahkan SpkSite ke SPK
            $pksGroups[$pksId]['spks'][$spkId]['sites'][] = $spkSite;
        }
        
        Log::info('Grouped SpkSites by PKS', [
            'pks_count' => count($pksGroups),
            'pks_details' => array_map(function($pksData) {
                return [
                    'pks_id' => $pksData['oldPks']->id,
                    'spk_count' => count($pksData['spks'])
                ];
            }, $pksGroups)
        ]);
        
        return $pksGroups;
    }

    /**
     * Link SPK ke PKS
     */
    private function linkSpkToPks(Spk $spk, Pks $pks): void
    {
        // Update SPK untuk link ke PKS (jika ada field pks_id di SPK)
        // Atau buat record di pivot table jika menggunakan many-to-many
        
        // Asumsi: Ada field pks_id di table spk
        $spk->update(['pks_id' => $pks->id]);
        
        Log::debug('Linked SPK to PKS', [
            'spk_id' => $spk->id,
            'pks_id' => $pks->id
        ]);
    }

    /**
     * Buat PKS Sites dari multiple SPK
     */
    private function createPksSitesFromMultipleSpks(Pks $oldPks, Pks $newPks, array $newSpks, Quotation $newQuot): int
    {
        $created = 0;
        
        // Ambil semua SpkSite dari semua SPK baru
        $allSpkSites = collect();
        foreach ($newSpks as $spkData) {
            $spkSites = SpkSite::where('spk_id', $spkData['id'])
                ->whereNull('deleted_at')
                ->get();
            $allSpkSites = $allSpkSites->merge($spkSites);
        }
        
        foreach ($allSpkSites as $spkSite) {
            try {
                // Cari apakah site ini ada di PKS lama
                $oldSite = Site::where('pks_id', $oldPks->id)
                    ->where('nama_site', $spkSite->nama_site)
                    ->where('provinsi', $spkSite->provinsi)
                    ->where('kota', $spkSite->kota)
                    ->first();
                
                // Generate nomor site
                $siteCount = Site::where('pks_id', $newPks->id)->count();
                $nomorSite = $newPks->nomor . '-' . sprintf("%04d", ($siteCount + 1));
                
                // Generate nama proyek
                $namaProyek = sprintf(
                    '%s-%s.%s.%s',
                    Carbon::parse($newPks->kontrak_awal)->format('my'),
                    Carbon::parse($newPks->kontrak_akhir)->format('my'),
                    strtoupper(substr($newPks->layanan ?? 'NN', 0, 2)),
                    strtoupper($newPks->nama_perusahaan)
                );
                
                // Buat Site baru - gunakan data lama jika ada
                if ($oldSite) {
                    Site::create([
                        'quotation_id' => $oldSite->quotation_id,
                        'spk_id' => $spkSite->spk_id,
                        'pks_id' => $newPks->id,
                        'quotation_site_id' => $oldSite->quotation_site_id,
                        'spk_site_id' => $spkSite->id,
                        'leads_id' => $oldSite->leads_id,
                        'nomor' => $nomorSite,
                        'nomor_proyek' => $namaProyek,
                        'nama_proyek' => $namaProyek,
                        'nama_site' => $oldSite->nama_site,
                        'provinsi_id' => $oldSite->provinsi_id,
                        'provinsi' => $oldSite->provinsi,
                        'kota_id' => $oldSite->kota_id,
                        'kota' => $oldSite->kota,
                        'ump' => $oldSite->ump,
                        'umk' => $oldSite->umk,
                        'nominal_upah' => $oldSite->nominal_upah,
                        'penempatan' => $oldSite->penempatan,
                        'kebutuhan_id' => $oldSite->kebutuhan_id,
                        'kebutuhan' => $oldSite->kebutuhan,
                        'nomor_quotation' => $oldSite->nomor_quotation,
                        'created_by' => Auth::user()->full_name ?? 'System'
                    ]);
                } else {
                    Site::create([
                        'quotation_id' => $spkSite->quotation_id,
                        'spk_id' => $spkSite->spk_id,
                        'pks_id' => $newPks->id,
                        'quotation_site_id' => $spkSite->quotation_site_id,
                        'spk_site_id' => $spkSite->id,
                        'leads_id' => $spkSite->leads_id,
                        'nomor' => $nomorSite,
                        'nomor_proyek' => $namaProyek,
                        'nama_proyek' => $namaProyek,
                        'nama_site' => $spkSite->nama_site,
                        'provinsi_id' => $spkSite->provinsi_id,
                        'provinsi' => $spkSite->provinsi,
                        'kota_id' => $spkSite->kota_id,
                        'kota' => $spkSite->kota,
                        'ump' => $spkSite->ump,
                        'umk' => $spkSite->umk,
                        'nominal_upah' => $spkSite->nominal_upah,
                        'penempatan' => $spkSite->penempatan,
                        'kebutuhan_id' => $spkSite->kebutuhan_id,
                        'kebutuhan' => $spkSite->kebutuhan,
                        'nomor_quotation' => $spkSite->nomor_quotation,
                        'created_by' => Auth::user()->full_name ?? 'System'
                    ]);
                }
                
                $created++;
                
            } catch (\Exception $e) {
                Log::error('Error buat Site PKS dari multiple SPK: ' . $e->getMessage());
            }
        }
        
        Log::info('PKS Sites dari multiple SPK dibuat', [
            'total_created' => $created,
            'new_pks_id' => $newPks->id,
            'spk_count' => count($newSpks)
        ]);
        
        return $created;
    }
    private function createSpk(Spk $oldSpk, Quotation $newQuot, $leads): ?Spk
    {
        try {
            // Generate nomor SPK baru
            $spkNomor = $this->generateSpkNo($leads->id);
            
            $newSpk = Spk::create([
                'leads_id' => $oldSpk->leads_id,
                'quotation_id' => $newQuot->id,
                'nomor' => $spkNomor,
                'tgl_spk' => Carbon::now()->format('Y-m-d'),
                'nama_perusahaan' => $oldSpk->nama_perusahaan,
                'tim_sales_id' => $oldSpk->tim_sales_id,
                'tim_sales_d_id' => $oldSpk->tim_sales_d_id,
                'link_spk_disetujui' => null,
                'status_spk_id' => 1, // Draft
                'created_by' => Auth::user()->full_name ?? 'System'
            ]);
            
            Log::info('SPK baru dibuat', [
                'new_spk_id' => $newSpk->id,
                'new_spk_no' => $newSpk->nomor,
                'old_spk_id' => $oldSpk->id
            ]);
            
            return $newSpk;
            
        } catch (\Exception $e) {
            Log::error('Error buat SPK baru: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Buat SpkSite baru (gabungan)
     */
    private function createMixedSpkSites(Spk $oldSpk, Spk $newSpk, Quotation $newQuot, $oldSpkSites): int
    {
        $created = 0;
        
        // 1. Copy semua SpkSite dari SPK lama
        $allOldSpkSites = SpkSite::where('spk_id', $oldSpk->id)
            ->whereNull('deleted_at')
            ->get();
        
        foreach ($allOldSpkSites as $oldSpkSite) {
            try {
                // Cek apakah ini site yang direkontrak
                $isRekontrak = $oldSpkSite->quotation_id == $newQuot->id || 
                    $oldSpkSites->contains('id', $oldSpkSite->id);
                
                if ($isRekontrak) {
                    // Untuk site yang direkontrak, pakai quotation baru
                    $newQuotSite = $newQuot->quotationSites()
                        ->where('nama_site', $oldSpkSite->nama_site)
                        ->where('provinsi', $oldSpkSite->provinsi)
                        ->where('kota', $oldSpkSite->kota)
                        ->first();
                    
                    if ($newQuotSite) {
                        SpkSite::create([
                            'spk_id' => $newSpk->id,
                            'quotation_id' => $newQuot->id,
                            'quotation_site_id' => $newQuotSite->id,
                            'leads_id' => $oldSpkSite->leads_id,
                            'nama_site' => $oldSpkSite->nama_site,
                            'provinsi_id' => $oldSpkSite->provinsi_id,
                            'provinsi' => $oldSpkSite->provinsi,
                            'kota_id' => $oldSpkSite->kota_id,
                            'kota' => $oldSpkSite->kota,
                            'ump' => $newQuotSite->ump,
                            'umk' => $newQuotSite->umk,
                            'nominal_upah' => $newQuotSite->nominal_upah,
                            'penempatan' => $newQuotSite->penempatan,
                            'kebutuhan_id' => $oldSpkSite->kebutuhan_id,
                            'kebutuhan' => $oldSpkSite->kebutuhan,
                            'jenis_site' => $oldSpkSite->jenis_site,
                            'nomor_quotation' => $newQuot->nomor,
                            'created_by' => Auth::user()->full_name ?? 'System'
                        ]);
                        
                        $created++;
                    }
                } else {
                    // Untuk site yang tidak direkontrak, copy dari lama
                    SpkSite::create([
                        'spk_id' => $newSpk->id,
                        'quotation_id' => $oldSpkSite->quotation_id,
                        'quotation_site_id' => $oldSpkSite->quotation_site_id,
                        'leads_id' => $oldSpkSite->leads_id,
                        'nama_site' => $oldSpkSite->nama_site,
                        'provinsi_id' => $oldSpkSite->provinsi_id,
                        'provinsi' => $oldSpkSite->provinsi,
                        'kota_id' => $oldSpkSite->kota_id,
                        'kota' => $oldSpkSite->kota,
                        'ump' => $oldSpkSite->ump,
                        'umk' => $oldSpkSite->umk,
                        'nominal_upah' => $oldSpkSite->nominal_upah,
                        'penempatan' => $oldSpkSite->penempatan,
                        'kebutuhan_id' => $oldSpkSite->kebutuhan_id,
                        'kebutuhan' => $oldSpkSite->kebutuhan,
                        'jenis_site' => $oldSpkSite->jenis_site,
                        'nomor_quotation' => $oldSpkSite->nomor_quotation,
                        'created_by' => Auth::user()->full_name ?? 'System'
                    ]);
                    
                    $created++;
                }
                
            } catch (\Exception $e) {
                Log::error('Error buat SpkSite baru: ' . $e->getMessage());
            }
        }
        
        Log::info('SpkSite gabungan dibuat', [
            'total_created' => $created,
            'new_spk_id' => $newSpk->id
        ]);
        
        return $created;
    }

    /**
     * Buat PKS baru
     */
    private function createPks(Pks $oldPks, ?Spk $newSpk, Quotation $newQuot): ?Pks
    {
        try {
            $leads = $oldPks->leads;
            
            if (!$leads) {
                throw new \Exception("Leads tidak ditemukan untuk PKS lama");
            }
            
            // Generate nomor PKS baru
            $pksNomor = $this->generatePksNo($leads->id, $oldPks->company_id);
            
            // Buat PKS baru dengan copy data dari PKS lama
            $newPks = Pks::create([
                'leads_id' => $oldPks->leads_id,
                'spk_id' => $newSpk?->id, // Nullable karena SPK bisa dibuat setelah PKS
                'quotation_id' => $newQuot->id,
                'branch_id' => $oldPks->branch_id,
                'nomor' => $pksNomor,
                'tgl_pks' => Carbon::now()->format('Y-m-d'),
                'kode_perusahaan' => $oldPks->kode_perusahaan,
                'nama_perusahaan' => $oldPks->nama_perusahaan,
                'alamat_perusahaan' => $oldPks->alamat_perusahaan,
                'layanan_id' => $oldPks->layanan_id,
                'layanan' => $oldPks->layanan,
                'bidang_usaha_id' => $oldPks->bidang_usaha_id,
                'bidang_usaha' => $oldPks->bidang_usaha,
                'jenis_perusahaan_id' => $oldPks->jenis_perusahaan_id,
                'jenis_perusahaan' => $oldPks->jenis_perusahaan,
                'kontrak_awal' => $oldPks->kontrak_awal,
                'kontrak_akhir' => $oldPks->kontrak_akhir,
                'status_pks_id' => 5, // Draft
                'sales_id' => $oldPks->sales_id,
                'company_id' => $oldPks->company_id,
                'salary_rule_id' => $oldPks->salary_rule_id,
                'rule_thr_id' => $oldPks->rule_thr_id,
                'kategori_sesuai_hc_id' => $oldPks->kategori_sesuai_hc_id,
                'kategori_sesuai_hc' => $oldPks->kategori_sesuai_hc,
                'loyalty_id' => $oldPks->loyalty_id,
                'loyalty' => $oldPks->loyalty,
                'provinsi_id' => $oldPks->provinsi_id,
                'provinsi' => $oldPks->provinsi,
                'kota_id' => $oldPks->kota_id,
                'kota' => $oldPks->kota,
                'pma' => $oldPks->pma,
                'created_by' => Auth::user()->full_name ?? 'System'
            ]);
            
            Log::info('PKS baru dibuat', [
                'new_pks_id' => $newPks->id,
                'new_pks_no' => $newPks->nomor,
                'old_pks_id' => $oldPks->id,
                'linked_spk_id' => $newSpk?->id
            ]);
            
            return $newPks;
            
        } catch (\Exception $e) {
            Log::error('Error buat PKS baru: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Buat Site baru untuk PKS
     */
    private function createPksSites(Pks $oldPks, Pks $newPks, Spk $newSpk, Quotation $newQuot): int
    {
        $created = 0;
        
        // Ambil semua SpkSite dari SPK baru
        $spkSites = SpkSite::where('spk_id', $newSpk->id)
            ->whereNull('deleted_at')
            ->get();
        
        foreach ($spkSites as $spkSite) {
            try {
                // Cari apakah site ini ada di PKS lama
                $oldSite = Site::where('pks_id', $oldPks->id)
                    ->where('nama_site', $spkSite->nama_site)
                    ->where('provinsi', $spkSite->provinsi)
                    ->where('kota', $spkSite->kota)
                    ->first();
                
                // Generate nomor site
                $siteCount = Site::where('pks_id', $newPks->id)->count();
                $nomorSite = $newPks->nomor . '-' . sprintf("%04d", ($siteCount + 1));
                
                // Generate nama proyek
                $namaProyek = sprintf(
                    '%s-%s.%s.%s',
                    Carbon::parse($newPks->kontrak_awal)->format('my'),
                    Carbon::parse($newPks->kontrak_akhir)->format('my'),
                    strtoupper(substr($newPks->layanan ?? 'NN', 0, 2)),
                    strtoupper($newPks->nama_perusahaan)
                );
                
                // Buat Site baru - gunakan data lama jika ada, kalau tidak pakai SpkSite
                if ($oldSite) {
                    // Copy dari Site lama
                    Site::create([
                        'quotation_id' => $oldSite->quotation_id,
                        'spk_id' => $newSpk->id,
                        'pks_id' => $newPks->id,
                        'quotation_site_id' => $oldSite->quotation_site_id,
                        'spk_site_id' => $spkSite->id,
                        'leads_id' => $oldSite->leads_id,
                        'nomor' => $nomorSite,
                        'nomor_proyek' => $namaProyek,
                        'nama_proyek' => $namaProyek,
                        'nama_site' => $oldSite->nama_site,
                        'provinsi_id' => $oldSite->provinsi_id,
                        'provinsi' => $oldSite->provinsi,
                        'kota_id' => $oldSite->kota_id,
                        'kota' => $oldSite->kota,
                        'ump' => $oldSite->ump,
                        'umk' => $oldSite->umk,
                        'nominal_upah' => $oldSite->nominal_upah,
                        'penempatan' => $oldSite->penempatan,
                        'kebutuhan_id' => $oldSite->kebutuhan_id,
                        'kebutuhan' => $oldSite->kebutuhan,
                        'nomor_quotation' => $oldSite->nomor_quotation,
                        'created_by' => Auth::user()->full_name ?? 'System'
                    ]);
                } else {
                    // Copy dari SpkSite baru
                    Site::create([
                        'quotation_id' => $spkSite->quotation_id,
                        'spk_id' => $newSpk->id,
                        'pks_id' => $newPks->id,
                        'quotation_site_id' => $spkSite->quotation_site_id,
                        'spk_site_id' => $spkSite->id,
                        'leads_id' => $spkSite->leads_id,
                        'nomor' => $nomorSite,
                        'nomor_proyek' => $namaProyek,
                        'nama_proyek' => $namaProyek,
                        'nama_site' => $spkSite->nama_site,
                        'provinsi_id' => $spkSite->provinsi_id,
                        'provinsi' => $spkSite->provinsi,
                        'kota_id' => $spkSite->kota_id,
                        'kota' => $spkSite->kota,
                        'ump' => $spkSite->ump,
                        'umk' => $spkSite->umk,
                        'nominal_upah' => $spkSite->nominal_upah,
                        'penempatan' => $spkSite->penempatan,
                        'kebutuhan_id' => $spkSite->kebutuhan_id,
                        'kebutuhan' => $spkSite->kebutuhan,
                        'nomor_quotation' => $spkSite->nomor_quotation,
                        'created_by' => Auth::user()->full_name ?? 'System'
                    ]);
                }
                
                $created++;
                
            } catch (\Exception $e) {
                Log::error('Error buat Site PKS baru: ' . $e->getMessage());
            }
        }
        
        Log::info('PKS Sites dibuat', [
            'total_created' => $created,
            'new_pks_id' => $newPks->id
        ]);
        
        return $created;
    }

    /**
     * Buat Customer Activity untuk SPK (sama seperti di SpkController)
     */
    private function createSpkActivity(Spk $spk, $leads): void
    {
        $nomorActivity = $this->generateActivityNo($leads->id);
        $user = Auth::user();

        if ($user && in_array($user->cais_role_id, [29,30,31,32,33])) {
            // Untuk Sales, buat SalesActivity
            $this->createSalesActivity($spk, $leads);
        } else {
            // Untuk non-Sales, buat CustomerActivity
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'spk_id' => $spk->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $nomorActivity,
                'tipe' => 'SPK',
                'notes' => 'SPK dengan nomor : ' . $spk->nomor . ' terbentuk dari rekontrak',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name
            ]);
        }
    }

    /**
     * Buat Sales Activity untuk SPK (sama seperti di SpkController)
     */
    private function createSalesActivity(Spk $spk, $leads): void
    {
        $user = Auth::user();

        // Ambil semua kebutuhan yang diassign ke sales ini dari leads_kebutuhan
        $leadsKebutuhanList = LeadsKebutuhan::where('leads_id', $spk->leads_id)
            ->whereNotNull('tim_sales_d_id')
            ->get();

        // Buat SalesActivity untuk setiap kebutuhan yang diassign ke sales ini
        foreach ($leadsKebutuhanList as $leadsKebutuhan) {
            // Cek apakah kebutuhan ini ada di SPK sites
            $spkSiteExists = SpkSite::where('spk_id', $spk->id)
                ->where('kebutuhan_id', $leadsKebutuhan->kebutuhan_id)
                ->exists();

            if ($spkSiteExists) {
                SalesActivity::create([
                    'leads_id' => $spk->leads_id,
                    'leads_kebutuhan_id' => $leadsKebutuhan->id,
                    'tgl_activity' => Carbon::now(),
                    'jenis_activity' => 'spk',
                    'notulen' => "SPK baru {$spk->nomor} dibuat untuk kebutuhan {$leadsKebutuhan->kebutuhan->nama} dari rekontrak",
                    'created_by' => $user->full_name
                ]);
            }
        }
    }

    /**
     * Buat Customer Activity untuk PKS
     */
    private function createPksActivity(Pks $pks, $leads): void
    {
        $nomorActivity = $this->generateActivityNo($leads->id);
        $user = Auth::user();

        if ($user && in_array($user->cais_role_id, [29,30,31,32,33])) {
            // Untuk Sales, buat SalesActivity
            $this->createPksSalesActivity($pks, $leads);
        } else {
            // Untuk non-Sales, buat CustomerActivity
            CustomerActivity::create([
                'leads_id' => $leads->id,
                'pks_id' => $pks->id,
                'branch_id' => $leads->branch_id,
                'tgl_activity' => now(),
                'nomor' => $nomorActivity,
                'tipe' => 'PKS',
                'notes' => 'PKS dengan nomor : ' . $pks->nomor . ' terbentuk dari rekontrak',
                'is_activity' => 0,
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->full_name
            ]);
        }
    }

    /**
     * Buat Sales Activity untuk PKS
     */
    private function createPksSalesActivity(Pks $pks, $leads): void
    {
        $user = Auth::user();

        // Ambil semua kebutuhan yang diassign ke sales ini dari leads_kebutuhan
        $leadsKebutuhanList = LeadsKebutuhan::where('leads_id', $pks->leads_id)
            ->whereNotNull('tim_sales_d_id')
            ->get();

        // Buat SalesActivity untuk setiap kebutuhan yang diassign ke sales ini
        foreach ($leadsKebutuhanList as $leadsKebutuhan) {
            // Cek apakah kebutuhan ini ada di PKS
            if ($pks->layanan_id == $leadsKebutuhan->kebutuhan_id) {
                SalesActivity::create([
                    'leads_id' => $pks->leads_id,
                    'leads_kebutuhan_id' => $leadsKebutuhan->id,
                    'tgl_activity' => Carbon::now(),
                    'jenis_activity' => 'pks',
                    'notulen' => "PKS baru {$pks->nomor} dibuat untuk kebutuhan {$leadsKebutuhan->kebutuhan->nama} dari rekontrak",
                    'created_by' => $user->full_name
                ]);
            }
        }
    }

    /**
     * Buat activity untuk quotation
     */
    private function createQuotActivities(Quotation $oldQuot, Quotation $newQuot): void
    {
        $leads = $oldQuot->leads;
        
        if (!$leads) return;

        // Activity untuk quotation lama
        CustomerActivity::create([
            'leads_id' => $leads->id,
            'quotation_id' => $oldQuot->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNo($leads->id),
            'tipe' => 'Quotation',
            'notes' => 'Quotation ' . $oldQuot->nomor . ' dinonaktifkan karena rekontrak',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name ?? 'System'
        ]);

        // Activity untuk quotation baru
        CustomerActivity::create([
            'leads_id' => $leads->id,
            'quotation_id' => $newQuot->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $this->generateActivityNo($leads->id),
            'tipe' => 'Quotation',
            'notes' => 'Quotation ' . $newQuot->nomor . ' dibuat dari rekontrak ' . $oldQuot->nomor,
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name ?? 'System'
        ]);
    }

    /**
     * Generate nomor SPK
     */
    private function generateSpkNo($leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::whereNull('deleted_at')->find($leadsId);
        
        if (!$leads) {
            throw new \Exception("Leads tidak ditemukan");
        }

        $baseNo = "SPK/" . $leads->nomor . "-";
        $month = $now->month < 10 ? "0" . $now->month : $now->month;

        $count = Spk::where('nomor', 'like', $baseNo . $month . $now->year . "-%")->count();
        $seq = sprintf("%05d", $count + 1);

        return $baseNo . $month . $now->year . "-" . $seq;
    }

    /**
     * Generate nomor PKS
     */
    private function generatePksNo($leadsId, $companyId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $no = "PKS/";
        if ($companyId) {
            $company = \App\Models\Company::find($companyId);
            if ($company) {
                $no .= $company->code . "/";
                $no .= $leads->nomor . "-";
            } else {
                $no .= "NN/NNNNN-";
            }
        } else {
            $no .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $count = Pks::where('nomor', 'like', $no . $month . $now->year . "-%")->count();
        $seq = sprintf("%05d", $count + 1);

        return $no . $month . $now->year . "-" . $seq;
    }

    /**
     * Generate nomor activity
     */
    private function generateActivityNo($leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $prefix = "CAT/";
        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                1 => "SG/",
                2 => "LS/",
                3 => "CS/",
                4 => "LL/",
                default => "NN/"
            };
            $prefix .= $leads->nomor . "-";
        } else {
            $prefix .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . "-%")->count();
        $seq = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . "-" . $seq;
    }

    /**
     * Non-aktifkan quot lama
     */
    private function deactivateQuot(Quotation $oldQuot): void
    {
        $oldQuot->update([
            'is_aktif' => 0,
            'status_quotation_id' => 3, // Replaced/Expired
            'deleted_at' => Carbon::now(),
            'deleted_by' => Auth::user()->full_name ?? 'System',
            'updated_at' => Carbon::now(),
            'updated_by' => Auth::user()->full_name ?? 'System'
        ]);
    }
}