<?php

namespace App\Services\Leads;

use App\Models\CustomerActivity;
use App\Models\Leads;
use App\Models\Pks;
use App\Models\Quotation;
use App\Models\SalesActivity;
use App\Models\Spk;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadsRelationService
{
    /**
     * Get SPK by lead id.
     */
    public function getSpkByLead(int $id): array
    {
        $spkData = Spk::with('statusSpk')
            ->byLeadsId($id)
            ->select('id', 'nomor', 'leads_id', 'tgl_spk', 'status_spk_id')
            ->orderBy('tgl_spk', 'desc')
            ->get();

        $spkData = $spkData->map(function ($item) {
            $data = $item->toArray();
            $data['nama_status'] = $item->nama_status;
            unset($data['status_spk']);
            return $data;
        });

        return [
            'data' => $spkData,
            'summary' => Spk::getSummaryByLeadsId($id),
        ];
    }

    /**
     * Get PKS by lead id.
     */
    public function getPksByLead(int $id): array
    {
        $pksData = Pks::with('leads')
            ->byLeadsId($id)
            ->select('id', 'nomor', 'leads_id', 'tgl_pks', 'status_pks_id', 'kontrak_akhir')
            ->orderBy('tgl_pks', 'desc')
            ->get();

        $pksData = $pksData->map(function ($item) {
            $data = $item->toArray();
            $data['nama_status'] = $item->nama_status;
            $data['sisa_kontrak'] = $this->hitungBerakhirKontrak($item->kontrak_akhir);
            unset($data['leads']);
            return $data;
        });

        return [
            'data' => $pksData,
            'summary' => Pks::getSummaryByLeadsId($id),
        ];
    }

    /**
     * Get customer activities by lead id.
     */
    public function getCustomerActivitiesByLead(int $id)
    {
        $customerActivities = CustomerActivity::with('leads')
            ->byLeadsId($id)
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($act) {
                $baseData = [
                    'id' => $act->id,
                    'source' => 'Customer Activity',
                    'tipe' => $act->tipe,
                    'notes' => $act->notes_tipe ?? $act->notes ?? $act->notulen,
                    'tgl_activity' => $act->tgl_activity,
                    'created_by' => $act->created_by,
                    'created_at' => $act->getRawOriginal('created_at'),
                ];

                if (in_array(strtolower($act->tipe), ['telepon', 'online meeting'])) {
                    $baseData['start'] = $act->start;
                    $baseData['end'] = $act->end;
                    $baseData['durasi'] = $act->durasi;
                    $baseData['tgl_realisasi'] = $act->tgl_realisasi;
                } elseif (strtolower($act->tipe) === 'visit') {
                    $baseData['tgl_realisasi'] = $act->tgl_realisasi;
                    $baseData['jam_realisasi'] = $act->jam_realisasi;
                    $baseData['jenis_visit'] = $act->jenis_visit;
                }

                return $baseData;
            });

        $salesActivities = SalesActivity::with(['lead', 'leadsKebutuhan.kebutuhan'])
            ->where('leads_id', $id)
            ->get()
            ->map(function ($act) {
                return [
                    'id' => $act->id,
                    'source' => 'Sales Activity',
                    'tipe' => $act->jenis_activity,
                    'notes' => $act->notulen,
                    'tgl_activity' => $act->tgl_activity,
                    'created_by' => $act->created_by,
                    'created_at' => $act->getRawOriginal('created_at'),
                    'kebutuhan' => $act->leadsKebutuhan && $act->leadsKebutuhan->kebutuhan
                        ? $act->leadsKebutuhan->kebutuhan->nama
                        : null,
                ];
            });

        $allActivities = $customerActivities->merge($salesActivities);
        $allActivities = $allActivities->sortByDesc(function ($activity) {
            // Customer Activity pakai tgl_realisasi (kalau ada, mis. telepon/online
            // meeting/visit); Sales Activity selalu pakai tgl_activity.
            $tanggal = $activity['source'] === 'Customer Activity'
                ? ($activity['tgl_realisasi'] ?? $activity['tgl_activity'])
                : $activity['tgl_activity'];

            return Carbon::parse($tanggal);
        })->values(); // Reset array keys

        return $allActivities;
    }

    /**
     * Get quotations by lead id.
     */
    public function getQuotationsByLead(int $id): array
    {
        $quotations = Quotation::with('statusQuotation:id,nama')
            ->where('leads_id', $id)
            ->select([
                'id',
                'nomor',
                'leads_id',
                'tgl_quotation',
                'revisi',
                'is_aktif',
                'status_quotation_id',
                'kebutuhan_id',
                'layanan',
                'mulai_kontrak',
                'kontrak_selesai',
                'tipe_quotation',
                'created_by',
            ])
            ->orderBy('tgl_quotation', 'desc')
            ->get();

        $now = Carbon::now();

        $summary = [
            'total' => $quotations->count(),
            'aktif' => $quotations->where('is_aktif', 1)->count(),
            'tidak_aktif' => $quotations->where('is_aktif', 0)->count(),
            'per_tipe' => $quotations->groupBy('tipe_quotation')->map->count(),
        ];

        $data = $quotations->map(function ($item) use ($now) {
            return [
                'id' => $item->id,
                'nomor' => $item->nomor,
                'tgl_quotation' => $item->getRawOriginal('tgl_quotation')
                    ? Carbon::parse($item->getRawOriginal('tgl_quotation'))->isoFormat('D MMMM Y')
                    : null,
                'tipe_quotation' => $item->tipe_quotation,
                'is_aktif' => $item->is_aktif,
                'status_quotation_id' => $item->status_quotation_id,
                'nama_status' => $item->statusQuotation->nama ?? null,
                'created_by' => $item->created_by,
            ];
        });

        return [
            'data' => $data,
            'summary' => $summary,
        ];
    }

    /**
     * Create child lead di bawah parent lead (transactional).
     */
    public function saveChildLeads(Request $request, Leads $leadsParent): Leads
    {
        return DB::transaction(function () use ($request, $leadsParent) {
            $current_date_time = Carbon::now()->toDateTimeString();
            $nomor = $this->generateNomor();

            $newLead = Leads::create([
                'nomor' => $nomor,
                'leads_id' => $leadsParent->id,
                'tgl_leads' => $current_date_time,
                'nama_perusahaan' => $request->nama_perusahaan,
                'telp_perusahaan' => $leadsParent->telp_perusahaan,
                'jenis_perusahaan_id' => $leadsParent->jenis_perusahaan_id,
                'branch_id' => $leadsParent->branch_id,
                'platform_id' => 8,
                'kebutuhan_id' => $leadsParent->kebutuhan_id,
                'alamat' => $leadsParent->alamat,
                'pic' => $leadsParent->pic,
                'jabatan' => $leadsParent->jabatan,
                'no_telp' => $leadsParent->no_telp,
                'email' => $leadsParent->email,
                'status_leads_id' => 1,
                'notes' => $leadsParent->notes,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id()
            ]);

            $nomorActivity = $this->generateNomorActivity($newLead->id);
            $activity = DB::table('sl_customer_activity')->insertGetId([
                'leads_id' => $newLead->id,
                'branch_id' => $leadsParent->branch_id,
                'tgl_activity' => $current_date_time,
                'nomor' => $nomorActivity,
                'notes' => 'Leads Terbentuk',
                'tipe' => 'Leads',
                'status_leads_id' => 1,
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_at' => $current_date_time,
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id()
            ]);

            if (Auth::user()->cais_role_id == 29) {
                $timSalesD = DB::table('m_tim_sales_d')->where('user_id', Auth::id())->first();
                if ($timSalesD) {
                    $newLead->update([
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_d_id' => $timSalesD->id
                    ]);

                    DB::table('sl_customer_activity')->where('id', $activity)->update([
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_d_id' => $timSalesD->id
                    ]);
                }
            }

            return $newLead;
        });
    }

    // ======================================================================
    // UTILITIES
    // ======================================================================

    public function hitungBerakhirKontrak($tanggalBerakhir)
    {
        if (is_null($tanggalBerakhir)) {
            return "-";
        }

        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);

        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) {
            return "Kontrak habis";
        }

        $selisih = $tanggalSekarang->diff($tanggalBerakhir);

        $hasil = [];
        if ($selisih->y > 0)
            $hasil[] = "{$selisih->y} tahun";
        if ($selisih->m > 0)
            $hasil[] = "{$selisih->m} bulan";
        if ($selisih->d > 0)
            $hasil[] = "{$selisih->d} hari";

        return implode(', ', $hasil);
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
