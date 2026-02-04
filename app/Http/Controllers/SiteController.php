<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Leads;
use App\Models\QuotationSite;
use App\Models\CustomerActivity;
use App\Models\TimSalesDetail;
use App\Models\StatusLeads;
use App\Models\Branch;
use App\Models\Platform;
use App\Models\Kebutuhan;
use App\Models\TimSales;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Site",
 *     description="API Endpoints untuk mengelola Site"
 * )
 */
class SiteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/site/list",
     *     tags={"Site"},
     *     summary="Get list of all sites",
     *     description="Mengambil daftar semua site/lokasi kerja dari quotation yang terhubung dengan leads. Endpoint ini menampilkan informasi dasar site termasuk nama perusahaan, lokasi, status SPK dan kontrak. Data diurutkan berdasarkan ID terbaru.",
     *     operationId="getSiteList",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Filter tanggal dari (format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="Filter tanggal sampai (format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="branch",
     *         in="query",
     *         description="Filter branch ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Filter platform ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter status leads ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar site",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="nama_site", type="string", example="Site Jakarta Pusat"),
     *                     @OA\Property(property="provinsi", type="string", example="DKI Jakarta"),
     *                     @OA\Property(property="kota", type="string", example="Jakarta Pusat"),
     *                     @OA\Property(property="penempatan", type="string", example="Gedung Perkantoran Lt. 5"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z"),
     *                     @OA\Property(property="created_by", type="integer", example=1),
     *                     @OA\Property(property="spk", type="boolean", example=true, description="Status apakah sudah ada SPK"),
     *                     @OA\Property(property="kontrak", type="boolean", example=false, description="Status apakah sudah ada kontrak")
     *                 )
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
     *         description="Server error - Terjadi kesalahan saat mengambil data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve sites")
     *         )
     *     )
     * )
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $query = QuotationSite::with(['leads', 'spkSite', 'site'])
                ->whereHas('leads', function ($query) {
                    $query->whereNull('deleted_at');
                });

            // Apply filters like in web controller
            if (!empty($request->tgl_dari)) {
                $query->whereHas('leads', function ($q) use ($request) {
                    $q->where('tgl_leads', '>=', $request->tgl_dari);
                });
            }

            if (!empty($request->tgl_sampai)) {
                $query->whereHas('leads', function ($q) use ($request) {
                    $q->where('tgl_leads', '<=', $request->tgl_sampai);
                });
            }

            if (!empty($request->branch)) {
                $query->whereHas('leads', function ($q) use ($request) {
                    $q->where('branch_id', $request->branch);
                });
            }

            if (!empty($request->platform)) {
                $query->whereHas('leads', function ($q) use ($request) {
                    $q->where('platform_id', $request->platform);
                });
            }

            if (!empty($request->status)) {
                $query->whereHas('leads', function ($q) use ($request) {
                    $q->where('status_leads_id', $request->status);
                });
            }

            $data = $query->orderBy('id', 'desc')->get();

            $result = $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama_perusahaan' => $item->leads->nama_perusahaan ?? '',
                    'nama_site' => $item->nama_site,
                    'provinsi' => $item->provinsi,
                    'kota' => $item->kota,
                    'penempatan' => $item->penempatan,
                    'created_at' => $item->created_at,
                    'created_by' => $item->created_by,
                    'spk' => !is_null($item->spkSite),
                    'kontrak' => !is_null($item->site)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sites'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/site/view/{id}",
     *     tags={"Site"},
     *     summary="Get detailed information of a specific site",
     *     description="Mengambil detail lengkap dari site/leads tertentu berdasarkan ID. Endpoint ini menampilkan informasi leads yang sudah memiliki customer_id beserta 5 aktivitas customer terbaru yang terkait dengan leads tersebut. Berguna untuk melihat histori interaksi dengan customer di site tertentu.",
     *     operationId="getSiteDetail",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID dari leads/site yang ingin dilihat detailnya",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil detail site",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="leads",
     *                     type="object",
     *                     description="Data lengkap leads/site",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="stgl_leads", type="string", example="15 Januari 2024"),
     *                     @OA\Property(property="screated_at", type="string", example="15 Januari 2024"),
     *                 ),
     *                 @OA\Property(
     *                     property="activity",
     *                     type="array",
     *                     description="5 aktivitas customer terbaru",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="screated_at", type="string", example="15 Januari 2024 10:30"),
     *                         @OA\Property(property="stgl_activity", type="string", example="15 Januari 2024"),
     *                         @OA\Property(property="notes", type="string", example="Meeting dengan client"),
     *                         @OA\Property(property="tipe", type="string", example="meeting")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="master_data",
     *                     type="object",
     *                     description="Data master untuk form",
     *                     @OA\Property(
     *                         property="branch",
     *                         type="array",
     *                         @OA\Items(type="object")
     *                     ),
     *                     @OA\Property(
     *                         property="jabatan_pic",
     *                         type="array",
     *                         @OA\Items(type="object")
     *                     ),
     *                     @OA\Property(
     *                         property="jenis_perusahaan",
     *                         type="array",
     *                         @OA\Items(type="object")
     *                     ),
     *                     @OA\Property(
     *                         property="kebutuhan",
     *                         type="array",
     *                         @OA\Items(type="object")
     *                     ),
     *                     @OA\Property(
     *                         property="platform",
     *                         type="array",
     *                         @OA\Items(type="object")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Site tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Site not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve site details")
     *         )
     *     )
     * )
     */
    public function view($id): JsonResponse
    {
        try {
            $data = Leads::whereNotNull('customer_id')->find($id);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Site not found'
                ], 404);
            }

            // Format dates
            $data->stgl_leads = Carbon::parse($data->tgl_leads)->isoFormat('D MMMM Y');
            $data->screated_at = Carbon::parse($data->created_at)->isoFormat('D MMMM Y');

            // Get activities
            $activities = CustomerActivity::where('leads_id', $id)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($activity) {
                    return [
                        'screated_at' => Carbon::parse($activity->created_at)->isoFormat('D MMMM Y HH:mm'),
                        'stgl_activity' => Carbon::parse($activity->tgl_activity)->isoFormat('D MMMM Y'),
                        'notes' => $activity->notes,
                        'tipe' => $activity->tipe
                    ];
                });

            // Get master data like in web controller
            $masterData = [
                'branch' => DB::connection('mysqlhris')->table('m_branch')->where('is_active', 1)->get(),
                'jabatan_pic' => DB::table('m_jabatan_pic')->whereNull('deleted_at')->get(),
                'jenis_perusahaan' => DB::table('m_jenis_perusahaan')->whereNull('deleted_at')->get(),
                'kebutuhan' => DB::table('m_kebutuhan')->whereNull('deleted_at')->get(),
                'platform' => DB::table('m_platform')->whereNull('deleted_at')->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'leads' => $data,
                    'activity' => $activities,
                    'master_data' => $masterData
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve site details'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/site/available-customer",
     *     tags={"Site"},
     *     summary="Get list of available customers for site assignment",
     *     description="Mengambil daftar customer yang tersedia untuk penugasan site. Endpoint ini menampilkan leads yang sudah memiliki customer_id dan dapat difilter berdasarkan role user yang login. Sales hanya melihat leads mereka sendiri, SPV Sales melihat leads tim mereka, RO melihat leads yang ditugaskan ke mereka, dan CRM melihat leads yang menjadi tanggung jawab mereka. Data mencakup informasi lengkap customer beserta status, kebutuhan, dan tim sales yang menangani.",
     *     operationId="getAvailableCustomers",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar customer yang tersedia",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="tgl", type="string", example="15 Januari 2024", description="Tanggal leads dalam format bahasa Indonesia"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="kebutuhan", type="string", example="Security", description="Jenis layanan yang dibutuhkan"),
     *                     @OA\Property(property="pic", type="string", example="John Doe", description="Person In Charge dari customer"),
     *                     @OA\Property(property="no_telp", type="string", example="081234567890"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="status", type="string", example="Hot Prospect", description="Status leads saat ini"),
     *                     @OA\Property(property="branch", type="string", example="Jakarta", description="Cabang yang menangani"),
     *                     @OA\Property(property="platform", type="string", example="Website", description="Platform asal leads"),
     *                     @OA\Property(property="tim_sales", type="string", example="Tim A", description="Nama tim sales"),
     *                     @OA\Property(property="sales", type="string", example="Jane Smith", description="Nama sales person"),
     *                     @OA\Property(property="ro", type="string", example="RO Name", description="Regional Officer yang menangani"),
     *                     @OA\Property(property="crm", type="string", example="CRM Name", description="CRM staff yang menangani"),
     *                     @OA\Property(property="warna_background", type="string", example="#FFFFFF", description="Warna background status"),
     *                     @OA\Property(property="warna_font", type="string", example="#000000", description="Warna font status")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve available customers")
     *         )
     *     )
     * )
     */
    public function availableCustomer(): JsonResponse
    {
        try {
            $query = Leads::with(['statusLeads', 'branch', 'platform', 'kebutuhan', 'timSales', 'timSalesDetail'])
                ->whereNotNull('customer_id')
                ->whereNull('deleted_at');

            // Apply user role filters
            $this->applyUserFilters($query);

            $result = $query->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'tgl' => Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y'),
                    'nama_perusahaan' => $item->nama_perusahaan,
                    'kebutuhan' => $item->kebutuhan->nama ?? '',
                    'pic' => $item->pic,
                    'no_telp' => $item->no_telp,
                    'email' => $item->email,
                    'status' => $item->statusLeads->nama ?? '',
                    'branch' => $item->branch->name ?? '',
                    'platform' => $item->platform->nama ?? '',
                    'tim_sales' => $item->timSales->nama ?? '',
                    'sales' => $item->timSalesDetail->nama ?? '',
                    'ro' => $item->ro,
                    'crm' => $item->crm,
                    'warna_background' => $item->statusLeads->warna_background ?? '',
                    'warna_font' => $item->statusLeads->warna_font ?? ''
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available customers'
            ], 500);
        }
    }

    /**
     * Apply user role-based filters
     */
    private function applyUserFilters($query): void
    {
        $user = Auth::user();

        // Sales division
        if (in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            if ($user->cais_role_id == 29) { // Sales
                $query->whereHas('timSalesDetail', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } elseif ($user->cais_role_id == 31) { // SPV Sales
                $tim = TimSalesDetail::where('user_id', $user->id)->first();
                if ($tim) {
                    $memberIds = TimSalesDetail::where('tim_sales_id', $tim->tim_sales_id)
                        ->pluck('user_id');
                    $query->whereHas('timSalesDetail', function ($q) use ($memberIds) {
                        $q->whereIn('user_id', $memberIds);
                    });
                }
            }
        }

        // RO division
        elseif (in_array($user->cais_role_id, [4, 5, 6, 8])) {
            if (in_array($user->cais_role_id, [4, 5])) {
                $query->where('ro_id', $user->id);
            }
        }

        // CRM division
        elseif (in_array($user->cais_role_id, [54, 55, 56])) {
            if ($user->cais_role_id == 54) {
                $query->where('crm_id', $user->id);
            }
        }
    }
}