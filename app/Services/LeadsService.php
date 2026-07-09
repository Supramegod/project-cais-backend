<?php

namespace App\Services;

use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Http\Requests\AssignSalesRequest;
use App\Http\Requests\RemoveSalesRequest;
use App\Models\Benua;
use App\Models\BidangPerusahaan;
use App\Models\City;
use App\Models\CustomerActivity;
use App\Models\District;
use App\Models\JenisPerusahaan;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\LeadsPic;
use App\Models\Negara;
use App\Models\Province;
use App\Models\TimSalesDetail;
use App\Models\Village;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadsService
{
    /**
     * Pure lead/tim permission check.
     */
    public function canViewLead($lead, $tim)
    {
        if (Auth::user()->cais_role_id == 29) {
            return $tim && $lead->tim_sales_d_id == $tim->id;
        }
        return true;
    }

    /**
     * Simpan daftar PIC (multi PIC) ke tabel sl_leads_pic.
     * Jika $replace = true, PIC lama akan di-soft delete dulu (dipakai saat update).
     */
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

    /**
     * Create lead (transactional) — assign sales berdasarkan role user.
     */
    public function createLead(StoreLeadRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $current_date_time = Carbon::now()->toDateTimeString();

            // Get related data using models
            $provinsi = Province::find($request->provinsi);
            $kota = City::find($request->kota);
            $kecamatan = District::find($request->kecamatan);
            $kelurahan = Village::find($request->kelurahan);
            $benua = Benua::find($request->benua);
            $negara = Negara::find($request->negara);
            $jenisPerusahaan = JenisPerusahaan::find($request->jenis_perusahaan);
            $bidangPerusahaan = BidangPerusahaan::find($request->bidang_perusahaan);

            $nomor = $this->generateNomor();

            // Multi PIC: kolom datar diisi dari PIC pertama (kompatibilitas mundur)
            $pics = $request->input('pics', []);
            $firstPic = !empty($pics) ? $pics[0] : null;

            // Create lead using model
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

            // Simpan daftar PIC (multi PIC). Fallback ke PIC tunggal lama bila pics kosong.
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

            // PROSES ASSIGNMENT SALES
            // CASE 1: Auto assign jika user adalah sales (role 29)
            if (in_array(Auth::user()->cais_role_id, [29, 31, 32, 33])) {
                $assignmentResults = $this->autoAssignSalesToKebutuhan($lead, $request->kebutuhan);
            }
            // CASE 2: Manual assignment dari user yang berwenang
            elseif ($request->has('assignments') && !empty($request->assignments)) {
                $assignmentResults = $this->manualAssignSalesToKebutuhan($lead, $request->assignments);
            }
            // CASE 3: Tidak ada assignment, hanya sync kebutuhan tanpa sales
            else {
                $this->syncKebutuhanTanpaSales($lead, $request->kebutuhan);
            }

            // Create activity using model
            $nomorActivity = $this->generateNomorActivity($lead->id);
            // ✅ SESUDAH — satu INSERT, response tetap sama
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

            // Multi PIC: kolom datar diisi dari PIC pertama (kompatibilitas mundur)
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
                'tgl_leads' => Carbon::now()->toDateString(), // hasil: 2026-06-08
                'updated_by' => Auth::user()->full_name
            ]);

            // Sync daftar PIC (multi PIC). Hanya diproses bila pics dikirim.
            if (!empty($pics)) {
                $this->syncLeadsPics($lead, $pics, true);
            }

            $assignmentResults = [];

            // PROSES ASSIGNMENT SALES (SAMA SEPERTI DI ADD)
            // CASE 1: Auto assign jika user adalah sales (role 29) dan lead belum memiliki sales
            if (Auth::user()->cais_role_id == 29 && !$lead->tim_sales_d_id) {
                $assignmentResults = $this->autoAssignSalesToKebutuhan($lead, $request->kebutuhan);
            }
            // CASE 2: Manual assignment dari user yang berwenang
            elseif ($request->has('assignments') && !empty($request->assignments)) {
                $assignmentResults = $this->manualAssignSalesToKebutuhan($lead, $request->assignments);
            }
            // CASE 3: Tidak ada assignment, hanya sync kebutuhan (pertahankan sales existing jika ada)
            else {
                $this->syncKebutuhanDenganSalesExisting($lead, $request->kebutuhan);
            }

            // Create activity untuk update
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
     * Soft delete lead beserta kebutuhan terkait (transactional).
     */
    public function deleteLead(Leads $lead): void
    {
        DB::transaction(function () use ($lead) {
            $id = $lead->id;
            // isi deleted_by di tabel sl_leads
            $lead->deleted_by = Auth::user()->full_name;
            $lead->save();
            $lead->delete();

            // Dapatkan ID user yang sedang login
            $deletedBy = Auth::user()->full_name;

            LeadsKebutuhan::where('leads_id', $id)
                ->update([
                    'deleted_by' => $deletedBy,
                    'deleted_at' => now(),
                ]);
        });
    }

    /**
     * Restore lead beserta kebutuhan terkait (transactional).
     */
    public function restoreLead(Leads $lead): void
    {
        DB::transaction(function () use ($lead) {
            $id = $lead->id;
            $lead->restore();
            $lead->deleted_by = null;
            $lead->save();
            LeadsKebutuhan::onlyTrashed()
                ->where('leads_id', $id)
                ->update([
                    'deleted_at' => null, // Me-restore record
                    'deleted_by' => null, // Mengosongkan kolom kustom
                ]);
        });
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

            // Create activity
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

    /**
     * Aktifkan lead (transactional).
     */
    public function activateLead(Leads $lead): void
    {
        DB::transaction(function () use ($lead) {
            $lead->is_aktif = 1;
            $lead->updated_by = Auth::user()->full_name;
            $lead->save();
        });
    }

    /**
     * Generate nomor untuk semua leads yang belum memiliki nomor (transactional).
     */
    public function generateNullKode(): void
    {
        DB::transaction(function () {
            $leads = Leads::whereNull('nomor')->whereNull('deleted_at')->get();
            $nomor = "";

            foreach ($leads as $key => $lead) {
                if ($key == 0) {
                    $nomor = $this->generateNomor();
                } else {
                    $nomor = $this->generateNomorLanjutan($nomor);
                }

                $lead->update(['nomor' => $nomor]);
            }
        });
    }

    /**
     * Assign sales ke kebutuhan lead (transactional).
     */
    public function assignSales(AssignSalesRequest $request, Leads $lead, $user): array
    {
        return DB::transaction(function () use ($request, $lead, $user) {
            // 4. Pre-load Data untuk optimasi (Anti N+1)
            $kebutuhanIds = [];
            foreach ($request->assignments as $assignment) {
                $kebutuhanIds = array_merge($kebutuhanIds, $assignment['kebutuhan_ids']);
            }
            $kebutuhanIds = array_unique($kebutuhanIds);
            $kebutuhanMap = Kebutuhan::whereIn('id', $kebutuhanIds)->pluck('nama', 'id');

            $timSalesDIds = array_column($request->assignments, 'tim_sales_d_id');
            $timSalesDMap = TimSalesDetail::with('user', 'timSales')
                ->whereIn('id', $timSalesDIds)
                ->get()
                ->keyBy('id');

            $assignmentResults = [];
            $allAssignedKebutuhanNames = [];
            $allAssignedSalesNames = [];

            // 5. Proses Assignment
            foreach ($request->assignments as $assignment) {
                $timSalesD = $timSalesDMap->get($assignment['tim_sales_d_id']);

                if (!$timSalesD)
                    continue;

                $salesName = $timSalesD->user->full_name ?? $timSalesD->nama;
                $allAssignedSalesNames[] = $salesName;

                $assignedKebutuhanForThisSales = [];

                foreach ($assignment['kebutuhan_ids'] as $kebutuhan_id) {

                    LeadsKebutuhan::updateOrCreate(
                        [
                            'leads_id' => $lead->id,
                            'kebutuhan_id' => $kebutuhan_id,
                            'tim_sales_d_id' => $timSalesD->id
                        ],
                        [
                            'tim_sales_id' => $timSalesD->tim_sales_id
                        ]
                    );

                    // ✅ FIX UNTUK DATA DOUBLE (Hapus "no assigned"):
                    // Menghapus record placeholder yang sales-nya masih kosong (NULL) untuk kebutuhan ini
                    LeadsKebutuhan::where('leads_id', $lead->id)
                        ->where('kebutuhan_id', $kebutuhan_id)
                        ->whereNull('tim_sales_d_id')
                        ->delete();

                    $assignedKebutuhanForThisSales[] = $kebutuhan_id;

                    if (isset($kebutuhanMap[$kebutuhan_id])) {
                        $allAssignedKebutuhanNames[] = $kebutuhanMap[$kebutuhan_id];
                    }
                }

                $assignmentResults[] = [
                    'sales_assigned' => [
                        'tim_sales_d_id' => $timSalesD->id,
                        'sales_name' => $salesName,
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_name' => $timSalesD->timSales->nama ?? 'N/A'
                    ],
                    'kebutuhan_assigned' => $assignedKebutuhanForThisSales
                ];
            }

            // 6. Buat Activity Log
            $nomorActivity = $this->generateNomorActivity($lead->id);
            CustomerActivity::create([
                'leads_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'tgl_activity' => Carbon::now()->toDateTimeString(),
                'nomor' => $nomorActivity,
                'notes' => implode(', ', array_unique($allAssignedSalesNames)) . ' diassign ke kebutuhan: ' . implode(', ', array_unique($allAssignedKebutuhanNames)),
                'tipe' => 'Assignment',
                'status_leads_id' => $lead->status_leads_id,
                'is_activity' => 0,
                'user_id' => $user->id,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id
            ]);


            if ($lead) {
                $lead->tgl_leads = Carbon::now()->toDateString();
                $lead->save();
            }

            return [
                'lead_id' => $lead->id,
                'assignments' => $assignmentResults
            ];
        });
    }

    /**
     * Hapus assignment sales dari kebutuhan tertentu (transactional).
     */
    public function removeSales(RemoveSalesRequest $request, Leads $lead): array
    {
        return DB::transaction(function () use ($request, $lead) {
            $id = $lead->id;
            // Hapus assignment sales dari kebutuhan yang dipilih
            $removedCount = LeadsKebutuhan::where('leads_id', $id)
                ->whereIn('kebutuhan_id', $request->kebutuhan_ids)
                ->update([
                    'tim_sales_id' => null,
                    'tim_sales_d_id' => null
                ]);

            // Buat activity log
            $nomorActivity = $this->generateNomorActivity($lead->id);
            CustomerActivity::create([
                'leads_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'tgl_activity' => Carbon::now()->toDateTimeString(),
                'nomor' => $nomorActivity,
                'notes' => 'Assignment sales dihapus dari kebutuhan: ' . implode(', ', $request->kebutuhan_ids),
                'tipe' => 'Assignment Removal',
                'status_leads_id' => $lead->status_leads_id,
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id()
            ]);

            return [
                'lead_id' => $id,
                'removed_kebutuhan' => $request->kebutuhan_ids,
                'removed_count' => $removedCount
            ];
        });
    }

    // ======================================================================
    // UTILITY METHODS
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

    // Tambahkan method helper untuk generate nomor lanjutan
    private function generateNomorLanjutan($nomor)
    {
        $chars = str_split($nomor);
        for ($i = count($chars) - 1; $i >= 0; $i--) {
            $ascii = ord($chars[$i]);
            if (($ascii >= 48 && $ascii < 57) || ($ascii >= 65 && $ascii < 90)) {
                $ascii += 1;
            } else if ($ascii == 90) {
                $ascii = 48;
            } else {
                continue;
            }
            $ascchar = chr($ascii);
            $nomor = substr_replace($nomor, $ascchar, $i);
            break;
        }
        if (strlen($nomor) < 5) {
            $jumlah = 5 - strlen($nomor);
            for ($i = 0; $i < $jumlah; $i++) {
                $nomor = $nomor . "A";
            }
        }
        return $nomor;
    }

    private function generateNomor()
    {
        $lastLeads = Leads::latest('id')->first();

        // Default nomor if no previous leads
        if (!$lastLeads?->nomor) {
            return 'AAAAA';
        }

        $nomor = $lastLeads->nomor;
        $chars = str_split($nomor);

        // Increment from right to left
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
                    $chars[$i] = '0'; // Z → 0, carry over ← sudah benar
                    continue;
                }
            }
        }

        // Convert back to string and pad to 5 chars
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

    /**
     * Auto assign sales ke lead (untuk user dengan role 29)
     */
    private function autoAssignSalesToKebutuhan($lead, $kebutuhanIds)
    {
        $user = Auth::user();
        $assignmentResults = [];

        if (in_array($user->cais_role_id, [29, 31, 32, 33])) {
            $timSalesD = TimSalesDetail::where('user_id', $user->id)->first();

            if ($timSalesD) {
                // Update lead dengan sales info
                $lead->update([
                    'tim_sales_id' => $timSalesD->tim_sales_id,
                    'tim_sales_d_id' => $timSalesD->id
                ]);

                // Assign sales ke SEMUA kebutuhan yang dipilih
                $kebutuhanData = [];
                foreach ($kebutuhanIds as $kebutuhan_id) {
                    $kebutuhanData[$kebutuhan_id] = [
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_d_id' => $timSalesD->id
                    ];
                }

                $lead->kebutuhan()->sync($kebutuhanData);

                $assignmentResults[] = [
                    'type' => 'auto_assign',
                    'sales_assigned' => [
                        'tim_sales_d_id' => $timSalesD->id,
                        'sales_name' => $timSalesD->user->full_name ?? $timSalesD->nama,
                        'tim_sales_id' => $timSalesD->tim_sales_id
                    ],
                    'kebutuhan_assigned' => $kebutuhanIds
                ];
            }
        }

        return $assignmentResults;
    }

    /**
     * Manual assignment sales ke kebutuhan (untuk user berwenang)
     */
    // ✅ SESUDAH — pre-load semua TimSalesDetail sekaligus, response tetap sama
    private function manualAssignSalesToKebutuhan($lead, $assignments)
    {
        $assignmentResults = [];

        // Pre-load data sales untuk efisiensi
        $timSalesDIds = array_column($assignments, 'tim_sales_d_id');
        $timSalesDMap = TimSalesDetail::with('user', 'timSales')
            ->whereIn('id', $timSalesDIds)
            ->get()
            ->keyBy('id');

        foreach ($assignments as $assignment) {
            $timSalesD = $timSalesDMap->get($assignment['tim_sales_d_id']);

            if ($timSalesD) {
                $assignedKebutuhan = [];
                foreach ($assignment['kebutuhan_ids'] as $kebutuhan_id) {

                    // ✅ FIX: Gunakan tim_sales_d_id sebagai kunci pencarian agar mendukung multi-sales
                    LeadsKebutuhan::updateOrCreate(
                        [
                            'leads_id' => $lead->id,
                            'kebutuhan_id' => $kebutuhan_id,
                            'tim_sales_d_id' => $timSalesD->id
                        ],
                        [
                            'tim_sales_id' => $timSalesD->tim_sales_id
                        ]
                    );

                    // ✅ FIX: Hapus record "no assigned" (yang tim_sales_d_id nya NULL)
                    // agar tidak muncul double di UI
                    LeadsKebutuhan::where('leads_id', $lead->id)
                        ->where('kebutuhan_id', $kebutuhan_id)
                        ->whereNull('tim_sales_d_id')
                        ->delete();

                    $assignedKebutuhan[] = $kebutuhan_id;
                }

                $assignmentResults[] = [
                    'sales_assigned' => [
                        'tim_sales_d_id' => $timSalesD->id,
                        'sales_name' => $timSalesD->user->full_name ?? $timSalesD->nama,
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_name' => $timSalesD->timSales->nama ?? 'N/A'
                    ],
                    'kebutuhan_assigned' => $assignedKebutuhan
                ];
            }
        }

        return $assignmentResults;
    }

    /**
     * Sync kebutuhan tanpa assignment sales
     */
    private function syncKebutuhanTanpaSales($lead, $kebutuhanIds)
    {
        $kebutuhanData = [];
        foreach ($kebutuhanIds as $kebutuhan_id) {
            $kebutuhanData[$kebutuhan_id] = [
                'tim_sales_d_id' => null, // Biarkan NULL agar muncul sebagai "no assigned" yang benar
                'tim_sales_id' => null
            ];
        }

        // Sync kebutuhan dengan status kosong (menunggu assignment)
        $lead->kebutuhan()->sync($kebutuhanData);
        return [];
    }
    /**
     * Sync kebutuhan dengan mempertahankan sales existing
     */
    private function syncKebutuhanDenganSalesExisting($lead, $kebutuhanIds)
    {
        // Ambil data kebutuhan existing beserta sales-nya
        $existingKebutuhan = LeadsKebutuhan::where('leads_id', $lead->id)
            ->whereIn('kebutuhan_id', $kebutuhanIds)
            ->get()
            ->keyBy('kebutuhan_id');

        $kebutuhanData = [];

        foreach ($kebutuhanIds as $kebutuhan_id) {
            $existing = $existingKebutuhan->get($kebutuhan_id);

            $kebutuhanData[$kebutuhan_id] = [
                'tim_sales_id' => $existing ? $existing->tim_sales_id : null,
                'tim_sales_d_id' => $existing ? $existing->tim_sales_d_id : null
            ];
        }

        $lead->kebutuhan()->sync($kebutuhanData);

        return [];
    }
}
