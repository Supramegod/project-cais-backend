<?php

namespace App\Services;
use App\Models\Province;
use App\Models\City;
use App\Models\Ump;
use App\Models\Umk;
use App\Models\Company;
use App\Models\Kebutuhan;
use App\Models\Quotation;
use App\Models\Leads;
use App\Models\QuotationSite;
use App\Models\QuotationPic;
use App\Models\CustomerActivity;
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
            'provinsi_id' => $province->id,
            'provinsi' => $province->nama,
            'kota_id' => $city->id,
            'kota' => $city->nama,
            'ump' => $ump ? $ump->ump : 0,
            'umk' => $umk ? $umk->umk : 0,
            'penempatan' => $isMulti ? $request->penempatan_multi[$index] : $request->penempatan,
            'created_by' => $createdBy
        ]);
    }

    /**
     * Create initial PIC for quotation
     */
    public function createInitialPic(Quotation $quotation, Request $request, string $createdBy): void
    {
        $leads = Leads::findOrFail($request->perusahaan_id);

        QuotationPic::create([
            'quotation_id' => $quotation->id,
            'leads_id' => $quotation->leads_id,
            'nama' => $leads->pic,
            'jabatan_id' => $leads->jabatan_id,
            'jabatan' => $leads->jabatan,
            'no_telp' => $leads->no_telp,
            'email' => $leads->email,
            'created_by' => $createdBy
        ]);
    }

    /**
     * Create initial customer activity
     */
    public function createInitialActivity(Quotation $quotation, string $createdBy, int $userId): void
    {
        $leads = $quotation->leads;
        $nomorActivity = $this->generateActivityNomor($quotation->leads_id);

        CustomerActivity::create([
            'leads_id' => $quotation->leads_id,
            'quotation_id' => $quotation->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => Carbon::now(),
            'nomor' => $nomorActivity,
            'tipe' => 'Quotation',
            'notes' => 'Quotation dengan nomor :' . $quotation->nomor . ' terbentuk',
            'is_activity' => 0,
            'user_id' => $userId,
            'created_by' => $createdBy
        ]);
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
    public function generateNomorByType($leadsId, $companyId, $tipeQuotation = 'baru', $quotationReferensi = null): string
    {
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->format('m');

        $dataLeads = Leads::findOrFail($leadsId);
        $company = Company::find($companyId);

        // Base format: QUOT/[jenis jika ada]/[COMPANY_CODE]/[LEADS_NUMBER]-[MMYYYY]-[XXXXX]
        $base = "QUOT/";

        // Tambahkan jenis quotation untuk adendum dan rekontrak
        if ($tipeQuotation == 'adendum') {
            $base .= "AD/";
        } elseif ($tipeQuotation == 'rekontrak') {
            $base .= "RK/";
        }
        // Untuk baru, tidak ada tambahan jenis

        // Tambahkan company code dan leads number
        if ($company) {
            $base .= $company->code . "/" . $dataLeads->nomor . "-" . $month . $year . "-";
        } else {
            $base .= "NN/NNNNN-" . $month . $year . "-";
        }

        // Hitung counter berdasarkan tipe quotation dan bulan
        $counter = Quotation::where('tipe_quotation', $tipeQuotation)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $now->month)
            ->count() + 1;

        return $base . str_pad($counter, 5, '0', STR_PAD_LEFT);
    }
    /**
 * Duplikasi data dari quotation referensi
 */
public function duplicateQuotationData(Quotation $newQuotation, Quotation $referensiQuotation): void
{
    // Copy basic quotation data
    $newQuotation->update([
        'jenis_kontrak' => $referensiQuotation->jenis_kontrak,
        'mulai_kontrak' => $referensiQuotation->mulai_kontrak,
        'kontrak_selesai' => $referensiQuotation->kontrak_selesai,
        'tgl_penempatan' => $referensiQuotation->tgl_penempatan,
        'salary_rule_id' => $referensiQuotation->salary_rule_id,
        'top' => $referensiQuotation->top,
        'upah' => $referensiQuotation->upah,
        'nominal_upah' => $referensiQuotation->nominal_upah,
        'management_fee_id' => $referensiQuotation->management_fee_id,
        'persentase' => $referensiQuotation->persentase,
        // ... tambah field lain yang perlu di-copy
    ]);
}
    /**
     * Generate activity number
     */
    public function generateActivityNomor($leadsId): string
    {
        $now = Carbon::now();
        $month = $now->month < 10 ? "0" . $now->month : $now->month;
        $count = CustomerActivity::where('leads_id', $leadsId)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        return "ACT/" . $leadsId . "/" . $month . $now->year . "/" . sprintf("%04d", $count + 1);
    }

    /**
     * Validate step 2 data
     */
    public function validateStep2(Request $request): void
    {
        $validator = \Validator::make($request->all(), [
            'mulai_kontrak' => 'required|date',
            'kontrak_selesai' => 'required|date|after_or_equal:mulai_kontrak',
            'tgl_penempatan' => 'required|date',
            'top' => 'required|string',
            'salary_rule' => 'required|exists:m_salary_rule,id'
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        if ($request->tgl_penempatan < $request->mulai_kontrak) {
            throw new \Exception('Tanggal Penempatan tidak boleh kurang dari Kontrak Awal');
        }

        if ($request->tgl_penempatan > $request->kontrak_selesai) {
            throw new \Exception('Tanggal Penempatan tidak boleh lebih dari Kontrak Selesai');
        }
    }

    /**
     * Prepare cuti data for step 2
     */
    public function prepareCutiData(Request $request): array
    {
        $data = [];

        if ($request->ada_cuti == "Tidak Ada") {
            $data['cuti'] = "Tidak Ada";
            $data['gaji_saat_cuti'] = null;
            $data['prorate'] = null;
        } else {
            $data['cuti'] = implode(",", $request->cuti);

            if (in_array("Cuti Melahirkan", $request->cuti)) {
                if ($request->gaji_saat_cuti != "Prorate") {
                    $data['prorate'] = null;
                }
            } else {
                $data['gaji_saat_cuti'] = null;
                $data['prorate'] = null;
            }

            $data['hari_cuti_kematian'] = in_array("Cuti Kematian", $request->cuti) ? $request->hari_cuti_kematian : null;
            $data['hari_istri_melahirkan'] = in_array("Istri Melahirkan", $request->cuti) ? $request->hari_istri_melahirkan : null;
            $data['hari_cuti_menikah'] = in_array("Cuti Menikah", $request->cuti) ? $request->hari_cuti_menikah : null;
        }

        return $data;
    }

    /**
     * Calculate upah data for step 4
     */
    /**
     * Calculate upah data for step 4
     */
    public function calculateUpahData(Quotation $quotation, Request $request): array
    {
        $nominalUpah = 0;
        $hitunganUpah = "Per Bulan";

        if ($request->upah == "Custom") {
            $hitunganUpah = $request->hitungan_upah;
            $customUpah = str_replace(".", "", $request->custom_upah);

            if ($hitunganUpah == "Per Hari") {
                $customUpah = $customUpah * 21;
            } else if ($hitunganUpah == "Per Jam") {
                $customUpah = $customUpah * 21 * 8;
            }

            $nominalUpah = $customUpah;
        } else {
            // Update nominal upah di semua site berdasarkan UMP/UMK menggunakan Model
            foreach ($quotation->quotationSites as $site) {
                if ($request->upah == "UMP") {
                    $dataUmp = Ump::where('province_id', $site->provinsi_id)
                        ->active()
                        ->first();
                    $nominalUpah = $dataUmp ? $dataUmp->ump : 0;
                } else if ($request->upah == "UMK") {
                    $dataUmk = Umk::where('city_id', $site->kota_id)
                        ->active()
                        ->first();
                    $nominalUpah = $dataUmk ? $dataUmk->umk : 0;
                }

                $site->update([
                    'nominal_upah' => $nominalUpah,
                    'updated_by' => $request->user()->full_name
                ]);
            }
        }

        return [
            'nominal_upah' => $nominalUpah,
            'hitungan_upah' => $hitunganUpah
        ];
    }

    /**
     * Prepare company data for step 5
     */
    public function prepareCompanyData(Request $request): array
    {
        $data = [
            'jenis_perusahaan_id' => $request->jenis_perusahaan,
            'bidang_perusahaan_id' => $request->bidang_perusahaan,
            'resiko' => $request->resiko
        ];

        if ($request->jenis_perusahaan) {
            $jenisPerusahaan = DB::table('m_jenis_perusahaan')->where('id', $request->jenis_perusahaan)->first();
            $data['jenis_perusahaan'] = $jenisPerusahaan ? $jenisPerusahaan->nama : null;
        }

        if ($request->bidang_perusahaan) {
            $bidangPerusahaan = DB::table('m_bidang_perusahaan')->where('id', $request->bidang_perusahaan)->first();
            $data['bidang_perusahaan'] = $bidangPerusahaan ? $bidangPerusahaan->nama : null;
        }

        return $data;
    }

    /**
     * Calculate final status for quotation
     */
    public function calculateFinalStatus(Quotation $quotation): array
    {
        $isAktif = 1;
        $statusQuotation = 3;

        // Business logic untuk menentukan status final
        if ($quotation->top == "Lebih Dari 7 Hari") {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        if ($quotation->persentase < 7) {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        if ($quotation->company_id == 17) { // PT ION
            $isAktif = 0;
            $statusQuotation = 2;
        }

        if ($quotation->jenis_kontrak == "Reguler" && $quotation->kompensasi == "Tidak Ada") {
            $isAktif = 0;
            $statusQuotation = 2;
        }

        return [
            'is_aktif' => $isAktif,
            'status_quotation_id' => $statusQuotation
        ];
    }
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
}