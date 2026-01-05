<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JabatanPic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Models
use App\Models\Branch;
use App\Models\StatusLeads;
use App\Models\Platform;
use App\Models\Leads;
use App\Models\JenisPerusahaan;
use App\Models\Kebutuhan;
use App\Models\CustomerActivity;
use App\Models\TimSalesDetail;
use App\Models\TimSales;

/**
 * @OA\Tag(
 *     name="Customer",
 *     description="API untuk manajemen data Customer. "
 * )
 */

class CustomerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/customer/list",
     *     summary="Mendapatkan daftar customer dengan filter",
     *     description="Menampilkan daftar customer yang sudah terkonversi (status_leads_id = 102) dengan kemampuan filtering berdasarkan branch, platform, status, dan range tanggal. Data yang ditampilkan disesuaikan dengan role user yang login (Sales, SPV Sales, RO, CRM). Default range tanggal adalah 3 bulan terakhir jika tidak dispesifikasikan.",
     *     tags={"Customer"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branch",
     *         in="query",
     *         description="ID Branch untuk filter customer berdasarkan cabang",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="ID Platform untuk filter customer berdasarkan sumber platform (misal: Website, WhatsApp, Referral)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="ID Status Leads untuk filter customer berdasarkan status saat ini",
     *         required=false,
     *         @OA\Schema(type="integer", example=102)
     *     ),
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Tanggal awal periode (format: YYYY-MM-DD). Default: 3 bulan yang lalu dari hari ini",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-07-02")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="Tanggal akhir periode (format: YYYY-MM-DD). Default: hari ini",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-10-02")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar customer",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="master",
     *                     type="object",
     *                     description="Data master untuk dropdown filter",
     *                     @OA\Property(
     *                         property="branch",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Jakarta"),
     *                             @OA\Property(property="is_active", type="integer", example=1)
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="platform",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="nama", type="string", example="Website")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=102),
     *                             @OA\Property(property="nama", type="string", example="Customer")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="customers",
     *                     type="array",
     *                     description="Daftar customer yang sudah difilter",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="ro", type="string", example="RO-2025-001"),
     *                         @OA\Property(property="crm", type="string", example="CRM-2025-001"),
     *                         @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Indonesia"),
     *                         @OA\Property(property="pic", type="string", example="John Doe"),
     *                         @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                         @OA\Property(property="tgl", type="string", example="2 Oktober 2025"),
     *                         @OA\Property(property="sales", type="string", example="Ahmad Salesman"),
     *                         @OA\Property(property="status_name", type="string", example="Customer"),
     *                         @OA\Property(property="branch_name", type="string", example="Jakarta"),
     *                         @OA\Property(property="platform_name", type="string", example="Website"),
     *                         @OA\Property(property="warna_background", type="string", example="#28a745"),
     *                         @OA\Property(property="warna_font", type="string", example="#ffffff"),
     *                         @OA\Property(property="can_view", type="boolean", example=true),
     *                         @OA\Property(property="aksi", type="string", example="view")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="applied_filters",
     *                     type="object",
     *                     description="Filter yang sedang digunakan",
     *                     @OA\Property(property="branch", type="integer", example=2),
     *                     @OA\Property(property="platform", type="integer", example=1),
     *                     @OA\Property(property="status", type="integer", example=102),
     *                     @OA\Property(property="tgl_dari", type="string", example="2025-07-02"),
     *                     @OA\Property(property="tgl_sampai", type="string", example="2025-10-02")
     *                 ),
     *                 @OA\Property(property="error", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid atau sudah expired",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $tglDari = $request->tgl_dari ?: Carbon::now()->startOfMonth()->subMonths(3)->toDateString();
            $tglSampai = $request->tgl_sampai ?: Carbon::now()->toDateString();

            // Validasi tanggal
            $error = null;
            $ctglDari = Carbon::parse($tglDari);
            $ctglSampai = Carbon::parse($tglSampai);

            if ($ctglDari->gt($ctglSampai)) {
                $tglDari = Carbon::now()->startOfMonth()->subMonths(3)->toDateString();
                $error = 'Tanggal dari tidak boleh melebihi tanggal sampai';
            }


            // Query base dengan role-based filtering
            $query = Leads::with(['statusLeads', 'branch', 'platform', 'timSalesD'])
                ->whereNull('customer_id')
                ->where('status_leads_id', 102);

            // Apply filters
            if ($request->branch) {
                $query->where('branch_id', $request->branch);
            }
            if ($request->platform) {
                $query->where('platform_id', $request->platform);
            }
            if ($request->status) {
                $query->where('status_leads_id', $request->status);
            }

            // Apply role-based filtering
            $query = $this->applyRoleFilter($query);
            $tim = TimSalesDetail::where('user_id', Auth::id())->first();

            $customers = $query->get()->map(function ($item) use ($tim) {
                // Format tambahan untuk response
                $item->tgl = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
                $item->sales = $item->timSalesD->nama ?? null;
                $item->status_name = $item->statusLeads->nama ?? null;
                $item->branch_name = $item->branch->name ?? null;
                $item->platform_name = $item->platform->nama ?? null;
                $item->warna_background = $item->statusLeads->warna_background ?? null;
                $item->warna_font = $item->statusLeads->warna_font ?? null;

                // Permission check
                $item->can_view = $this->checkViewPermission($item, $tim);
                $item->aksi = $item->can_view ? 'view' : 'no_access';

                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'customers' => $customers,
                    'error' => $error
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Customer API List Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customer/view/{id}",
     *     summary="Mendapatkan detail customer berdasarkan ID",
     *     description="Menampilkan informasi lengkap customer termasuk data perusahaan, PIC, kebutuhan, dan 5 aktivitas terakhir. Endpoint ini hanya menampilkan customer yang memiliki customer_id (sudah terkonversi). Data aktivitas ditampilkan dalam urutan terbaru terlebih dahulu dengan format tanggal yang user-friendly.",
     *     tags={"Customer"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID Customer (leads_id yang sudah memiliki customer_id)",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil detail customer",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="customer",
     *                     type="object",
     *                     description="Detail informasi customer",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="customer_id", type="string", example="CUST-2025-001"),
     *                     @OA\Property(property="ro", type="string", example="RO-2025-001"),
     *                     @OA\Property(property="crm", type="string", example="CRM-2025-001"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Indonesia"),
     *                     @OA\Property(property="pic", type="string", example="John Doe"),
     *                     @OA\Property(property="jabatan_pic", type="string", example="Manager"),
     *                     @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="alamat", type="string", example="Jl. Contoh No. 123"),
     *                     @OA\Property(property="stgl_leads", type="string", example="2 Oktober 2025"),
     *                     @OA\Property(property="screated_at", type="string", example="1 Oktober 2025")
     *                 ),
     *                 @OA\Property(
     *                     property="activity",
     *                     type="array",
     *                     description="5 aktivitas terakhir customer",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="leads_id", type="integer", example=1),
     *                         @OA\Property(property="tgl_activity", type="string", example="2025-10-02"),
     *                         @OA\Property(property="stgl_activity", type="string", example="2 Oktober 2025"),
     *                         @OA\Property(property="keterangan", type="string", example="Follow up meeting dengan client"),
     *                         @OA\Property(property="screated_at", type="string", example="2 Oktober 2025 14:30")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="branch",
     *                     type="array",
     *                     description="List semua branch aktif",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Jakarta")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="jabatanPic",
     *                     type="array",
     *                     description="List jabatan PIC",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="Manager")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="jenisPerusahaan",
     *                     type="array",
     *                     description="List jenis perusahaan",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="PT")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="kebutuhan",
     *                     type="array",
     *                     description="List kebutuhan",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="Security")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="platform",
     *                     type="array",
     *                     description="List platform",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="Website")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Customer not found")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function view(Request $request, $id): JsonResponse
    {
        try {
            $data = Leads::with(['branch', 'jenisPerusahaan', 'kebutuhan', 'platform'])
                ->whereNotNull('customer_id')
                ->find($id);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Format dates seperti di original controller
            $data->stgl_leads = Carbon::parse($data->tgl_leads)->isoFormat('D MMMM Y');
            $data->screated_at = Carbon::parse($data->created_at)->isoFormat('D MMMM Y');

            // Get activity dengan formatting
            $activity = CustomerActivity::with(['branch', 'statusLeads', 'user'])
                ->whereNull('deleted_at')
                ->where('leads_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $item->screated_at = Carbon::parse($item->created_at)->isoFormat('D MMMM Y HH:mm');
                    $item->stgl_activity = Carbon::parse($item->tgl_activity)->isoFormat('D MMMM Y');
                    return $item;
                });

            return response()->json([
                'success' => true,
                'data' => array_merge([
                    'customer' => $data,
                    'activity' => $activity
                ], )
            ]);

        } catch (\Exception $e) {
            \Log::error('Customer API View Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customer/available",
     *     summary="Mendapatkan daftar customer yang tersedia",
     *     description="Menampilkan semua customer yang tersedia dengan informasi lengkap termasuk sales team, status, dan platform. Data yang ditampilkan disesuaikan dengan role user (Sales hanya melihat customer sendiri, SPV melihat team, RO melihat yang ditangani, CRM melihat yang ditangani). Berguna untuk dropdown selection atau quick reference customer list.",
     *     tags={"Customer"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar customer yang tersedia",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array customer dengan informasi ringkas untuk selection",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="ro", type="string", example="RO-2025-001", description="Nomor RO (Routing Order)"),
     *                     @OA\Property(property="crm", type="string", example="CRM-2025-001", description="Nomor CRM"),
     *                     @OA\Property(property="tim_sales", type="string", example="Team Alpha", description="Nama tim sales"),
     *                     @OA\Property(property="sales", type="string", example="Ahmad Salesman", description="Nama sales person"),
     *                     @OA\Property(property="tim_sales_id", type="integer", example=1),
     *                     @OA\Property(property="tim_sales_d_id", type="integer", example=5),
     *                     @OA\Property(property="status_leads_id", type="integer", example=102),
     *                     @OA\Property(property="tgl_leads", type="string", format="date", example="2025-10-02"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Indonesia"),
     *                     @OA\Property(property="kebutuhan", type="string", example="Security", description="Jenis kebutuhan customer"),
     *                     @OA\Property(property="pic", type="string", example="John Doe", description="Person in Charge"),
     *                     @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="status", type="string", example="Customer", description="Status leads saat ini"),
     *                     @OA\Property(property="branch", type="string", example="Jakarta", description="Nama cabang"),
     *                     @OA\Property(property="platform", type="string", example="Website", description="Platform sumber"),
     *                     @OA\Property(property="warna_background", type="string", example="#28a745", description="Warna background badge status"),
     *                     @OA\Property(property="warna_font", type="string", example="#ffffff", description="Warna font badge status"),
     *                     @OA\Property(property="tgl", type="string", example="2 Oktober 2025", description="Tanggal leads terformat")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function availableCustomer(Request $request): JsonResponse
    {
        try {
            // Gunakan scope yang sudah diperbaiki
            $query = Leads::whereNotNull('customer_id')
                ->whereNull('deleted_at')
                ->with([
                    'statusLeads:id,nama,warna_background,warna_font',
                    'platform:id,nama',
                    'kebutuhan:id,nama',
                    'branch:id,name',
                    'timSales:id,nama',
                    'timSalesD:id,nama,tim_sales_id,user_id'
                ]);

            // Apply role-based filtering
            $query = $this->applyComplexRoleFilter($query);

            $leads = $query->get([
                'ro',
                'crm',
                'tim_sales_id',
                'tim_sales_d_id',
                'status_leads_id',
                'id',
                'tgl_leads',
                'nama_perusahaan',
                'kebutuhan_id',
                'pic',
                'no_telp',
                'email',
                'branch_id',
                'platform_id'
            ]);

            $data = $leads->map(function ($lead) {
                return [
                    'ro' => $lead->ro,
                    'crm' => $lead->crm,
                    'tim_sales' => $lead->timSales->nama ?? null,
                    'sales' => $lead->timSalesDetail->nama ?? null,
                    'tim_sales_id' => $lead->tim_sales_id,
                    'tim_sales_d_id' => $lead->tim_sales_d_id,
                    'status_leads_id' => $lead->status_leads_id,
                    'id' => $lead->id,
                    'tgl_leads' => $lead->tgl_leads,
                    'nama_perusahaan' => $lead->nama_perusahaan,
                    'kebutuhan' => $lead->kebutuhan->nama ?? null,
                    'pic' => $lead->pic,
                    'no_telp' => $lead->no_telp,
                    'email' => $lead->email,
                    'status' => $lead->statusLeads->nama ?? null,
                    'branch' => $lead->branch->name ?? null,
                    'platform' => $lead->platform->nama ?? null,
                    'warna_background' => $lead->statusLeads->warna_background ?? null,
                    'warna_font' => $lead->statusLeads->warna_font ?? null,
                    'tgl' => Carbon::parse($lead->tgl_leads)->isoFormat('D MMMM Y')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            \Log::error('Customer API Available Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Apply role-based filtering untuk endpoint list
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyRoleFilter($query)
    {
        $roleId = Auth::user()->cais_role_id;

        // Sales division filtering
        if (in_array($roleId, [29, 30, 31, 32, 33])) {
            if ($roleId == 29) { // Sales
                $query->whereHas('timSalesD', function ($q) {
                    $q->where('user_id', Auth::id());
                });
            } else if ($roleId == 31) { // SPV Sales
                $tim = TimSalesDetail::where('user_id', Auth::id())->first();
                if ($tim) {
                    $memberSales = TimSalesDetail::whereNull('deleted_at')
                        ->where('tim_sales_id', $tim->tim_sales_id)
                        ->pluck('user_id')
                        ->toArray();
                    $query->whereHas('timSalesDetail', function ($q) use ($memberSales) {
                        $q->whereIn('user_id', $memberSales);
                    });
                }
            }
            // Role 30, 32, 33 - no additional filter
        }
        // RO division
        else if (in_array($roleId, [4, 5, 6, 8])) {
            if (in_array($roleId, [4, 5])) {
                $query->where('ro_id', Auth::id());
            }
        }
        // CRM division
        else if (in_array($roleId, [54, 55, 56])) {
            if ($roleId == 54) {
                $query->where('crm_id', Auth::id());
            }
        }

        return $query;
    }

    /**
     * Apply complex role filtering untuk endpoint availableCustomer
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyComplexRoleFilter($query)
    {
        $roleId = Auth::user()->cais_role_id;

        // divisi sales
        if (in_array($roleId, [29, 30, 31, 32, 33])) {
            if ($roleId == 29) { // sales
                $query->whereHas('timSalesD', function ($q) {
                    $q->where('user_id', Auth::id());
                });
            } else if ($roleId == 31) { // spv sales
                $tim = TimSalesDetail::where('user_id', Auth::id())->first();
                if ($tim) {
                    $memberSales = TimSalesDetail::whereNull('deleted_at')
                        ->where('tim_sales_id', $tim->tim_sales_id)
                        ->pluck('user_id')
                        ->toArray();
                    $query->whereHas('timSalesDetail', function ($q) use ($memberSales) {
                        $q->whereIn('user_id', $memberSales);
                    });
                }
            }
            // Role 30, 32, 33 - no filter
        }
        // divisi RO
        else if (in_array($roleId, [4, 5, 6, 8])) {
            if (in_array($roleId, [4, 5])) {
                $query->where('ro_id', Auth::id());
            }
        }
        // divisi crm
        else if (in_array($roleId, [54, 55, 56])) {
            if ($roleId == 54) {
                $query->where('crm_id', Auth::id());
            }
        }

        return $query;
    }

    /**
     * Check view permission seperti original
     * 
     * @param mixed $data
     * @param mixed $tim
     * @return bool
     */
    private function checkViewPermission($data, $tim): bool
    {
        if (Auth::user()->cais_role_id == 29) {
            return $data->tim_sales_d_id == $tim->id;
        }
        return true;
    }
}