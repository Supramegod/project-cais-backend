<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Benua;
use App\Models\BidangPerusahaan;
use App\Models\City;
use App\Models\District;
use App\Models\JabatanPic;
use App\Models\JenisPerusahaan;
use App\Models\Kebutuhan;
use App\Models\Leads;
use App\Models\Branch;
use App\Models\LeadsKebutuhan;
use App\Models\Negara;
use App\Models\Pks;
use App\Models\Province;
use App\Models\SalesActivity;
use App\Models\Spk;
use App\Models\StatusLeads;
use App\Models\Platform;
use App\Models\TimSalesDetail;
use App\Models\CustomerActivity;
use App\Models\Village;
use App\Rules\UniqueCompanyStrict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
/**
 * @OA\Tag(
 *     name="Leads",
 *     description="API untuk manajemen data Leads (prospek pelanggan potensial yang belum menjadi customer)"
 * )
 */

class LeadsController extends Controller
{


    /**
     * @OA\Get(
     *     path="/api/leads/list",
     *     summary="Mendapatkan daftar semua leads",
     *     description="Endpoint ini digunakan untuk mengambil daftar leads dengan berbagai filter seperti tanggal, branch, platform, dan status. Default menampilkan leads hari ini. User dengan role Sales (29) hanya dapat melihat leads mereka sendiri.",
     *     tags={"Leads"},
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
                ->where('status_leads_id', '!=', 102);

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

            $transformedData = $data->getCollection()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nomor' => $item->nomor,
                    'wilayah' => $item->branch->name ?? '-',
                    'wilayah_id' => $item->branch_id,
                    'tgl_leads' => Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y'),
                    'sales' => $item->timSalesD->nama ?? '-',
                    'nama_perusahaan' => $item->nama_perusahaan,
                    'telp_perusahaan' => $item->telp_perusahaan,
                    'provinsi' => $item->provinsi,
                    'kota' => $item->kota,
                    'no_telp' => $item->no_telp,
                    'email' => $item->email,
                    'status_leads' => $item->statusLeads->nama ?? '-',
                    'status_leads_id' => $item->status_leads_id,
                    'sumber_leads' => $item->platform->nama ?? '-',
                    'sumber_leads_id' => $item->platform_id,
                    'created_by' => $item->created_by,
                    'notes' => $item->notes,
                    'kebutuhan' => $item->leadsKebutuhan->map(function ($lk) {
                        return [
                            'id' => $lk->kebutuhan_id,
                            'nama' => $lk->kebutuhan->nama ?? '-',
                            'tim_sales_d_id' => $lk->tim_sales_d_id,
                            'sales_name' => $lk->timSalesD->nama ?? '-'
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

    private function canViewLead($lead, $tim)
    {
        if (Auth::user()->cais_role_id == 29) {
            return $tim && $lead->tim_sales_d_id == $tim->id;
        }
        return true;
    }

    /**
     * @OA\Get(
     *     path="/api/leads/view/{id}",
     *     summary="Mendapatkan detail lead berdasarkan ID",
     *     description="Endpoint ini digunakan untuk mengambil informasi lengkap sebuah lead termasuk data perusahaan, PIC, kebutuhan, perusahaan group, dan 5 aktivitas terakhir. Hanya menampilkan lead yang belum menjadi customer.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID unik dari lead yang ingin dilihat detailnya",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil detail lead",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail lead berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="lead", type="object"),
     *                 @OA\Property(
     *                     property="perusahaan_groups",
     *                     type="array",
     *                     description="Daftar grup perusahaan yang memiliki lead ini",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="group_id", type="integer", example=1),
     *                         @OA\Property(property="nama_grup", type="string", example="Grup ABC"),
     *                         @OA\Property(property="jumlah_perusahaan", type="integer", example=5),
     *                         @OA\Property(property="created_at", type="string", example="01-01-2025")
     *                     )
     *                 ),
     *                 @OA\Property(property="activities", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Lead tidak ditemukan"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal Server Error")
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
                'company',
                'groupDetails'
            ])->whereNull('customer_id')->find($id);

            if (!$lead) {
                return response()->json(['success' => false, 'message' => 'Lead tidak ditemukan'], 404);
            }

            $lead->stgl_leads = Carbon::parse($lead->tgl_leads)->isoFormat('D MMMM Y');
            $lead->screated_at = Carbon::parse($lead->created_at)->isoFormat('D MMMM Y');
            $lead->kebutuhan_array = $lead->kebutuhan_id ? array_map('trim', explode(',', $lead->kebutuhan_id)) : [];


            return response()->json([
                'success' => true,
                'message' => 'Detail lead berhasil diambil',
                'data' => $lead,

            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/leads/add",
     *     summary="Membuat lead baru dengan assign sales ke kebutuhan",
     *     description="Endpoint ini digunakan untuk membuat data lead baru. Sistem akan melakukan validasi nama perusahaan untuk mencegah duplikasi, generate nomor lead otomatis, membuat aktivitas pertama, dan assign sales ke kebutuhan berdasarkan role user:
     *                 - User role 29 (Sales): Auto assign ke semua kebutuhan
     *                 - User role 30,31,32,33,53,96: Bisa manual assign multiple sales ke multiple kebutuhan
     *                 - User lainnya: Hanya sync kebutuhan tanpa sales",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_perusahaan","pic","branch","kebutuhan","provinsi","kota"},
     *             @OA\Property(property="nama_perusahaan", type="string", minLength=3, maxLength=100, example="PT ABC Indonesia", description="Nama perusahaan leads"),
     *             @OA\Property(property="telp_perusahaan", type="string", example="021-1234567", description="Nomor telepon perusahaan"),
     *             @OA\Property(property="jenis_perusahaan", type="integer", example=1, description="ID jenis perusahaan (PT, CV, UD, dll)"),
     *             @OA\Property(property="bidang_perusahaan", type="integer", example=1, description="ID bidang usaha perusahaan"),
     *             @OA\Property(property="branch", type="integer", example=1, description="ID cabang yang menangani lead ini"),
     *             @OA\Property(property="platform", type="integer", example=1, description="ID platform sumber lead (website, referral, social media)"),
     *             @OA\Property(
     *                 property="kebutuhan", 
     *                 type="array", 
     *                 @OA\Items(type="integer"), 
     *                 example={1,2,3},
     *                 description="Array ID kebutuhan produk/layanan yang diinginkan (minimal 1)"
     *             ),
     *             @OA\Property(property="alamat_perusahaan", type="string", example="Jl. Sudirman No. 123, Jakarta", description="Alamat lengkap perusahaan"),
     *             @OA\Property(property="pic", type="string", example="John Doe", description="Nama Person In Charge / contact person"),
     *             @OA\Property(property="jabatan_pic", type="string", example="Manager Purchasing", description="Jabatan PIC di perusahaan"),
     *             @OA\Property(property="no_telp", type="string", example="08123456789", description="Nomor telepon/HP PIC"),
     *             @OA\Property(property="email", type="string", format="email", example="john@abc.com", description="Email PIC"),
     *             @OA\Property(property="pma", type="string", example="PMDN", description="Apakah perusahaan PMA (Penanaman Modal Asing)"),
     *             @OA\Property(property="detail_leads", type="string", example="Tertarik dengan produk A untuk kebutuhan X", description="Catatan/detail tambahan tentang lead"),
     *             @OA\Property(property="provinsi", type="integer", example=10, description="ID provinsi lokasi perusahaan"),
     *             @OA\Property(property="kota", type="integer", example=259, description="ID kota/kabupaten lokasi perusahaan"),
     *             @OA\Property(property="kecamatan", type="integer", example=3515170, description="ID kecamatan lokasi perusahaan"),
     *             @OA\Property(property="kelurahan", type="integer", example=9471083530, description="ID kelurahan/desa lokasi perusahaan"),
     *             @OA\Property(property="benua", type="integer", example=2, description="ID benua (untuk perusahaan luar negeri)"),
     *             @OA\Property(property="negara", type="integer", example=79, description="ID negara (untuk perusahaan luar negeri)"),
     *             @OA\Property(
     *                 property="assignments", 
     *                 type="array",
     *                 description="Array assignment sales ke kebutuhan (hanya untuk user role 30,31,32,33,53,96)",
     *                 @OA\Items(
     *                     @OA\Property(property="tim_sales_d_id", type="integer", example=1, description="ID Tim Sales Detail"),
     *                     @OA\Property(
     *                         property="kebutuhan_ids", 
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={1,2},
     *                         description="Array ID kebutuhan yang diassign ke sales ini"
     *                     )
     *                 ),
     *                 example={
     *                     {"tim_sales_d_id": 1, "kebutuhan_ids": {1,2}},
     *                     {"tim_sales_d_id": 2, "kebutuhan_ids": {3,4}}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leads PT ABC Indonesia berhasil disimpan dengan nomor: AAAAB"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="lead", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="AAAAB"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *                     @OA\Property(property="tgl_leads", type="string", example="2024-01-15 10:30:00"),
     *                     @OA\Property(property="tim_sales_id", type="integer", example=1),
     *                     @OA\Property(property="tim_sales_d_id", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="array",
     *                     description="Detail assignment sales yang berhasil dilakukan",
     *                     @OA\Items(
     *                         @OA\Property(property="type", type="string", example="auto_assign", description="auto_assign atau manual_assign"),
     *                         @OA\Property(property="sales_assigned", type="object",
     *                             @OA\Property(property="tim_sales_d_id", type="integer", example=1),
     *                             @OA\Property(property="sales_name", type="string", example="John Sales"),
     *                             @OA\Property(property="tim_sales_id", type="integer", example=1),
     *                             @OA\Property(property="tim_sales_name", type="string", example="Tim Sales Jakarta")
     *                         ),
     *                         @OA\Property(
     *                             property="kebutuhan_assigned",
     *                             type="array",
     *                             @OA\Items(type="integer"),
     *                             example={1,2,3}
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error atau nama perusahaan terlalu mirip",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="nama_perusahaan", type="array", @OA\Items(type="string", example="Masukkan nama_perusahaan minimal 3")),
     *                 @OA\Property(property="kebutuhan", type="array", @OA\Items(type="string", example="Kebutuhan harus dipilih minimal 1"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
    public function add(Request $request)
    {
        try {
            set_time_limit(0);
            DB::beginTransaction();

            // // Debug: Test if UniqueCompanyStrict class exists
            // if (!class_exists(UniqueCompanyStrict::class)) {
            //     \Log::error('UniqueCompanyStrict class not found');
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'UniqueCompanyStrict class not found'
            //     ], 500);
            // }

            // // Debug: Test instantiation
            // try {
            //     $testRule = new UniqueCompanyStrict();
            //     \Log::info('UniqueCompanyStrict instantiated successfully');
            // } catch (\Exception $e) {
            //     \Log::error('Failed to instantiate UniqueCompanyStrict: ' . $e->getMessage());
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Failed to instantiate UniqueCompanyStrict: ' . $e->getMessage()
            //     ], 500);
            // }

            $validator = Validator::make($request->all(), [
                'nama_perusahaan' => ['required', 'max:100', 'min:3', new UniqueCompanyStrict()],
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
                \Log::info('Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->toArray()
                ], 400);
            }

            // \Log::info('Validation passed', [
            //     'nama_perusahaan' => $request->nama_perusahaan
            // ]);
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

            // Create lead using model
            $lead = Leads::create([
                'nomor' => $nomor,
                'tgl_leads' => $current_date_time,
                'nama_perusahaan' => strtoupper($request->nama_perusahaan),
                'telp_perusahaan' => $request->telp_perusahaan,
                'jenis_perusahaan_id' => $request->jenis_perusahaan,
                'jenis_perusahaan' => $jenisPerusahaan ? $jenisPerusahaan->nama : null,
                'bidang_perusahaan_id' => $request->bidang_perusahaan,
                'bidang_perusahaan' => $bidangPerusahaan ? $bidangPerusahaan->nama : null,
                'branch_id' => $request->branch,
                'platform_id' => $request->platform,
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
            $activity = CustomerActivity::create([
                'leads_id' => $lead->id,
                'branch_id' => $request->branch,
                'tgl_activity' => $current_date_time,
                'nomor' => $nomorActivity,
                'notes' => 'Leads Terbentuk' .
                    (!empty($assignmentResults) ? ' dengan assignment sales' : ''),
                'tipe' => 'Leads',
                'status_leads_id' => 1,
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name
            ]);

            // Update activity dengan sales info jika ada
            if ($lead->tim_sales_d_id) {
                $activity->update([
                    'tim_sales_id' => $lead->tim_sales_id,
                    'tim_sales_d_id' => $lead->tim_sales_d_id
                ]);
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Leads ' . $request->nama_perusahaan . ' berhasil disimpan',
                'data' => [
                    'lead' => $lead,
                    'assignments' => $assignmentResults
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Put(
     *     path="/api/leads/update/{id}",
     *     summary="Mengupdate data lead yang sudah ada dengan assignment sales",
     *     description="Endpoint ini digunakan untuk mengupdate informasi lead yang sudah ada berdasarkan ID. Semua field yang dikirim akan diupdate. Assignment sales dilakukan berdasarkan role user:
     *                 - User role 29 (Sales): Auto assign jika lead belum memiliki sales
     *                 - User role 30,31,32,33,53,96: Bisa manual assign multiple sales ke multiple kebutuhan
     *                 - User lainnya: Hanya sync kebutuhan dengan mempertahankan sales existing",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead yang akan diupdate",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_perusahaan","pic","branch","kebutuhan","provinsi","kota"},
     *             @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *             @OA\Property(property="telp_perusahaan", type="string", example="021-1234567"),
     *             @OA\Property(property="jenis_perusahaan", type="integer", example=1),
     *             @OA\Property(property="bidang_perusahaan", type="integer", example=1),
     *             @OA\Property(property="branch", type="integer", example=1),
     *             @OA\Property(property="platform", type="integer", example=1),
     *             @OA\Property(
     *                 property="kebutuhan", 
     *                 type="array", 
     *                 @OA\Items(type="integer"), 
     *                 example={1,2,3},
     *                 description="Array ID kebutuhan produk/layanan"
     *             ),
     *             @OA\Property(property="alamat_perusahaan", type="string", example="Jl. Sudirman No. 123"),
     *             @OA\Property(property="pic", type="string", example="John Doe"),
     *             @OA\Property(property="jabatan_pic", type="string", example="Manager"),
     *             @OA\Property(property="no_telp", type="string", example="08123456789"),
     *             @OA\Property(property="email", type="string", example="john@abc.com"),
     *             @OA\Property(property="pma", type="string", example="Yes"),
     *             @OA\Property(property="detail_leads", type="string", example="Update: tertarik demo produk"),
     *             @OA\Property(property="provinsi", type="integer", example=11),
     *             @OA\Property(property="kota", type="integer", example=1101),
     *             @OA\Property(property="kecamatan", type="integer", example=110101),
     *             @OA\Property(property="kelurahan", type="integer", example=11010101),
     *             @OA\Property(property="benua", type="integer", example=1),
     *             @OA\Property(property="negara", type="integer", example=1),
     *             @OA\Property(
     *                 property="assignments", 
     *                 type="array",
     *                 description="Array assignment sales ke kebutuhan (hanya untuk user role 30,31,32,33,53,96)",
     *                 @OA\Items(
     *                     @OA\Property(property="tim_sales_d_id", type="integer", example=1, description="ID Tim Sales Detail"),
     *                     @OA\Property(
     *                         property="kebutuhan_ids", 
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={1,2},
     *                         description="Array ID kebutuhan yang diassign ke sales ini"
     *                     )
     *                 ),
     *                 example={
     *                     {"tim_sales_d_id": 1, "kebutuhan_ids": {1,2}},
     *                     {"tim_sales_d_id": 2, "kebutuhan_ids": {3,4}}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leads PT ABC Indonesia berhasil diupdate"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="lead", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="AAAAB"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *                     @OA\Property(property="tim_sales_id", type="integer", example=1),
     *                     @OA\Property(property="tim_sales_d_id", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="assignments",
     *                     type="array",
     *                     description="Detail assignment sales yang berhasil dilakukan",
     *                     @OA\Items(
     *                         @OA\Property(property="type", type="string", example="manual_assign"),
     *                         @OA\Property(property="sales_assigned", type="object",
     *                             @OA\Property(property="tim_sales_d_id", type="integer", example=1),
     *                             @OA\Property(property="sales_name", type="string", example="John Sales"),
     *                             @OA\Property(property="tim_sales_id", type="integer", example=1),
     *                             @OA\Property(property="tim_sales_name", type="string", example="Tim Sales Jakarta")
     *                         ),
     *                         @OA\Property(
     *                             property="kebutuhan_assigned",
     *                             type="array",
     *                             @OA\Items(type="integer"),
     *                             example={1,2}
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lead tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lead tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
     * 
     * )
     */
    public function update(Request $request, $id)
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

            $validator = Validator::make($request->all(), [
                'nama_perusahaan' => ['sometimes', 'max:100', 'min:3', new UniqueCompanyStrict($id)],
                'pic' => 'required',
                'branch' => 'required',
                'kebutuhan' => 'required|array|min:1',
                'provinsi' => 'required',
                'kota' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->toArray()
                ], 400);
            }

            $current_date_time = Carbon::now()->toDateTimeString();

            $provinsi = Province::find($request->provinsi);
            $kota = City::find($request->kota);
            $kecamatan = District::find($request->kecamatan);
            $kelurahan = Village::find($request->kelurahan);
            $benua = Benua::find($request->benua);
            $negara = Negara::find($request->negara);
            $jenisPerusahaan = JenisPerusahaan::find($request->jenis_perusahaan);
            $bidangPerusahaan = BidangPerusahaan::find($request->bidang_perusahaan);

            $lead->update([
                'nama_perusahaan' => $request->nama_perusahaan ?? $lead->nama_perusahaan,
                'telp_perusahaan' => $request->telp_perusahaan,
                'jenis_perusahaan_id' => $request->jenis_perusahaan,
                'jenis_perusahaan' => $jenisPerusahaan ? $jenisPerusahaan->nama : null,
                'bidang_perusahaan_id' => $request->bidang_perusahaan,
                'bidang_perusahaan' => $bidangPerusahaan ? $bidangPerusahaan->nama : null,
                'branch_id' => $request->branch,
                'platform_id' => $request->platform,
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
            $activity = CustomerActivity::create([
                'leads_id' => $lead->id,
                'branch_id' => $request->branch,
                'tgl_activity' => $current_date_time,
                'nomor' => $nomorActivity,
                'notes' => 'Leads Diupdate' .
                    (!empty($assignmentResults) ? ' dengan assignment sales' : ''),
                'tipe' => 'Leads',
                'status_leads_id' => $lead->status_leads_id,
                'is_activity' => 0,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name
            ]);

            // Update activity dengan sales info jika ada
            if ($lead->tim_sales_d_id) {
                $activity->update([
                    'tim_sales_id' => $lead->tim_sales_id,
                    'tim_sales_d_id' => $lead->tim_sales_d_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leads ' . $request->nama_perusahaan . ' berhasil diupdate',
                'data' => [
                    'lead' => $lead,
                    'assignments' => $assignmentResults
                ]
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
     * @OA\Delete(
     *     path="/api/leads/delete/{id}",
     *     summary="Menghapus lead (soft delete)",
     *     description="Endpoint ini melakukan soft delete pada lead, artinya data tidak benar-benar dihapus dari database tetapi ditandai sebagai deleted. Data yang dihapus masih bisa di-restore kembali. Sistem akan mencatat siapa yang menghapus dan kapan.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead yang akan dihapus",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leads PT ABC Indonesia berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lead tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lead tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Leads ' . $lead->nama_perusahaan . ' berhasil dihapus beserta kebutuhan terkait'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/leads/restore/{id}",
     *     summary="Mengembalikan lead yang telah dihapus",
     *     description="Endpoint ini digunakan untuk melakukan restore/mengembalikan lead yang sudah di-soft delete sebelumnya. Lead yang di-restore akan kembali muncul di daftar leads aktif dan dapat digunakan kembali.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead yang akan di-restore",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead berhasil di-restore",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leads PT ABC Indonesia berhasil direstore")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lead tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lead tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
            LeadsKebutuhan::onlyTrashed()
                ->where('leads_id', $id)
                ->update([
                    'deleted_at' => null, // Me-restore record
                    'deleted_by' => null, // Mengosongkan kolom kustom
                ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Leads ' . $lead->nama_perusahaan . ' berhasil direstore beserta kebutuhan terkait'
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
     *     summary="Mendapatkan daftar semua leads yang telah dihapus (soft delete)",
     *     description="Endpoint ini digunakan untuk mengambil daftar leads yang telah dihapus secara soft delete. Data yang dihapus masih tersimpan di database tetapi tidak muncul di daftar leads aktif. Endpoint ini berguna untuk:
     *                 - Melihat riwayat leads yang pernah dihapus
     *                 - Memantau data yang mungkin terhapus secara tidak sengaja
     *                 - Memungkinkan restore data jika diperlukan",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data leads terhapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data leads terhapus berhasil diambil"),
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
     *                     @OA\Property(property="deleted_at", type="string", format="date-time"),
     *                     @OA\Property(property="deleted_by", type="string", example="Admin User"),
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
     *     path="/api/leads/child/{id}",
     *     summary="Mendapatkan daftar child leads dari parent lead tertentu",
     *     description="Endpoint ini digunakan untuk mengambil semua child leads yang terkait dengan parent lead tertentu. Child leads adalah leads cabang yang berada di bawah lead utama (parent). Berguna untuk:
     *                 - Melihat struktur hierarki perusahaan grup
     *                 - Memantau semua cabang/counterpart dari sebuah perusahaan utama
     *                 - Management leads yang kompleks dengan multiple lokasi",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID parent lead yang ingin dilihat child leads-nya",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data child leads",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data child leads berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="leads_id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="AAAAA"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Cabang Jakarta"),
     *                     @OA\Property(property="pic", type="string", example="John Doe"),
     *                     @OA\Property(property="no_telp", type="string", example="08123456789"),
     *                     @OA\Property(property="email", type="string", example="john@abc.com"),
     *                     @OA\Property(property="status_leads_id", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
     *     path="/api/leads/child/{id}",
     *     summary="Membuat child lead baru di bawah parent lead tertentu",
     *     description="Endpoint ini digunakan untuk membuat child lead (lead cabang) yang terkait dengan parent lead utama. Berguna untuk:
     *                 - Membuat leads untuk cabang perusahaan yang sama
     *                 - Management perusahaan grup dengan multiple entities
     *                 - Mempertahankan hubungan hierarki antara perusahaan induk dan cabang",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID parent lead yang akan menjadi induk dari child lead baru",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_perusahaan"},
     *             @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Cabang Surabaya", description="Nama perusahaan untuk child lead")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Child lead berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leads PT ABC Cabang Surabaya berhasil disimpan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="nomor", type="string", example="AAAAB"),
     *                 @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Cabang Surabaya"),
     *                 @OA\Property(property="leads_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Data tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Nama perusahaan harus diisi")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Parent lead tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Parent lead tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
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
     *     summary="Mendapatkan daftar leads yang belum diaktifkan",
     *     description="Endpoint ini digunakan untuk mengambil leads yang memiliki status belum aktif (is_aktif = null). Berguna untuk:
     *                 - Memantau leads baru yang belum diproses lebih lanjut
     *                 - Filter leads berdasarkan status aktivasi
     *                 - Management leads dalam tahap awal",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data leads belum aktif",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data leads belum aktif berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="AAAAA"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *                     @OA\Property(property="tgl_leads", type="string", example="1 Januari 2025"),
     *                     @OA\Property(property="is_aktif", type="string", example=null),
     *                     @OA\Property(
     *                         property="status_leads",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="nama", type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="kebutuhan",
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
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
     *     path="/api/leads/available-quotation",
     *     summary="Mendapatkan daftar leads yang tersedia untuk pembuatan quotation",
     *     description="Endpoint ini digunakan untuk mengambil leads yang memenuhi syarat untuk dibuatkan quotation. Hanya menampilkan leads parent (bukan child) dan difilter berdasarkan role user dengan aturan yang sama seperti available leads.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data leads untuk quotation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data leads tersedia untuk quotation berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="AAAAA"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *                     @OA\Property(property="tgl", type="string", example="1 Januari 2025"),
     *                     @OA\Property(property="leads_id", type="string", example=null),
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
     *                         property="kebutuhan",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="nama", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
    public function availableQuotation()
    {
        try {
            $user = Auth::user();

            // Gunakan scope dari model
            $query = Leads::with(['statusLeads', 'branch', 'kebutuhan', 'timSales', 'timSalesD'])
                ->availableForQuotation($user);

            $data = $query->get();

            $data->transform(function ($item) {
                $item->tgl = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
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
     * @OA\Post(
     *     path="/api/leads/activate/{id}",
     *     summary="Mengaktifkan lead yang belum aktif",
     *     description="Endpoint ini digunakan untuk mengubah status lead dari belum aktif menjadi aktif (is_aktif = 1). Berguna untuk:
     *                 - Mengaktifkan leads baru yang sudah siap diproses
     *                 - Memindahkan leads dari status 'belum aktif' ke pipeline sales aktif",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead yang akan diaktifkan",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead berhasil diaktifkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lead PT ABC Indonesia berhasil diaktifkan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lead tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lead tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
    /**
     * @OA\Post(
     *     path="/api/leads/import",
     *     summary="Mengimpor data leads dari file Excel",
     *     description="Endpoint ini digunakan untuk mengimpor data leads dalam jumlah banyak dari file Excel. Mendukung format CSV, XLS, dan XLSX. Berguna untuk:
     *                 - Migrasi data dari sistem lama
     *                 - Input data massal dari campaign marketing
     *                 - Backup dan restore data leads",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     description="File Excel berisi data leads",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import berhasil diproses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Import berhasil diproses")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validasi gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="file", type="array", @OA\Items(type="string", example="File harus berformat csv,xls,xlsx"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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

    public function import(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:csv,xls,xlsx',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Process import logic here
            return response()->json([
                'success' => true,
                'message' => 'Import berhasil diproses'
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
     *     path="/api/leads/export",
     *     summary="Mengekspor data leads ke file Excel",
     *     description="Endpoint ini digunakan untuk mengekspor data leads ke dalam format Excel. Berguna untuk:
     *                 - Backup data leads
     *                 - Analisis data di external tools
     *                 - Reporting dan audit",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Export berhasil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Export berhasil")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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

    public function exportExcel(Request $request)
    {
        try {
            // Export logic here
            return response()->json([
                'success' => true,
                'message' => 'Export berhasil'
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
     *     path="/api/leads/template-import",
     *     summary="Mendownload template untuk import data leads",
     *     description="Endpoint ini digunakan untuk mendownload template Excel yang sudah diformat untuk memudahkan import data leads. Template berisi kolom-kolom yang diperlukan dengan format yang benar.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Template berhasil diunduh",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Template berhasil diunduh")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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

    public function templateImport(Request $request)
    {
        try {
            // Template download logic
            return response()->json([
                'success' => true,
                'message' => 'Template berhasil diunduh'
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
     *     path="/api/leads/generate-null-kode",
     *     summary="Generate nomor untuk leads yang belum memiliki kode",
     *     description="Endpoint ini digunakan untuk generate nomor otomatis untuk semua leads yang belum memiliki nomor (null). Berguna untuk:
     *                 - Maintenance data leads yang sudah ada tetapi belum memiliki nomor
     *                 - Migrasi sistem lama ke baru
     *                 - Perbaikan data yang rusak",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Nomor berhasil digenerate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Nomor berhasil digenerate untuk semua leads yang belum memiliki nomor.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
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
    public function generateNullKode()
    {
        try {
            DB::beginTransaction();

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

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Nomor berhasil digenerate untuk semua leads yang belum memiliki nomor.'
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
     *     path="/api/leads/sales-kebutuhan/{id}",
     *     summary="Melihat sales yang diassign per kebutuhan untuk lead",
     *     description="Endpoint ini digunakan untuk melihat sales yang sudah diassign untuk setiap kebutuhan pada suatu lead. Satu sales bisa memiliki lebih dari satu kebutuhan.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead yang ingin dilihat sales per kebutuhannya",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data sales per kebutuhan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data sales per kebutuhan berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="sales_summary",
     *                     type="array",
     *                     description="Ringkasan sales dan kebutuhannya",
     *                     @OA\Items(
     *                         @OA\Property(property="tim_sales_d_id", type="integer", example=1),
     *                         @OA\Property(property="sales_name", type="string", example="John Doe"),
     *                         @OA\Property(property="kebutuhan_count", type="integer", example=2),
     *                         @OA\Property(
     *                             property="kebutuhan_list",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                                 @OA\Property(property="kebutuhan_nama", type="string", example="Kebutuhan A")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="detailed_data",
     *                     type="array",
     *                     description="Data detail setiap record",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="leads_id", type="integer", example=1),
     *                         @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                         @OA\Property(property="tim_sales_id", type="integer", example=1),
     *                         @OA\Property(property="tim_sales_d_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="kebutuhan",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="nama", type="string", example="Kebutuhan A")
     *                         ),
     *                         @OA\Property(
     *                             property="tim_sales_d",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="nama", type="string", example="Sales Name"),
     *                             @OA\Property(
     *                                 property="user",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="full_name", type="string", example="John Doe")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lead tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lead tidak ditemukan")
     *         )
     *     )
     * )
     */
    public function getSalesKebutuhan($id)
    {
        try {
            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }

            // Ambil semua data sales per kebutuhan
            $salesKebutuhan = LeadsKebutuhan::with([
                'kebutuhan:id,nama',
                'timSalesD.user:id,full_name',
                'timSalesD.timSales:id,nama'
            ])->where('leads_id', $id)->get();

            // Group by sales untuk summary
            $salesSummary = [];
            $groupedBySales = $salesKebutuhan->groupBy('tim_sales_d_id');

            foreach ($groupedBySales as $timSalesDId => $items) {
                if ($timSalesDId) {
                    $firstItem = $items->first();
                    $salesSummary[] = [
                        'tim_sales_d_id' => $timSalesDId,
                        'sales_name' => $firstItem->timSalesD->user->full_name ?? $firstItem->timSalesD->nama,
                        'tim_sales_name' => $firstItem->timSalesD->timSales->nama ?? 'N/A',
                        'kebutuhan_count' => $items->count(),
                        'kebutuhan_list' => $items->map(function ($item) {
                            return [
                                'kebutuhan_id' => $item->kebutuhan_id,
                                'kebutuhan_nama' => $item->kebutuhan->nama ?? 'N/A'
                            ];
                        })->toArray()
                    ];
                }
            }

            // Juga tampilkan kebutuhan yang belum diassign sales
            $unassignedKebutuhan = $salesKebutuhan->whereNull('tim_sales_d_id');
            if ($unassignedKebutuhan->count() > 0) {
                $salesSummary[] = [
                    'tim_sales_d_id' => null,
                    'sales_name' => 'Belum diassign',
                    'tim_sales_name' => 'N/A',
                    'kebutuhan_count' => $unassignedKebutuhan->count(),
                    'kebutuhan_list' => $unassignedKebutuhan->map(function ($item) {
                        return [
                            'kebutuhan_id' => $item->kebutuhan_id,
                            'kebutuhan_nama' => $item->kebutuhan->nama ?? 'N/A'
                        ];
                    })->toArray()
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Data sales per kebutuhan berhasil diambil',
                'data' => [
                    'sales_summary' => $salesSummary,
                    'detailed_data' => $salesKebutuhan
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
     *     path="/api/leads/available-sales/{id}",
     *     summary="Mendapatkan daftar tim sales yang available untuk diassign ke lead tertentu",
     *     description="Endpoint ini digunakan untuk mengambil daftar tim sales yang dapat diassign ke lead tertentu berdasarkan branch_id dari lead tersebut.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead yang akan diassign sales",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data tim sales",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data tim sales berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="John Sales"),
     *                     @OA\Property(property="tim_sales_id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=10),
     *                     @OA\Property(property="is_leader", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="full_name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com")
     *                     ),
     *                     @OA\Property(
     *                         property="tim_sales",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="Tim Sales Jakarta"),
     *                         @OA\Property(property="branch_id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="branch",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="nama", type="string", example="Jakarta")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Tidak memiliki akses"),
     *     @OA\Response(response=404, description="Lead tidak ditemukan"),
     *     @OA\Response(response=500, description="Internal Server Error")
     * )
     */
    public function availableSales($id)
    {
        try {
            $user = Auth::user();
            $allowedRoles = [30, 31, 32, 33, 53, 96, 2];

            // Cek apakah user memiliki akses
            if (!in_array($user->cais_role_id, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat daftar tim sales'
                ], 403);
            }

            // Cari lead untuk mendapatkan branch_id
            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }

            // Pastikan branch_id adalah integer
            if (!is_numeric($lead->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak memiliki branch_id yang valid'
                ], 400);
            }

            $query = TimSalesDetail::with([
                'user:id,full_name',
                'timSales:id,nama,branch_id',
                'timSales.branch:id,name'
            ]);

            // Filter berdasarkan role
            if ($user->cais_role_id == 31) { // Sales Leader
                // Dapatkan tim sales leader
                $leaderTim = TimSalesDetail::where('user_id', $user->id)
                    ->first();

                if ($leaderTim) {
                    // Hanya tampilkan anggota tim yang sama
                    $query->where('tim_sales_id', $leaderTim->tim_sales_id);
                } else {
                    // Jika leader tidak memiliki tim, return empty
                    return response()->json([
                        'success' => true,
                        'message' => 'Data tim sales berhasil diambil',
                        'data' => []
                    ]);
                }
            }

            // Filter berdasarkan branch_id dari lead
            $query->whereHas('timSales', function ($q) use ($lead) {
                $q->where('branch_id', (int) $lead->branch_id);
            });

            $sales = $query->get();

            // Transform data untuk response
            $salesData = $sales->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'tim_sales_id' => $item->tim_sales_id,
                    'user_id' => $item->user_id,
                    'is_leader' => (bool) $item->is_leader,
                    'is_active' => (bool) $item->is_active,
                    'user' => $item->user ? [
                        'id' => $item->user->id,
                        'full_name' => $item->user->full_name,
                    ] : null,
                    'tim_sales' => $item->timSales ? [
                        'id' => $item->timSales->id,
                        'nama' => $item->timSales->nama,
                        'branch_id' => $item->timSales->branch_id,
                        'branch' => $item->timSales->branch ? [
                            'id' => $item->timSales->branch->id,
                            'nama' => $item->timSales->branch->name
                        ] : null
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data tim sales berhasil diambil',
                'data' => $salesData,
                'debug' => [
                    'lead_id' => $lead->id,
                    'branch_id' => $lead->branch_id,
                    'total_sales' => $salesData->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    /**
     * @OA\Put(
     *     path="/api/leads/assign-sales/{id}",
     *     summary="Mengassign sales ke leads berdasarkan kebutuhan",
     *     description="Endpoint ini digunakan untuk memilih atau mengubah sales untuk suatu lead di tabel leads_kebutuhan. Satu sales bisa diassign ke multiple kebutuhan. Hanya user dengan cais_role_id 30, 31, 32, 33, 53, atau 96 yang dapat mengakses.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead yang akan diassign sales",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assignments"},
     *             @OA\Property(
     *                 property="assignments",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(
     *                         property="tim_sales_d_id", 
     *                         type="integer", 
     *                         example=1, 
     *                         description="ID dari Tim Sales Detail"
     *                     ),
     *                     @OA\Property(
     *                         property="kebutuhan_ids",
     *                         type="array",
     *                         @OA\Items(type="integer"),
     *                         example={1, 2},
     *                         description="Array ID kebutuhan yang akan diassign ke sales ini"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sales berhasil diassign ke kebutuhan lead",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sales berhasil diassign ke kebutuhan lead")
     *         )
     *     )
     * )
     */
    public function assignSales(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            // Cek lead
            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }

            // Cek authorization - hanya user dengan cais_role_id tertentu yang bisa assign sales
            $user = Auth::user();
            $allowedRoles = [30, 31, 32, 33, 53, 96, 2];

            if (!in_array($user->cais_role_id, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengassign sales'
                ], 403);
            }

            // Validasi input
            $validator = Validator::make($request->all(), [
                'assignments' => 'required|array|min:1',
                'assignments.*.tim_sales_d_id' => 'required|exists:m_tim_sales_d,id',
                'assignments.*.kebutuhan_ids' => 'required|array|min:1',
                'assignments.*.kebutuhan_ids.*' => 'exists:m_kebutuhan,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 400);
            }

            $assignmentResults = [];
            $allAssignedKebutuhan = [];
            $allAssignedKebutuhanNames = []; // Tambahkan array untuk menyimpan nama kebutuhan

            // Process each assignment
            foreach ($request->assignments as $assignment) {
                $timSalesD = TimSalesDetail::with('user', 'timSales')->find($assignment['tim_sales_d_id']);

                if (!$timSalesD) {
                    continue; // Skip jika sales tidak ditemukan
                }

                // Update atau buat record di leads_kebutuhan untuk setiap kebutuhan
                $assignedKebutuhan = [];
                foreach ($assignment['kebutuhan_ids'] as $kebutuhan_id) {
                    $leadsKebutuhan = LeadsKebutuhan::updateOrCreate(
                        [
                            'leads_id' => $lead->id,
                            'kebutuhan_id' => $kebutuhan_id
                        ],
                        [
                            'tim_sales_id' => $timSalesD->tim_sales_id,
                            'tim_sales_d_id' => $timSalesD->id
                        ]
                    );

                    $assignedKebutuhan[] = $kebutuhan_id;
                    $allAssignedKebutuhan[] = $kebutuhan_id;

                    // Ambil nama kebutuhan
                    $kebutuhan = Kebutuhan::find($kebutuhan_id);
                    if ($kebutuhan) {
                        $allAssignedKebutuhanNames[] = $kebutuhan->nama;
                    }
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

            // Buat activity untuk mencatat perubahan sales
            $nomorActivity = $this->generateNomorActivity($lead->id);
            CustomerActivity::create([
                'leads_id' => $lead->id,
                'branch_id' => $lead->branch_id,
                'tgl_activity' => Carbon::now()->toDateTimeString(),
                'nomor' => $nomorActivity,
                'notes' => $timSalesD->user->full_name . ' diassign ke kebutuhan: ' . implode(', ', array_unique($allAssignedKebutuhanNames)), // Gunakan nama kebutuhan
                'tipe' => 'Assignment',
                'status_leads_id' => $lead->status_leads_id,
                'is_activity' => 0,
                'user_id' => $user->id,
                'created_by' => $user->full_name
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sales berhasil diassign ke kebutuhan lead',
                'data' => [
                    'lead_id' => $lead->id,
                    'assignments' => $assignmentResults
                ]
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
     * @OA\Delete(
     *     path="/api/leads/remove-sales/{id}",
     *     summary="Menghapus assignment sales dari kebutuhan tertentu",
     *     description="Endpoint ini digunakan untuk menghapus assignment sales dari kebutuhan tertentu pada lead.",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"kebutuhan_ids"},
     *             @OA\Property(
     *                 property="kebutuhan_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2},
     *                 description="Array ID kebutuhan yang akan dihapus assignment sales-nya"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Assignment sales berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Assignment sales berhasil dihapus dari kebutuhan")
     *         )
     *     )
     * )
     */
    public function removeSales(Request $request, $id)
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

            // Validasi input
            $validator = Validator::make($request->all(), [
                'kebutuhan_ids' => 'required|array|min:1',
                'kebutuhan_ids.*' => 'exists:m_kebutuhan,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 400);
            }

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
                'created_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Assignment sales berhasil dihapus dari ' . $removedCount . ' kebutuhan',
                'data' => [
                    'lead_id' => $id,
                    'removed_kebutuhan' => $request->kebutuhan_ids,
                    'removed_count' => $removedCount
                ]
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
     *     path="/api/leads/spk/{id}",
     *     summary="Mendapatkan daftar SPK berdasarkan leads_id",
     *     description="Endpoint ini digunakan untuk mengambil semua SPK yang terkait dengan leads tertentu",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data SPK"
     *     )
     * )
     */
    public function getSpkByLead($id, Request $request)
    {
        try {
            // Validasi leads_id
            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }
            $spkData = Spk::with('statusSpk')
                ->byLeadsId($id)
                ->select('id', 'nomor', 'leads_id', 'tgl_spk', 'status_spk_id')
                ->orderBy('tgl_spk', 'desc')
                ->get();

            // Di sini kita bisa menggunakan `map` untuk menambahkan accessor dan menghilangkan relasi
            $spkData = $spkData->map(function ($item) {
                $data = $item->toArray();
                // Tambahkan nama status menggunakan accessor yang baru dibuat
                $data['nama_status'] = $item->nama_status;

                // Hapus objek relasi statusSpk (opsional, jika Anda hanya mau nama status)
                unset($data['status_spk']);

                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data SPK berhasil diambil',
                'data' => $spkData, // <-- Menggunakan data yang sudah di-map
                'summary' => Spk::getSummaryByLeadsId($id)
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
     *     path="/api/leads/pks/{id}",
     *     summary="Mendapatkan daftar PKS berdasarkan leads_id",
     *     description="Endpoint ini digunakan untuk mengambil semua PKS yang terkait dengan leads tertentu",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data PKS"
     *     )
     * )
     */
    public function getPksByLead($id, Request $request)
    {
        try {
            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead tidak ditemukan'
                ], 404);
            }


            // GUNAKAN MODEL PKS LANGSUNG dengan scope byLeadsId
            // Logika query ADA DI MODEL PKS, bukan di controller
            $pksData = Pks::with('leads')
                ->byLeadsId($id)
                ->select('id', 'nomor', 'leads_id', 'tgl_pks', 'status_pks_id', 'kontrak_akhir') // <-- TAMBAH 'kontrak_akhir'
                ->orderBy('tgl_pks', 'desc')
                ->get();

            // 2. Map data dan tambahkan informasi tambahan
            $pksData = $pksData->map(function ($item) {
                $data = $item->toArray();
                $data['nama_status'] = $item->nama_status;

                // Hitung sisa kontrak menggunakan method di class ini
                $data['sisa_kontrak'] = $this->hitungBerakhirKontrak($item->kontrak_akhir);

                unset($data['leads']);

                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data PKS berhasil diambil',
                'data' => $pksData,
                'summary' => Pks::getSummaryByLeadsId($id) // Summary dari model Pks
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
     *     path="/api/leads/customeractivity/{id}",
     *     summary="Mendapatkan daftar aktivitas customer dan sales berdasarkan leads_id",
     *     description="Endpoint ini digunakan untuk mengambil semua aktivitas customer dan sales activity yang terkait dengan leads tertentu, diurutkan berdasarkan tanggal dan waktu",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID lead",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data aktivitas"
     *     )
     * )
     */
    public function getcustomeractivityByLead($id, Request $request)
    {
        try {
            // Ambil Customer Activity
            $customerActivities = CustomerActivity::with('leads')
                ->byLeadsId($id)
                ->whereNull('deleted_at')
                ->get()
                ->map(function ($act) {
                    $baseData = [
                        'id' => $act->id,
                        'source' => 'Customer Activity', // Menandai sumber data
                        'tipe' => $act->tipe,
                        'notes' => $act->notes_tipe ?? $act->notes ?? $act->notulen,
                        'tgl_activity' => $act->tgl_activity,
                        'created_by' => $act->created_by,
                        'created_at' => $act->getRawOriginal('created_at')  // Untuk sorting
                    ];

                    // Conditional fields berdasarkan tipe
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

            // Ambil Sales Activity
            $salesActivities = SalesActivity::with(['lead', 'leadsKebutuhan.kebutuhan'])
                ->where('leads_id', $id)
                ->get()
                ->map(function ($act) {
                    return [
                        'id' => $act->id,
                        'source' => 'Sales Activity', // Menandai sumber data
                        'tipe' => $act->jenis_activity,
                        'notes' => $act->notulen,
                        'tgl_activity' => $act->tgl_activity,
                        'created_by' => $act->created_by,
                        'created_at' => $act->getRawOriginal('created_at'), // Untuk sorting
                        'kebutuhan' => $act->leadsKebutuhan && $act->leadsKebutuhan->kebutuhan
                            ? $act->leadsKebutuhan->kebutuhan->nama
                            : null
                    ];
                });

            // Gabungkan kedua collection
            $allActivities = $customerActivities->merge($salesActivities);

            // Urutkan berdasarkan created_at (datetime) secara descending
            $allActivities = $allActivities->sortByDesc(function ($activity) {
                return Carbon::parse($activity['created_at']);
            })->values(); // Reset array keys

            return response()->json([
                'success' => true,
                'message' => 'Data aktivitas berhasil diambil',
                'data' => $allActivities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    //==============================================================================//
    private function hitungBerakhirKontrak($tanggalBerakhir)
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

            // Handle digits (0-9)
            if (is_numeric($current)) {
                if ($current < '9') {
                    $chars[$i] = (string) ($current + 1);
                    break;
                } else {
                    $chars[$i] = 'A'; // 9 -> A
                    break;
                }
            }

            // Handle letters (A-Z)
            if (ctype_alpha($current)) {
                if ($current < 'Z') {
                    $chars[$i] = chr(ord($current) + 1);
                    break;
                } else {
                    $chars[$i] = '0'; // Z -> 0 (carry over)
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

        if ($user->cais_role_id == 29) {
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
    private function manualAssignSalesToKebutuhan($lead, $assignments)
    {
        $user = Auth::user();
        $assignmentResults = [];
        $allowedRoles = [30, 31, 32, 33, 53, 96, 2];

        if (!in_array($user->cais_role_id, $allowedRoles)) {
            return $assignmentResults;
        }

        foreach ($assignments as $assignment) {
            $timSalesD = TimSalesDetail::with('user', 'timSales')->find($assignment['tim_sales_d_id']);

            if ($timSalesD) {
                // Update atau buat record di leads_kebutuhan untuk setiap kebutuhan
                foreach ($assignment['kebutuhan_ids'] as $kebutuhan_id) {
                    LeadsKebutuhan::updateOrCreate(
                        [
                            'leads_id' => $lead->id,
                            'kebutuhan_id' => $kebutuhan_id
                        ],
                        [
                            'tim_sales_id' => $timSalesD->tim_sales_id,
                            'tim_sales_d_id' => $timSalesD->id
                        ]
                    );
                }

                $assignmentResults[] = [
                    'type' => 'manual_assign',
                    'sales_assigned' => [
                        'tim_sales_d_id' => $timSalesD->id,
                        'sales_name' => $timSalesD->user->full_name ?? $timSalesD->nama,
                        'tim_sales_id' => $timSalesD->tim_sales_id,
                        'tim_sales_name' => $timSalesD->timSales->nama ?? 'N/A'
                    ],
                    'kebutuhan_assigned' => $assignment['kebutuhan_ids']
                ];
            }
        }

        // Update lead dengan sales dari assignment pertama (untuk konsistensi)
        if (!empty($assignments[0]['tim_sales_d_id'])) {
            $firstTimSalesD = TimSalesDetail::find($assignments[0]['tim_sales_d_id']);
            if ($firstTimSalesD) {
                $lead->update([
                    'tim_sales_id' => $firstTimSalesD->tim_sales_id,
                    'tim_sales_d_id' => $firstTimSalesD->id
                ]);
            }
        }

        return $assignmentResults;
    }

    /**
     * Sync kebutuhan tanpa assignment sales
     */
    private function syncKebutuhanTanpaSales($lead, $kebutuhanIds)
    {
        $lead->kebutuhan()->sync($kebutuhanIds);
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