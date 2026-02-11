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
     *         name="tgl_dari",
     *         in="query",
     *         description="Filter tanggal mulai (format: YYYY-MM-DD). Jika kosong, akan mengambil data hari ini",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="Filter tanggal akhir (format: YYYY-MM-DD). Jika kosong, akan mengambil data hari ini",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="branch",
     *         in="query",
     *         description="Filter berdasarkan ID cabang/branch tertentu",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="platform",
     *         in="query",
     *         description="Filter berdasarkan ID platform sumber leads (misal: website, social media, referral)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter berdasarkan ID status leads (misal: new, contacted, qualified)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Pencarian berdasarkan nama perusahaan. Jika ada parameter search, filter tanggal akan diabaikan untuk mencari di semua data",
     *         required=false,
     *         @OA\Schema(type="string", example="PT ABC")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Jumlah data per halaman untuk pagination (default: 15)",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Nomor halaman untuk pagination (default: 1)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data leads",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data leads berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="AAAAA"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *                     @OA\Property(property="pic", type="string", example="John Doe"),
     *                     @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                     @OA\Property(property="email", type="string", example="john@abc.com"),
     *                     @OA\Property(property="tgl", type="string", example="1 Januari 2025"),
     *                     @OA\Property(property="tgl_leads", type="string", format="date-time"),
     *                     @OA\Property(property="can_view", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="status_leads",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="nama", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="branch",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="nama", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="platform",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="nama", type="string")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=75),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=15)
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
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: Error message")
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $query = Leads::select([
                'id',
                'nomor',
                'branch_id',
                'tgl_leads',
                'tim_sales_d_id',
                'nama_perusahaan',
                'telp_perusahaan',
                'provinsi',
                'kota',
                'no_telp',
                'email',
                'status_leads_id',
                'platform_id',
                'created_by',
                'notes',
                'created_at'
            ])
                ->with([
                    'statusLeads:id,nama',
                    'branch:id,name',
                    'platform:id,nama',
                    'timSalesD:id,nama',
                    'kebutuhan' => function ($q) {
                        $q->select('m_kebutuhan.id', 'm_kebutuhan.nama'); // sesuaikan nama tabel kebutuhan
                    },
                    'leadsKebutuhan.timSalesD:id,nama'
                ])
                ->whereNull('customer_id')
                ->where('status_leads_id', 102);

            // ✅ Gunakan scope yang sudah ada di model Leads.php
            $query->filterByUserRole();

            // ✅ Optimasi Search dengan Fulltext
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                // Jika mengandung spasi (kalimat), bungkus dengan tanda kutip untuk pencarian 'exact phrase'
                if (str_contains($searchTerm, ' ')) {
                    $searchTerm = '"' . $searchTerm . '"';
                } else {
                    $searchTerm = $searchTerm . '*';
                }

                $query->whereRaw("MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
            } else {
                $tglDari = $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString());
                $tglSampai = $request->get('tgl_sampai', Carbon::today()->toDateString());
                $query->whereBetween('tgl_leads', [$tglDari, $tglSampai]);
            }

            // Filter tambahan
            if ($request->filled('branch'))
                $query->where('branch_id', $request->branch);
            if ($request->filled('platform'))
                $query->where('platform_id', $request->platform);
            if ($request->filled('status'))
                $query->where('status_leads_id', $request->status);

            $data = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

            $transformedData = $data->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'nomor' => $item->nomor,
                    'wilayah' => $item->branch->name ?? null,
                    'wilayah_id' => $item->branch_id,
                    'tgl_leads' => Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y'),
                    'sales' => $item->timSalesD->nama ?? null,
                    'nama_perusahaan' => $item->nama_perusahaan,
                    'telp_perusahaan' => $item->telp_perusahaan,
                    'provinsi' => $item->provinsi,
                    'kota' => $item->kota,
                    'no_telp' => $item->no_telp,
                    'email' => $item->email,
                    'status_leads' => $item->statusLeads->nama ?? null,
                    'status_leads_id' => $item->status_leads_id,
                    'sumber_leads' => $item->platform->nama ?? null,
                    'sumber_leads_id' => $item->platform_id,
                    'created_by' => $item->created_by,
                    'notes' => $item->notes,
                    'kebutuhan' => $item->leadsKebutuhan->map(function ($lk) {
                        return [
                            'id' => $lk->kebutuhan_id,
                            'nama' => $lk->kebutuhan->nama ?? null,
                            'tim_sales_d_id' => $lk->tim_sales_d_id,
                            'sales_name' => $lk->timSalesD->nama ?? null
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data leads berhasil diambil',
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'total' => $data->total(),
                    'total_per_page' => $data->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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
                ])
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