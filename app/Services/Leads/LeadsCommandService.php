<?php

namespace App\Services\Leads;

use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Models\Benua;
use App\Models\BidangPerusahaan;
use App\Models\City;
use App\Models\CustomerActivity;
use App\Models\District;
use App\Models\JenisPerusahaan;
use App\Models\Leads;
use App\Models\LeadsPic;
use App\Models\Negara;
use App\Models\Province;
use App\Models\Village;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadsCommandService
{
    public function __construct(
        private LeadsAssignSetupService $leadsAssignSetupService
    ) {}

    /**
     * Create lead (transactional) — assign sales berdasarkan role user.
     */
    public function createLead(StoreLeadRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $current_date_time = Carbon::now()->toDateTimeString();

            $provinsi = Province::find($request->provinsi);
            $kota = City::find($request->kota);
            $kecamatan = District::find($request->kecamatan);
            $kelurahan = Village::find($request->kelurahan);
            $benua = Benua::find($request->benua);
            $negara = Negara::find($request->negara);
            $jenisPerusahaan = JenisPerusahaan::find($request->jenis_perusahaan);
            $bidangPerusahaan = BidangPerusahaan::find($request->bidang_perusahaan);

            $nomor = $this->generateNomor();

            $pics = $request->input('pics', []);
            $firstPic = !empty($pics) ? $pics[0] : null;

            $lead = Leads::create([
                'nomor' => $nomor,
                'tgl_leads' => $current_date_time,
                'nama_perusahaan' => strtoupper($request->nama_perusahaan),
                'telp_perusahaan' => $request->telp_perusahaan,
                'jenis_perusahaan_id' => $request->jenis_perusahaan,
                'jenis_perusahaan' => $jenisPerusahaan ? $jenisPerusahaan->nama : null,
                'bentuk_usaha' => $request->bentuk_usaha,
                'bidang_perusahaan_id' => $request->bidang_perusahaan,
                'bidang_perusahaan' => $bidangPerusahaan ? $bidangPerusahaan->nama : null,
                'branch_id' => $request->branch,
                'platform_id' => $request->platform,
                'alamat' => $request->alamat_perusahaan,
                'pic' => $request->pic ?: ($firstPic['pic'] ?? null),
                'jabatan' => $request->jabatan_pic ?: ($firstPic['jabatan_pic'] ?? null),
                'no_telp' => $request->no_telp ?: ($firstPic['no_telp'] ?? null),
                'email' => $request->email ?: ($firstPic['email'] ?? null),
                'pma' => $request->pma,
                'status_leads_id' => 1,
                'notes' => $request->detail_leads,
                'provinsi_id' => $request->provinsi,
                'provinsi' => $provinsi ? $provinsi->name : null,
                'kota_id' => $request->kota,
                'kota' => $kota ? $kota->name : null,
                'kecamatan_id' => $request->kecamatan,
                'kecamatan' => $kecamatan ? $kecamatan->name : null,
                'kelurahan_id' => $request->kelurahan,
                'kelurahan' => $kelurahan ? $kelurahan->name : null,
                'benua_id' => $request->benua,
                'benua' => $benua ? $benua->nama_benua : null,
                'negara_id' => $request->negara,
                'negara' => $negara ? $negara->nama_negara : null,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id()
            ]);

            $this->syncLeadsPics(
                $lead,
                !empty($pics) ? $pics : [[
                    'pic'         => $request->pic,
                    'jabatan_pic' => $request->jabatan_pic,
                    'no_telp'     => $request->no_telp,
                    'email'       => $request->email,
                ]]
            );

            $assignmentResults = [];
            if (in_array(Auth::user()->cais_role_id, [29, 31, 32, 33])) {
                $assignmentResults = $this->leadsAssignSetupService->autoAssignSalesToKebutuhan($lead, $request->kebutuhan);
            } elseif ($request->has('assignments') && !empty($request->assignments)) {
                $assignmentResults = $this->leadsAssignSetupService->manualAssignSalesToKebutuhan($lead, $request->assignments);
            } else {
                $this->leadsAssignSetupService->syncKebutuhanTanpaSales($lead, $request->kebutuhan);
            }

            $nomorActivity = $this->generateNomorActivity($lead->id);
            $activityData = [
                'leads_id' => $lead->id,
                'branch_id' => $request->branch,
                'tgl_activity' => $current_date_time,
                'nomor' => $nomorActivity,
                'notes' => 'Leads Terbentuk' . (!empty($assignmentResults) ? ' dengan assignment sales' : ''),
                'tipe' => 'Leads',
                'status_leads_id' => 1,
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id()
            ];

            if ($lead->tim_sales_d_id) {
                $activityData['tim_sales_id'] = $lead->tim_sales_id;
                $activityData['tim_sales_d_id'] = $lead->tim_sales_d_id;
            }

            CustomerActivity::create($activityData);

            return [
                'lead' => $lead,
                'assignments' => $assignmentResults
            ];
        });
    }

    /**
     * Update lead (transactional).
     */
    public function updateLead(UpdateLeadRequest $request, Leads $lead): array
    {
        return DB::transaction(function () use ($request, $lead) {
            $current_date_time = Carbon::now()->toDateTimeString();

            $provinsi = Province::find($request->provinsi);
            $kota = City::find($request->kota);
            $kecamatan = District::find($request->kecamatan);
            $kelurahan = Village::find($request->kelurahan);
            $benua = Benua::find($request->benua);
            $negara = Negara::find($request->negara);
            $jenisPerusahaan = JenisPerusahaan::find($request->jenis_perusahaan);
            $bidangPerusahaan = BidangPerusahaan::find($request->bidang_perusahaan);

            $pics = $request->input('pics', []);
            $firstPic = !empty($pics) ? $pics[0] : null;

            $lead->update([
                'nama_perusahaan' => $request->nama_perusahaan ?? $lead->nama_perusahaan,
                'telp_perusahaan' => $request->telp_perusahaan,
                'jenis_perusahaan_id' => $request->jenis_perusahaan,
                'jenis_perusahaan' => $jenisPerusahaan ? $jenisPerusahaan->nama : null,
                'bentuk_usaha' => $request->bentuk_usaha,
                'bidang_perusahaan_id' => $request->bidang_perusahaan,
                'bidang_perusahaan' => $bidangPerusahaan ? $bidangPerusahaan->nama : null,
                'branch_id' => $request->branch,
                'platform_id' => $request->platform,
                'alamat' => $request->alamat_perusahaan,
                'pic' => $request->pic ?: ($firstPic['pic'] ?? null),
                'jabatan' => $request->jabatan_pic ?: ($firstPic['jabatan_pic'] ?? null),
                'no_telp' => $request->no_telp ?: ($firstPic['no_telp'] ?? null),
                'email' => $request->email ?: ($firstPic['email'] ?? null),
                'pma' => $request->pma,
                'notes' => $request->detail_leads,
                'provinsi_id' => $request->provinsi,
                'provinsi' => $provinsi ? $provinsi->name : null,
                'kota_id' => $request->kota,
                'kota' => $kota ? $kota->name : null,
                'kecamatan_id' => $request->kecamatan,
                'kecamatan' => $kecamatan ? $kecamatan->name : null,
                'kelurahan_id' => $request->kelurahan,
                'kelurahan' => $kelurahan ? $kelurahan->name : null,
                'benua_id' => $request->benua,
                'benua' => $benua ? $benua->nama_benua : null,
                'negara_id' => $request->negara,
                'negara' => $negara ? $negara->nama_negara : null,
                'tgl_leads' => Carbon::now()->toDateString(),
                'updated_by' => Auth::user()->full_name
            ]);

            if (!empty($pics)) {
                $this->syncLeadsPics($lead, $pics, true);
            }

            $assignmentResults = [];
            if (Auth::user()->cais_role_id == 29 && !$lead->tim_sales_d_id) {
                $assignmentResults = $this->leadsAssignSetupService->autoAssignSalesToKebutuhan($lead, $request->kebutuhan);
            } elseif ($request->has('assignments') && !empty($request->assignments)) {
                $assignmentResults = $this->leadsAssignSetupService->manualAssignSalesToKebutuhan($lead, $request->assignments);
            } else {
                $this->leadsAssignSetupService->syncKebutuhanDenganSalesExisting($lead, $request->kebutuhan);
            }

            $nomorActivity = $this->generateNomorActivity($lead->id);
            $activityData = [
                'leads_id' => $lead->id,
                'branch_id' => $request->branch,
                'tgl_activity' => $current_date_time,
                'nomor' => $nomorActivity,
                'notes' => 'Leads Terbentuk' . (!empty($assignmentResults) ? ' dengan assignment sales' : ''),
                'tipe' => 'Leads',
                'status_leads_id' => 1,
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id()
            ];

            if ($lead->tim_sales_d_id) {
                $activityData['tim_sales_id'] = $lead->tim_sales_id;
                $activityData['tim_sales_d_id'] = $lead->tim_sales_d_id;
            }

            CustomerActivity::create($activityData);

            return [
                'lead' => $lead,
                'assignments' => $assignmentResults
            ];
        });
    }

    private function syncLeadsPics(Leads $lead, ?array $pics, bool $replace = false): void
    {
        if (empty($pics)) {
            return;
        }

        if ($replace) {
            LeadsPic::where('leads_id', $lead->id)
                ->update(['deleted_by' => Auth::user()->full_name]);
            LeadsPic::where('leads_id', $lead->id)->delete();
        }

        foreach ($pics as $pic) {
            if (empty($pic['pic'])) {
                continue;
            }
            LeadsPic::create([
                'leads_id'   => $lead->id,
                'nama'       => $pic['pic'],
                'jabatan_id' => $pic['jabatan_pic'] ?? null,
                'no_telp'    => $pic['no_telp'] ?? null,
                'email'      => $pic['email'] ?? null,
                'is_kuasa'   => $pic['is_kuasa'] ?? false,
                'created_by' => Auth::user()->full_name,
            ]);
        }
    }

    private function generateNomor()
    {
        $lastLeads = Leads::latest('id')->first();

        if (!$lastLeads?->nomor) {
            return 'AAAAA';
        }

        $nomor = $lastLeads->nomor;
        $chars = str_split($nomor);

        for ($i = count($chars) - 1; $i >= 0; $i--) {
            $current = $chars[$i];

            if (is_numeric($current)) {
                if ($current < '9') {
                    $chars[$i] = (string) ($current + 1);
                    break;
                } else {
                    $chars[$i] = 'A';
                    break;
                }
            }

            if (ctype_alpha($current)) {
                if ($current < 'Z') {
                    $chars[$i] = chr(ord($current) + 1);
                    break;
                } else {
                    $chars[$i] = '0';
                    continue;
                }
            }
        }

        return str_pad(implode('', $chars), 5, 'A', STR_PAD_RIGHT);
    }

    private function generateNomorActivity($leadsId)
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
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return $prefix . $month . $year . "-" . $sequence;
    }
}
