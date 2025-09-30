<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Leads;
use App\Models\Branch;
use App\Models\StatusLeads;
use App\Models\Platform;
use App\Models\TimSalesDetail;
use App\Models\CustomerActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class LeadsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/leads",
     *     summary="Get leads list with filters",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Filter tanggal dari (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="Filter tanggal sampai (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="branch",
     *         in="query",
     *         description="Filter by branch ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Filter by platform ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status leads ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data leads berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="jumlah_belum_aktif", type="integer"),
     *                 @OA\Property(property="jumlah_ditolak", type="integer"),
     *                 @OA\Property(property="leads", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function index(Request $request)
    {
        try {
            $tglDari = $request->tgl_dari ?? Carbon::now()->startOfMonth()->subMonths(3)->toDateString();
            $tglSampai = $request->tgl_sampai ?? Carbon::now()->toDateString();

            $ctglDari = Carbon::createFromFormat('Y-m-d', $tglDari);
            $ctglSampai = Carbon::createFromFormat('Y-m-d', $tglSampai);

            if ($ctglDari->gt($ctglSampai)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal dari tidak boleh melebihi tanggal sampai'
                ], 400);
            }

            $jumlahBelumAktif = Leads::whereNull('is_aktif')->count();
            $jumlahDitolak = Leads::where('is_aktif', 0)->count();

            $branches = Branch::where('id', '!=', 1)
                ->where('is_active', 1)
                ->get(['id', 'name']);

            $statuses = StatusLeads::all(['id', 'nama', 'warna_background', 'warna_font']);
            $platforms = Platform::all(['id', 'nama']);

            return response()->json([
                'success' => true,
                'message' => 'Data leads berhasil diambil',
                'data' => [
                    'jumlah_belum_aktif' => $jumlahBelumAktif,
                    'jumlah_ditolak' => $jumlahDitolak,
                    'branches' => $branches,
                    'statuses' => $statuses,
                    'platforms' => $platforms,
                    'filters' => [
                        'tgl_dari' => $tglDari,
                        'tgl_sampai' => $tglSampai
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leads/list",
     *     summary="Get all leads data",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Filter tanggal dari",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="Filter tanggal sampai",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="branch",
     *         in="query",
     *         description="Filter by branch ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Filter by platform ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function list(Request $request)
    {
        try {
            $tim = TimSalesDetail::where('user_id', Auth::id())->first();

            $query = Leads::with(['statusLeads', 'branch', 'platform', 'timSalesD'])
                ->where('status_leads_id', '!=', 102);

            if ($request->filled('tgl_dari')) {
                $query->where('tgl_leads', '>=', $request->tgl_dari);
            } else {
                $query->whereDate('tgl_leads', Carbon::today());
            }

            if ($request->filled('tgl_sampai')) {
                $query->where('tgl_leads', '<=', $request->tgl_sampai);
            } else {
                $query->whereDate('tgl_leads', Carbon::today());
            }

            if ($request->filled('branch')) {
                $query->where('branch_id', $request->branch);
            }

            if ($request->filled('platform')) {
                $query->where('platform_id', $request->platform);
            }

            if ($request->filled('status')) {
                $query->where('status_leads_id', $request->status);
            }

            $data = $query->get();

            $data->transform(function ($item) use ($tim) {
                $item->tgl = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
                $item->can_view = $this->canViewLead($item, $tim);
                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data leads berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    private function canViewLead($lead, $tim)
    {
        if (Auth::user()->role_id == 29) {
            return $tim && $lead->tim_sales_d_id == $tim->id;
        }
        return true;
    }

    /**
     * @OA\Get(
     *     path="/api/leads/{id}",
     *     summary="Get lead detail by ID",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Lead ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Lead not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function view($id)
    {
        try {
            $lead = Leads::with([
                'branch',
                'kebutuhan',
                'timSales',
                'timSalesD',
                'statusLeads',
                'jenisPerusahaan',
                'company'
            ])
                ->whereNull('customer_id')
                ->find($id);

            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }

            $lead->stgl_leads = Carbon::parse($lead->tgl_leads)->isoFormat('D MMMM Y');
            $lead->screated_at = Carbon::parse($lead->created_at)->isoFormat('D MMMM Y');

            if (!empty($lead->kebutuhan_id)) {
                $lead->kebutuhan_array = array_map('trim', explode(',', $lead->kebutuhan_id));
            } else {
                $lead->kebutuhan_array = [];
            }

            $activities = CustomerActivity::where('leads_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $activities->transform(function ($activity) {
                $activity->screated_at = Carbon::parse($activity->created_at)->isoFormat('D MMMM Y HH:mm');
                $activity->stgl_activity = Carbon::parse($activity->tgl_activity)->isoFormat('D MMMM Y');
                return $activity;
            });

            return response()->json([
                'success' => true,
                'message' => 'Detail lead berhasil diambil',
                'data' => [
                    'lead' => $lead,
                    'activities' => $activities
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leads",
     *     summary="Create or update lead",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_perusahaan","pic","branch","kebutuhan","provinsi","kota"},
     *             @OA\Property(property="id", type="integer", description="ID untuk update, kosongkan untuk create"),
     *             @OA\Property(property="nama_perusahaan", type="string", example="PT ABC"),
     *             @OA\Property(property="telp_perusahaan", type="string", example="021-1234567"),
     *             @OA\Property(property="jenis_perusahaan", type="integer", example=1),
     *             @OA\Property(property="bidang_perusahaan", type="integer", example=1),
     *             @OA\Property(property="branch", type="integer", example=1),
     *             @OA\Property(property="platform", type="integer", example=1),
     *             @OA\Property(property="kebutuhan", type="array", @OA\Items(type="integer"), example={1,2}),
     *             @OA\Property(property="alamat_perusahaan", type="string"),
     *             @OA\Property(property="pic", type="string", example="John Doe"),
     *             @OA\Property(property="jabatan_pic", type="string", example="Manager"),
     *             @OA\Property(property="no_telp", type="string", example="08123456789"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="pma", type="string"),
     *             @OA\Property(property="detail_leads", type="string"),
     *             @OA\Property(property="provinsi", type="integer", example=1),
     *             @OA\Property(property="kota", type="integer", example=1),
     *             @OA\Property(property="kecamatan", type="integer", example=1),
     *             @OA\Property(property="kelurahan", type="integer", example=1),
     *             @OA\Property(property="benua", type="integer", example=1),
     *             @OA\Property(property="negara", type="integer", example=1),
     *             @OA\Property(property="perusahaan_group_id", type="integer"),
     *             @OA\Property(property="new_nama_grup", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation Error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function save(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'nama_perusahaan' => 'required|max:100|min:3',
                'pic' => 'required',
                'branch' => 'required',
                'kebutuhan' => 'required|array|min:1',
                'provinsi' => 'required',
                'kota' => 'required'
            ], [
                'min' => 'Masukkan :attribute minimal :min',
                'max' => 'Masukkan :attribute maksimal :max',
                'required' => ':attribute harus di isi',
                'kebutuhan.required' => 'Kebutuhan harus dipilih minimal 1',
                'kebutuhan.array' => 'Kebutuhan harus berupa array',
                'kebutuhan.min' => 'Kebutuhan harus dipilih minimal 1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 400);
            }

            $current_date_time = Carbon::now()->toDateTimeString();

            $provinsi = DB::connection('mysqlhris')->table('m_province')
                ->where('id', $request->provinsi)->first();
            $kota = DB::connection('mysqlhris')->table('m_city')
                ->where('id', $request->kota)->first();
            $kecamatan = DB::connection('mysqlhris')->table('m_district')
                ->where('id', $request->kecamatan)->first();
            $kelurahan = DB::connection('mysqlhris')->table('m_village')
                ->where('id', $request->kelurahan)->first();
            $benua = DB::table('m_benua')->where('id_benua', $request->benua)->first();
            $negara = DB::table('m_negara')->where('id_negara', $request->negara)->first();
            $jenisPerusahaan = DB::table('m_jenis_perusahaan')
                ->where('id', $request->jenis_perusahaan)->first();
            $bidangPerusahaan = DB::table('m_bidang_perusahaan')
                ->where('id', $request->bidang_perusahaan)->first();

            $kebutuhan_ids = '';
            if ($request->has('kebutuhan') && is_array($request->kebutuhan)) {
                $kebutuhan_ids = implode(',', $request->kebutuhan);
            }

            if (!empty($request->id)) {
                // UPDATE
                $lead = Leads::find($request->id);
                if (!$lead) {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => 'Lead tidak ditemukan'
                    ], 404);
                }

                $lead->update([
                    'nama_perusahaan' => $request->nama_perusahaan,
                    'telp_perusahaan' => $request->telp_perusahaan,
                    'jenis_perusahaan_id' => $request->jenis_perusahaan,
                    'jenis_perusahaan' => $jenisPerusahaan ? $jenisPerusahaan->nama : null,
                    'bidang_perusahaan_id' => $request->bidang_perusahaan,
                    'bidang_perusahaan' => $bidangPerusahaan ? $bidangPerusahaan->nama : null,
                    'branch_id' => $request->branch,
                    'platform_id' => $request->platform,
                    'kebutuhan_id' => $kebutuhan_ids,
                    'alamat' => $request->alamat_perusahaan,
                    'pic' => $request->pic,
                    'jabatan' => $request->jabatan_pic,
                    'no_telp' => $request->no_telp,
                    'email' => $request->email,
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
                    'updated_by' => Auth::user()->full_name
                ]);

                $msgSave = 'Leads ' . $request->nama_perusahaan . ' berhasil diupdate';
            } else {
                // CREATE
                // Cek kemiripan nama
                $companies = Leads::pluck('nama_perusahaan');
                foreach ($companies as $company) {
                    if (similar_text(strtolower($request->nama_perusahaan), strtolower($company), $percent)) {
                        if ($percent > 95) {
                            DB::rollback();
                            return response()->json([
                                'success' => false,
                                'message' => 'Nama perusahaan terlalu mirip dengan: ' . $company
                            ], 400);
                        }
                    }
                }

                $nomor = $this->generateNomor();

                $lead = Leads::create([
                    'nomor' => $nomor,
                    'tgl_leads' => $current_date_time,
                    'nama_perusahaan' => $request->nama_perusahaan,
                    'telp_perusahaan' => $request->telp_perusahaan,
                    'jenis_perusahaan_id' => $request->jenis_perusahaan,
                    'branch_id' => $request->branch,
                    'platform_id' => $request->platform,
                    'kebutuhan_id' => $kebutuhan_ids,
                    'alamat' => $request->alamat_perusahaan,
                    'pic' => $request->pic,
                    'jabatan' => $request->jabatan_pic,
                    'no_telp' => $request->no_telp,
                    'email' => $request->email,
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
                    'created_by' => Auth::user()->full_name
                ]);

                // Handle grup perusahaan
                $groupId = $request->perusahaan_group_id;
                if ($groupId === '__new__') {
                    $groupId = DB::table('sl_perusahaan_groups')->insertGetId([
                        'nama_grup' => $request->new_nama_grup,
                        'created_at' => now(),
                        'created_by' => Auth::user()->full_name,
                        'update_at' => now(),
                        'update_by' => Auth::user()->full_name
                    ]);
                }

                if (!empty($groupId)) {
                    DB::table('sl_perusahaan_groups_d')->insert([
                        'group_id' => $groupId,
                        'nama_perusahaan' => $request->nama_perusahaan,
                        'leads_id' => $lead->id,
                        'created_at' => now(),
                        'created_by' => Auth::user()->full_name,
                        'update_at' => now(),
                        'update_by' => Auth::user()->full_name
                    ]);
                }

                // Create customer activity
                $nomorActivity = $this->generateNomorActivity($lead->id);
                $activity = CustomerActivity::create([
                    'leads_id' => $lead->id,
                    'branch_id' => $request->branch,
                    'tgl_activity' => $current_date_time,
                    'nomor' => $nomorActivity,
                    'notes' => 'Leads Terbentuk',
                    'tipe' => 'Leads',
                    'status_leads_id' => 1,
                    'is_activity' => 0,
                    'user_id' => Auth::id(),
                    'created_by' => Auth::user()->full_name
                ]);

                // Assign tim sales
                $timSalesD = DB::table('m_tim_sales_d')->where('user_id', Auth::id())->first();
                if ($timSalesD) {
                    $lead->update([
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_d_id' => $timSalesD->id
                    ]);

                    $activity->update([
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_d_id' => $timSalesD->id
                    ]);
                }

                $msgSave = 'Leads ' . $request->nama_perusahaan . ' berhasil disimpan dengan nomor: ' . $nomor;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $msgSave,
                'data' => $lead
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateNomor()
    {
        $nomor = "AAAAA";
        $lastLeads = Leads::orderBy('id', 'DESC')->first();

        if ($lastLeads && $lastLeads->nomor) {
            $nomor = $lastLeads->nomor;
            $chars = str_split($nomor);
            for ($i = count($chars) - 1; $i >= 0; $i--) {
                $ascii = ord($chars[$i]);
                if (($ascii >= 48 && $ascii < 57) || ($ascii >= 65 && $ascii < 90)) {
                    $ascii += 1;
                } elseif ($ascii == 90) {
                    $ascii = 48;
                } else {
                    continue;
                }
                $ascchar = chr($ascii);
                $nomor = substr_replace($nomor, $ascchar, $i);
                break;
            }
        }

        if (strlen($nomor) < 5) {
            $jumlah = 5 - strlen($nomor);
            for ($i = 0; $i < $jumlah; $i++) {
                $nomor = $nomor . "A";
            }
        }

        return $nomor;
    }

    private function generateNomorActivity($leadsId)
    {
        $lastActivity = CustomerActivity::where('leads_id', $leadsId)
            ->orderBy('id', 'DESC')
            ->first();

        if (!$lastActivity) {
            return 'ACT-' . $leadsId . '-001';
        }

        $parts = explode('-', $lastActivity->nomor);
        $number = isset($parts[2]) ? intval($parts[2]) + 1 : 1;

        return 'ACT-' . $leadsId . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @OA\Delete(
     *     path="/api/leads/{id}",
     *     summary="Soft delete lead",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Lead ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Lead not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }

            $lead->deleted_by = Auth::user()->full_name;
            $lead->save();
            $lead->delete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Leads ' . $lead->nama_perusahaan . ' berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leads/{id}/restore",
     *     summary="Restore deleted lead",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Lead ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Lead not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function restore($id)
    {
        try {
            DB::beginTransaction();

            $lead = Leads::withTrashed()->find($id);
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }

            $lead->restore();
            $lead->deleted_by = null;
            $lead->save();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Leads ' . $lead->nama_perusahaan . ' berhasil direstore'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leads/deleted",
     *     summary="Get all deleted leads",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function listTerhapus()
    {
        try {
            $data = Leads::onlyTrashed()
                ->with(['statusLeads', 'branch', 'platform', 'timSalesD'])
                ->whereNull('customer_id')
                ->get();

            $data->transform(function ($item) {
                $item->tgl = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data leads terhapus berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leads/{id}/child",
     *     summary="Get child leads",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Lead ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function childLeads($id)
    {
        try {
            $data = Leads::where(function ($query) use ($id) {
                $query->where('leads_id', $id)
                    ->orWhere('id', $id);
            })
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data child leads berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leads/{id}/child",
     *     summary="Create child lead",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Parent Lead ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_perusahaan"},
     *             @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Cabang Jakarta")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Parent lead not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function saveChildLeads(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $leadsParent = Leads::find($id);
            if (!$leadsParent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent lead tidak ditemukan'
                ], 404);
            }

            if (empty($request->nama_perusahaan)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama perusahaan harus diisi'
                ], 400);
            }

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
                'created_by' => Auth::user()->full_name
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
                'created_by' => Auth::user()->full_name
            ]);

            if (Auth::user()->role_id == 29) {
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

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Leads ' . $request->nama_perusahaan . ' berhasil disimpan',
                'data' => $newLead
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leads/belum-aktif",
     *     summary="Get leads that are not yet active",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function leadsBelumAktif()
    {
        try {
            $data = Leads::with(['statusLeads', 'kebutuhan', 'platform'])
                ->whereNull('is_aktif')
                ->get();

            $data->transform(function ($item) {
                $item->tgl_leads = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data leads belum aktif berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leads/available",
     *     summary="Get available leads for quotation/activity",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function availableLeads()
    {
        try {
            $query = Leads::with(['statusLeads', 'branch', 'kebutuhan', 'timSales', 'timSalesD']);

            // Role-based filtering
            $user = auth()->user();
            if (in_array($user->role_id, [29, 30, 31, 32, 33])) {
                if ($user->role_id == 29) {
                    $query->whereHas('timSalesD', function($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                } elseif ($user->role_id == 31) {
                    $tim = DB::table('m_tim_sales_d')->where('user_id', $user->id)->first();
                    if ($tim) {
                        $memberSales = DB::table('m_tim_sales_d')
                            ->where('tim_sales_id', $tim->tim_sales_id)
                            ->pluck('user_id')
                            ->toArray();
                        $query->whereHas('timSalesD', function($q) use ($memberSales) {
                            $q->whereIn('user_id', $memberSales);
                        });
                    }
                }
            } elseif (in_array($user->role_id, [6, 8])) {
                // RO roles
            } elseif (in_array($user->role_id, [54, 55, 56])) {
                if ($user->role_id == 54) {
                    $query->where('crm_id', $user->id);
                }
            }

            $data = $query->get();

            $data->transform(function ($item) {
                $item->tgl = \Carbon\Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
                $item->salesEmail = '';
                $item->branchManagerEmail = '';
                $item->branchManager = '';
                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data leads tersedia berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/leads/available-quotation",
     *     summary="Get available leads for quotation creation",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function availableQuotation()
    {
        try {
            $query = Leads::with(['statusLeads', 'branch', 'kebutuhan', 'timSales', 'timSalesD'])
                ->whereNull('leads_id');

            // Role-based filtering
            $user = auth()->user();
            if (in_array($user->role_id, [29, 30, 31, 32, 33])) {
                if ($user->role_id == 29) {
                    $query->whereHas('timSalesD', function($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                } elseif ($user->role_id == 31) {
                    $tim = DB::table('m_tim_sales_d')->where('user_id', $user->id)->first();
                    if ($tim) {
                        $memberSales = DB::table('m_tim_sales_d')
                            ->where('tim_sales_id', $tim->tim_sales_id)
                            ->pluck('user_id')
                            ->toArray();
                        $query->whereHas('timSalesD', function($q) use ($memberSales) {
                            $q->whereIn('user_id', $memberSales);
                        });
                    }
                }
            } elseif (in_array($user->role_id, [4, 5, 6, 8])) {
                if (in_array($user->role_id, [4, 5])) {
                    $query->where('ro_id', $user->id);
                }
            } elseif (in_array($user->role_id, [54, 55, 56])) {
                if ($user->role_id == 54) {
                    $query->where('crm_id', $user->id);
                }
            }

            $data = $query->get();

            $data->transform(function ($item) {
                $item->tgl = \Carbon\Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
                return $item;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data leads tersedia untuk quotation berhasil diambil',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/location/kota/{provinsiId}",
     *     summary="Get cities by province",
     *     tags={"Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="provinsiId",
     *         in="path",
     *         description="Province ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getKota($provinsiId)
    {
        try {
            $kota = DB::connection('mysqlhris')
                ->table('m_city')
                ->where('province_id', $provinsiId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kota berhasil diambil',
                'data' => $kota
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/location/kecamatan/{kotaId}",
     *     summary="Get districts by city",
     *     tags={"Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="kotaId",
     *         in="path",
     *         description="City ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getKecamatan($kotaId)
    {
        try {
            $kecamatan = DB::connection('mysqlhris')
                ->table('m_district')
                ->where('city_id', $kotaId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kecamatan berhasil diambil',
                'data' => $kecamatan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/location/kelurahan/{kecamatanId}",
     *     summary="Get villages by district",
     *     tags={"Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="kecamatanId",
     *         in="path",
     *         description="District ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getKelurahan($kecamatanId)
    {
        try {
            $kelurahan = DB::connection('mysqlhris')
                ->table('m_village')
                ->where('district_id', $kecamatanId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data kelurahan berhasil diambil',
                'data' => $kelurahan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/location/negara/{benuaId}",
     *     summary="Get countries by continent",
     *     tags={"Location"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="benuaId",
     *         in="path",
     *         description="Continent ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getNegara($benuaId)
    {
        try {
            $negara = DB::table('m_negara')
                ->where('id_benua', $benuaId)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Data negara berhasil diambil',
                'data' => $negara
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leads/{id}/activate",
     *     summary="Activate lead",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Lead ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Lead not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function activateLead($id)
    {
        try {
            DB::beginTransaction();

            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }

            $lead->is_aktif = 1;
            $lead->updated_by = Auth::user()->full_name;
            $lead->save();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Lead ' . $lead->nama_perusahaan . ' berhasil diaktifkan'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}