<?php

namespace App\Services;
use App\Models\LeadsKebutuhan;
use App\Models\Pks;
use App\Models\Province;
use App\Models\City;
use App\Models\SalesActivity;
use App\Models\Ump;
use App\Models\Umk;
use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\Quotation;
use App\Models\Leads;
use App\Models\QuotationSite;
use App\Models\QuotationPic;
use App\Models\CustomerActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class QuotationBusinessService
{
    /**
     * Prepare quotation data for creation
     */
    public function prepareQuotationData(Request $request): array
    {
        $leads = Leads::findOrFail($request->perusahaan_id);
        $kebutuhan = Kebutuhan::findOrFail($request->layanan);
        $company = Company::findOrFail($request->entitas);

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

    /**
     * Create quotation sites based on request
     */
    public function createQuotationSites(Quotation $quotation, Request $request, string $createdBy): void
    {
        if ($request->jumlah_site == "Multi Site") {
            foreach ($request->multisite as $key => $value) { // Nama site
                $this->createQuotationSite($quotation, $request, $key, true, $createdBy);
            }
        } else {
            $this->createQuotationSite($quotation, $request, null, false, $createdBy); // Single site
        }
    }

    /**
     * Create single quotation site
     */
    public function createQuotationSite(Quotation $quotation, Request $request, ?int $index, bool $isMulti, string $createdBy): void
    {
        $provinceId = $isMulti ? $request->provinsi_multi[$index] : $request->provinsi;
        $cityId = $isMulti ? $request->kota_multi[$index] : $request->kota;

        // Menggunakan Model Eloquent
        $province = Province::findOrFail($provinceId);
        $city = City::findOrFail($cityId);

        // Menggunakan scope yang sudah didefinisikan di model
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
    }
    // Di QuotationBusinessService
    public function createQuotationSiteFromReference(Quotation $quotation, QuotationSite $refSite, string $createdBy)
    {
        // Ambil UMP & UMK berdasarkan provinsi & kota dari refSite
        $province = Province::findOrFail($refSite->provinsi_id);
        $city = City::findOrFail($refSite->kota_id);

        $ump = Ump::where('province_id', $province->id)
            ->active()
            ->first();

        $umk = Umk::where('city_id', $city->id)
            ->active()
            ->first();

        return QuotationSite::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'nama_site' => $refSite->nama_site,
            'provinsi_id' => $refSite->provinsi_id,
            'provinsi' => $province->nama,
            'kota_id' => $refSite->kota_id,
            'kota' => $city->name,
            'ump' => $ump ? $ump->ump : 0,
            'umk' => $umk ? $umk->umk : 0, // ✅ UMK TERBARU
            'penempatan' => $refSite->penempatan,
            'created_by' => $createdBy
        ]);
    }

    // QuotationBusinessService.php
    /**
     * Create initial PIC for quotation
     */
    // QuotationBusinessService.php
    /**
     * Create initial PIC for quotation
     */
    // QuotationBusinessService.php - Perbaiki method createInitialPic
    public function createInitialPic(Quotation $quotation, string $createdBy): void
    {
        try {
            // Get leads data
            $leads = $quotation->leads;

            if (!$leads) {
                \Log::warning('Leads not found for quotation', [
                    'quotation_id' => $quotation->id
                ]);
                return;
            }

            // Create PIC from leads contact person
            if ($leads->pic || $leads->email || $leads->telp_perusahaan) {
                $quotation->quotationPics()->create([
                    'leads_id' => $leads->id,
                    'nama' => $leads->pic ?? 'Unknown',
                    'jabatan' => $leads->jabatan ?? 'Contact Person',
                    'email' => $leads->email ?? '',
                    'no_hp' => $leads->telp_perusahaan ?? '',
                    'created_by' => $createdBy
                ]);

                \Log::info('Initial PIC created from leads', [
                    'quotation_id' => $quotation->id,
                    'pic_name' => $leads->pic
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to create initial PIC', [
                'quotation_id' => $quotation->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw, just log the error
        }
    }
    public function createInitialActivity(Quotation $quotation, string $createdBy, int $userId, string $tipe = 'baru', ?Quotation $quotationReferensi = null): void
    {
        $leads = $quotation->leads;
        $nomorActivity = $this->generateActivityNomor($quotation->leads_id);

        // Buat notes berdasarkan tipe quotation
        $notes = $this->generateActivityNotes($quotation, $tipe, $quotationReferensi);

        // ✅ Cek role user - jika Sales (role 29), buat SalesActivity
        $user = Auth::user();

        if ($user && in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            // Untuk Sales, buat SalesActivity dengan tipe baru tanpa referensi
            $this->createSalesActivity($quotation, $createdBy);
        } else {
            // Untuk role lain, buat CustomerActivity seperti biasa
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
                'created_by' => $createdBy
            ]);
        }
    }

    /**
     * Create sales activity for quotation
     */
    private function createSalesActivity(Quotation $quotation, string $createdBy): void
    {
        $user = Auth::user();

        // Cari leads_kebutuhan_id berdasarkan leads_id dan kebutuhan_id dari quotation
        $leadsKebutuhan = LeadsKebutuhan::where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->where('tim_sales_d_id', $user->id) // Filter berdasarkan sales yang login
            ->first();

        SalesActivity::create([
            'leads_id' => $quotation->leads_id,
            'leads_kebutuhan_id' => $leadsKebutuhan ? $leadsKebutuhan->id : null,
            'tgl_activity' => Carbon::now(),
            'jenis_activity' => 'Quotation',
            'notulen' => "Quotation baru {$quotation->nomor} dibuat untuk kebutuhan {$quotation->kebutuhan}",
            'created_by' => $createdBy
        ]);
    }

    /**
     * Generate activity notes based on quotation type
     */
    private function generateActivityNotes(Quotation $quotation, string $tipe, ?Quotation $quotationReferensi): string
    {
        switch ($tipe) {
            case 'revisi':
                return "Quotation revisi {$quotation->nomor} dibuat dari referensi {$quotationReferensi->nomor}";
            case 'rekontrak':
                return "Quotation rekontrak {$quotation->nomor} dibuat dari kontrak sebelumnya {$quotationReferensi->nomor}";
            case 'addendum':
                return "Quotation addendum {$quotation->nomor} dibuat dari referensi {$quotationReferensi->nomor}";
            case 'baru_dengan_referensi':
                return "Quotation baru {$quotation->nomor} dibuat menggunakan data dari Quotation {$quotationReferensi->nomor}";
            default: // 'baru'
                return "Quotation baru {$quotation->nomor} dibuat dari awal";
        }
    }
    /**
     * Get activity type based on quotation type
     */
    private function getActivityType(string $tipe): string
    {
        switch ($tipe) {
            case 'revisi':
                return 'Quotation Revisi';
            case 'rekontrak':
                return 'Quotation Rekontrak';
            case 'addendum':
                return 'Quotation addendum';
            case 'baru_dengan_referensi':
                return 'Quotation copy';
            default: // 'baru'
                return 'Quotation';
        }
    }

    /**
     * Soft delete quotation relations
     */
    public function softDeleteQuotationRelations(Quotation $quotation, string $deletedBy): void
    {
        $relations = [
            'quotationAplikasis',
            'quotationDevices',
            'quotationKaporlaps',
            'quotationChemicals',
            'quotationOhcs',
            'quotationDetails',
            'quotationDetailRequirements',
            'quotationDetailHpps',
            'quotationDetailCosses',
            'quotationDetailTunjangans',
            'quotationPics',
            'quotationTrainings',
            'quotationKerjasamas',
            'quotationSites'
        ];

        foreach ($relations as $relation) {
            if ($quotation->$relation()->exists()) {
                $quotation->$relation()->update([
                    'deleted_at' => Carbon::now(),
                    'deleted_by' => $deletedBy
                ]);
            }
        }
    }
    /**
     * Generate quotation number
     */
    public function generateNomor($leadsId, $companyId): string
    {
        $now = Carbon::now();
        $nomor = "QUOT/";

        $dataLeads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId); // Menggunakan model Company

        if ($company) {
            $nomor .= $company->code . "/";
            $nomor .= $dataLeads->nomor . "-";
        } else {
            $nomor .= "NN/NNNNN-";
        }

        $month = $now->month < 10 ? "0" . $now->month : $now->month;
        $urutan = "00001";

        $jumlahData = Quotation::where('nomor', 'like', $nomor . $month . $now->year . "-%")->count();
        $urutan = sprintf("%05d", $jumlahData + 1);

        return $nomor . $month . $now->year . "-" . $urutan;
    }
    /**
     * Generate nomor quotation berdasarkan jenis
     */
    public function generateNomorByType($leadsId, $companyId, $tipeQuotation, $quotationReferensi = null): string
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->format('m');

        // 1. Logic Khusus untuk Addendum
        if ($tipeQuotation === 'addendum' && $quotationReferensi) {
            // Ambil nomor referensi (contoh: QUOT/ABC/001-022026-00001)
            $nomorRef = $quotationReferensi->nomor;

            // Hitung sudah berapa kali quotation ini di-addendum
            $counter = Quotation::where('tipe_quotation', 'addendum')
                ->where('nomor', 'like', "ADD/{$nomorRef}/%")
                ->count() + 1;

            return "ADD/" . $nomorRef . "/" . str_pad($counter, 5, '0', STR_PAD_LEFT);
        }

        // 2. Logic Standar untuk tipe lain (Baru, Revisi, Rekontrak)
        $dataLeads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);

        $base = "QUOT/";
        if ($company) {
            $base .= $company->code . "/" . $dataLeads->nomor . "-" . $month . $year . "-";
        } else {
            $base .= "NN/NNNNN-" . $month . $year . "-";
        }

        $counter = Quotation::where('tipe_quotation', $tipeQuotation)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $now->month)
            ->count() + 1;

        return $base . str_pad($counter, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate activity number
     */
    public function generateActivityNomor($leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $prefix = "CAT/";
        if ($leads) {
            switch ($leads->kebutuhan_id) {
                case 2:
                    $prefix .= "LS/";
                    break;
                case 1:
                    $prefix .= "SG/";
                    break;
                case 3:
                    $prefix .= "CS/";
                    break;
                case 4:
                    $prefix .= "LL/";
                    break;
                default:
                    $prefix .= "NN/";
                    break;
            }
            $prefix .= $leads->nomor . "-";
        } else {
            $prefix .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $year = $now->year;

        $count = CustomerActivity::where('nomor', 'like', $prefix . $month . $year . "-%")->count();
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . "-" . $sequence;
    }
    /**
     * Validate multi site data consistency
     */
    public function validateMultiSiteData(Request $request): void
    {
        if ($request->jumlah_site == 'Multi Site') {
            $siteCount = count($request->multisite ?? []);
            $provinceCount = count($request->provinsi_multi ?? []);
            $cityCount = count($request->kota_multi ?? []);
            $placementCount = count($request->penempatan_multi ?? []);

            if ($siteCount !== $provinceCount || $siteCount !== $cityCount || $siteCount !== $placementCount) {
                throw new \Exception('Jumlah data multisite, provinsi, kota, dan penempatan harus sama');
            }
        }
    }
    /**
     * Get filtered quotations based on type
     */
    public function getFilteredQuotations(string $leadsId, string $tipeQuotation)
    {
        $query = Quotation::with(['statusQuotation', 'pks', 'quotationSites'])
            ->withoutTrashed();

        switch ($tipeQuotation) {
            case 'baru':
                $query
                    // Bukan revisi
                    ->where('leads_id', $leadsId)
                    ->whereIn('status_quotation_id', [1, 2, 4, 5, 8])
                    // Bukan rekontrak (tidak punya PKS aktif yang akan berakhir ≤ 3 bulan)
                    ->whereDoesntHave('pks', function ($q) {
                        $q->where('is_aktif', 1)
                            ->whereBetween('kontrak_akhir', [now(), now()->addMonths(3)]);
                    });
                break;

            case 'revisi':
                $query->where('leads_id', $leadsId)
                    ->whereIn('status_quotation_id', [1, 2, 4, 5, 8]);

                break;
            case 'addendum':
                $query->where('leads_id', $leadsId)
                    ->whereIn('status_quotation_id', [1, 2, 4, 5, 8]);
                // ->where(function ($q) {
                //     $q->whereHas('sites', function ($siteQuery) {
                //         $siteQuery->whereHas('pks', function ($pksQuery) {
                //             $pksQuery->where('is_aktif', 1)
                //                 ->whereBetween('kontrak_akhir', [now(), now()->addMonths(11)]);
                //         });
                //     })
                //     ->orWhereHas('sites', function ($siteQuery) {
                //         $siteQuery->whereNull('pks_id');
                //     });
                // });
                break;

            case 'rekontrak':
                $query->where('leads_id', $leadsId)
                    ->where(function ($q) {
                        $q->where('status_quotation_id', 3)
                            ->orWhereNotNull('ot1');
                    });
                // ->whereHas('sites', function ($siteQuery) {
                //     $siteQuery->whereHas('pks', function ($pksQuery) {
                //         $pksQuery->where('is_aktif', 1)
                //             ->whereBetween('kontrak_akhir', [now(), now()->addMonths(3)]);
                //     });
                // });
                break;
        }

        return $query->latest('created_at')
            ->get()
            ->map(fn(Quotation $quotation) => $this->formatQuotationData($quotation, $tipeQuotation));
    }

    /**
     * Format quotation data for response
     */
    public function formatQuotationData(Quotation $quotation, string $tipeQuotation): array
    {
        $data = [
            'id' => $quotation->id,
            'nomor' => $quotation->nomor,
            'nama_perusahaan' => $quotation->nama_perusahaan,
            'mulai_kontrak' => $quotation->mulai_kontrak,
            'kontrak_selesai' => $quotation->kontrak_selesai,
            'tgl_quotation' => $quotation->tgl_quotation,
            'kebutuhan_id' => $quotation->kebutuhan_id,
            'jumlah_site' => $quotation->jumlah_site,
            'step' => $quotation->step,
            'is_aktif' => $quotation->is_aktif,
            'status_quotation_id' => $quotation->status_quotation_id,
            'status_quotation' => $quotation->statusQuotation->nama ?? 'Unknown',
            'tipe_quotation' => $quotation->tipe_quotation,
            'kebutuhan' => $quotation->kebutuhan,
            'company' => $quotation->company,
            'source' => 'quotation',
            // Menampilkan semua site dalam bentuk array
            'sites' => $quotation->quotationSites->map(function ($site) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,
                    'provinsi_id' => $site->provinsi_id,
                    'kota_id' => $site->kota_id,
                    'ump' => $site->ump,
                    'umk' => $site->umk
                ];
            })->toArray()
        ];

        // Untuk kompatibilitas dengan kode yang sudah ada, tetap sertakan site pertama
        // (opsional, bisa dihapus jika tidak diperlukan)
        $data['site'] = $quotation->quotationSites->first()->nama_site ?? null;

        // Add PKS data for recontract
        if ($tipeQuotation === 'rekontrak' && $quotation->pks) {
            $data['pks_data'] = [
                'id' => $quotation->pks->id,
                'nomor' => $quotation->pks->nomor,
                'tgl_pks' => $quotation->pks->tgl_pks,
                'kontrak_awal' => $quotation->pks->kontrak_awal,
                'kontrak_akhir' => $quotation->pks->kontrak_akhir,
                'is_aktif' => $quotation->pks->is_aktif
            ];
        }

        return $data;
    }


}