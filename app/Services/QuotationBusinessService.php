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
use App\Models\Umsk;
use App\Models\User;
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
        if ($request->jumlah_site == 'Multi Site') {
            foreach ($request->multisite as $key => $value) {
                $this->createQuotationSite($quotation, $request, $key, true, $createdBy);
            }
        } else {
            $this->createQuotationSite($quotation, $request, null, false, $createdBy);
        }
    }

    /**
     * Create single quotation site
     */
    public function createQuotationSite(Quotation $quotation, Request $request, ?int $index, bool $isMulti, string $createdBy): void
    {
        $provinceId = $isMulti ? $request->provinsi_multi[$index] : $request->provinsi;
        $cityId = $isMulti ? $request->kota_multi[$index] : $request->kota;

        $province = Province::findOrFail($provinceId);
        $city = City::findOrFail($cityId);

        $ump = Ump::where('province_id', $province->id)->active()->first();
        $umk = Umk::where('city_id', $city->id)->active()->first();
        $umsk = Umsk::where('city_id', $city->id)->active()->first();

        QuotationSite::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'nama_site' => $isMulti ? $request->multisite[$index] : $request->nama_site,
            'provinsi_id' => $provinceId,
            'provinsi' => $province->name,
            'kota_id' => $cityId,
            'kota' => $city->name,
            'ump' => $ump ? $ump->ump : 0,
            'umk' => $umk ? $umk->umk : 0,
            'umsk' => $umsk ? $umsk->umsk : 0,
            'penempatan' => $isMulti ? $request->penempatan_multi[$index] : $request->penempatan,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Create quotation site from a reference site (copy nilai UMP/UMK terbaru)
     */
    public function createQuotationSiteFromReference(Quotation $quotation, QuotationSite $refSite, string $createdBy): QuotationSite
    {
        $province = Province::findOrFail($refSite->provinsi_id);
        $city = City::findOrFail($refSite->kota_id);

        $ump = Ump::where('province_id', $province->id)->active()->first();
        $umk = Umk::where('city_id', $city->id)->active()->first();
        $umsk = Umsk::where('city_id', $city->id)->active()->first();

        return QuotationSite::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'nama_site' => $refSite->nama_site,
            'provinsi_id' => $refSite->provinsi_id,
            'provinsi' => $province->name,
            'kota_id' => $refSite->kota_id,
            'kota' => $city->name,
            'ump' => $ump ? $ump->ump : 0,
            'umk' => $umk ? $umk->umk : 0,
            'umsk' => $umsk ? $umsk->umsk : 0,
            'penempatan' => $refSite->penempatan,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Create initial PIC dari data leads
     */
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
            ]);
        }
    }


    public function createInitialActivity(
        Quotation $quotation,
        string $createdBy,
        int $userId,
        string $tipe = 'baru',
        ?Quotation $quotationReferensi = null,
        $user = null
    ): void {
        // ✅ Gunakan user yang dipasskan. Fallback ke Auth::user() hanya untuk konteks non-queue.
        $user = $user ?? Auth::user() ?? User::find($userId);

        if (!$user) {
            \Log::error('createInitialActivity: user tidak ditemukan', ['user_id' => $userId]);
            return;
        }

        $leads = $quotation->leads;
        $nomorActivity = $this->generateActivityNomor($quotation->leads_id);
        $notes = $this->generateActivityNotes($quotation, $tipe, $quotationReferensi);

        // Sales role IDs: 29, 30, 31, 32, 33
        if (in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            $this->createSalesActivity($quotation, $createdBy, $user);
        } else {
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
                'created_by' => $createdBy,
            ]);
        }
    }

    /**
     * ✅ FIX: Menerima $user object eksplisit — tidak lagi bergantung pada Auth::user().
     */
    private function createSalesActivity(Quotation $quotation, string $createdBy, $user): void
    {
        $leadsKebutuhan = LeadsKebutuhan::where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->where('tim_sales_d_id', $user->id)
            ->first();

        SalesActivity::create([
            'leads_id' => $quotation->leads_id,
            'leads_kebutuhan_id' => $leadsKebutuhan?->id,
            'tgl_activity' => Carbon::now(),
            'jenis_activity' => 'Quotation',
            'notulen' => "Quotation baru {$quotation->nomor} dibuat untuk kebutuhan {$quotation->kebutuhan}",
            'created_by' => $createdBy,
        ]);
    }

    private function generateActivityNotes(Quotation $quotation, string $tipe, ?Quotation $quotationReferensi): string
    {
        return match ($tipe) {
            'revisi' => "Quotation revisi {$quotation->nomor} dibuat dari referensi {$quotationReferensi->nomor}",
            'rekontrak' => "Quotation rekontrak {$quotation->nomor} dibuat dari kontrak sebelumnya {$quotationReferensi->nomor}",
            'addendum' => "Quotation addendum {$quotation->nomor} dibuat dari referensi {$quotationReferensi->nomor}",
            'baru_dengan_referensi' => "Quotation baru {$quotation->nomor} dibuat menggunakan data dari Quotation {$quotationReferensi->nomor}",
            default => "Quotation baru {$quotation->nomor} dibuat dari awal",
        };
    }

    private function getActivityType(string $tipe): string
    {
        return match ($tipe) {
            'revisi' => 'Quotation Revisi',
            'rekontrak' => 'Quotation Rekontrak',
            'addendum' => 'Quotation addendum',
            'baru_dengan_referensi' => 'Quotation copy',
            default => 'Quotation',
        };
    }

    /**
     * Soft delete semua relasi quotation
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
            'quotationSites',
        ];

        foreach ($relations as $relation) {
            if ($quotation->$relation()->exists()) {
                $quotation->$relation()->update([
                    'deleted_at' => Carbon::now(),
                    'deleted_by' => $deletedBy,
                ]);
            }
        }
        if (!$quotation->deleted_at) {
            $quotation->deleted_at = Carbon::now(); // ← pakai assignment langsung, bukan update()
            $quotation->deleted_by = $deletedBy;
            $quotation->save();
        }
    }

    /**
     * Generate quotation number (legacy — gunakan generateNomorByType untuk kasus baru)
     */
    public function generateNomor(int $leadsId, int $companyId): string
    {
        $now = Carbon::now();
        $nomor = 'QUOT/';
        $dataLeads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);

        if ($company) {
            $nomor .= $company->code . '/' . $dataLeads->nomor . '-';
        } else {
            $nomor .= 'NN/NNNNN-';
        }

        $month = $now->format('m');
        $jumlah = Quotation::where('nomor', 'like', $nomor . $month . $now->year . '-%')->count();
        $urutan = str_pad($jumlah + 1, 5, '0', STR_PAD_LEFT);

        return $nomor . $month . $now->year . '-' . $urutan;
    }

    /**
     * Generate nomor quotation berdasarkan tipe
     */
    public function generateNomorByType(int $leadsId, int $companyId, string $tipeQuotation, ?Quotation $quotationReferensi = null): string
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->format('m');

        // Addendum: prefix ADD/<nomor_ref>/<counter>
        if ($tipeQuotation === 'addendum' && $quotationReferensi) {
            $nomorRef = $quotationReferensi->nomor;
            $counter = Quotation::where('tipe_quotation', 'addendum')
                ->where('nomor', 'like', "ADD/{$nomorRef}/%")
                ->count() + 1;

            return 'ADD/' . $nomorRef . '/' . str_pad($counter, 5, '0', STR_PAD_LEFT);
        }

        // Standar: QUOT/<code>/<nomor_leads>-<bulan><tahun>-<urutan>
        $dataLeads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);

        $base = 'QUOT/';
        $base .= $company
            ? $company->code . '/' . $dataLeads->nomor . '-' . $month . $year . '-'
            : 'NN/NNNNN-' . $month . $year . '-';

        $counter = Quotation::where('tipe_quotation', $tipeQuotation)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $now->month)
            ->count() + 1;

        return $base . str_pad($counter, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate activity nomor
     */
    public function generateActivityNomor(int $leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);
        $prefix = 'CAT/';

        if ($leads) {
            $prefix .= match ($leads->kebutuhan_id) {
                2 => 'LS/',
                1 => 'SG/',
                3 => 'CS/',
                4 => 'LL/',
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

    /**
     * Validate multi site data consistency
     */
    public function validateMultiSiteData(Request $request): void
    {
        if ($request->jumlah_site == 'Multi Site') {
            $counts = [
                count($request->multisite ?? []),
                count($request->provinsi_multi ?? []),
                count($request->kota_multi ?? []),
                count($request->penempatan_multi ?? []),
            ];

            if (count(array_unique($counts)) > 1) {
                throw new \Exception('Jumlah data multisite, provinsi, kota, dan penempatan harus sama');
            }
        }
    }
}