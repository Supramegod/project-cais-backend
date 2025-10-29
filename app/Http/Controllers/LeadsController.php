<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Benua;
use App\Models\BidangPerusahaan;
use App\Models\City;
use App\Models\District;
use App\Models\JabatanPic;
use App\Models\JenisPerusahaan;
use App\Models\Leads;
use App\Models\Branch;
use App\Models\LeadsKebutuhan;
use App\Models\Negara;
use App\Models\Province;
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
            $tim = TimSalesDetail::where('user_id', Auth::id())->first();

            $query = Leads::with(['statusLeads', 'branch', 'platform', 'timSalesD', 'kebutuhan'])
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

            // 🧩 Tambahkan ini sebelum get()
            $query->orderBy('created_at', 'desc');

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
            'branch', 'kebutuhan', 'timSales', 'timSalesD', 
            'statusLeads', 'jenisPerusahaan', 'company', 'groupDetails'
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
            'data' => [
                'lead' => $lead,
                'activities' => CustomerActivity::where('leads_id', $id)
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(function($a) {
                        $a->screated_at = Carbon::parse($a->created_at)->isoFormat('D MMMM Y HH:mm');
                        $a->stgl_activity = Carbon::parse($a->tgl_activity)->isoFormat('D MMMM Y');
                        return $a;
                    })
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
    }
}

    /**
     * @OA\Post(
     *     path="/api/leads/add",
     *     summary="Membuat lead baru",
     *     description="Endpoint ini digunakan untuk membuat data lead baru. Sistem akan melakukan validasi nama perusahaan untuk mencegah duplikasi (similarity > 95%), generate nomor lead otomatis, membuat aktivitas pertama, dan assign ke tim sales jika user adalah sales. Juga mendukung pembuatan grup perusahaan baru.",
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
     *             @OA\Property(property="perusahaan_group_id", type="integer", example=1, description="ID grup perusahaan jika ada. Gunakan '__new__' untuk membuat grup baru"),
     *             @OA\Property(property="new_nama_grup", type="string", example="ABC Group", description="Nama grup baru jika perusahaan_group_id = '__new__'")
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
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="AAAAB"),
     *                 @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia")
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
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors(),
                    // 'errors' => $validator->errors()
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

            $nomor = $this->generateNomor();

            $lead = Leads::create([
                'nomor' => $nomor,
                'tgl_leads' => $current_date_time,
                'nama_perusahaan' => $request->nama_perusahaan,
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


            // Setelah $lead berhasil dibuat
            $lead->kebutuhan()->sync($request->kebutuhan);


            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Leads ' . $request->nama_perusahaan . ' berhasil disimpan',
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

    /**
     * @OA\Put(
     *     path="/api/leads/update/{id}",
     *     summary="Mengupdate data lead yang sudah ada",
     *     description="Endpoint ini digunakan untuk mengupdate informasi lead yang sudah ada berdasarkan ID. Semua field yang dikirim akan diupdate kecuali nomor lead, tanggal lead, dan status. Update dilakukan dalam database transaction untuk menjaga integritas data.",
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
     *             @OA\Property(property="kebutuhan", type="array", @OA\Items(type="integer"), example={1,2}),
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
     *             @OA\Property(property="negara", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leads PT ABC Indonesia berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
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
                'nama_perusahaan' => ['required', 'max:100', 'min:3', new UniqueCompanyStrict($id)],
                'pic' => 'required',
                'branch' => 'required',
                'kebutuhan' => 'required|array|min:1',
                'provinsi' => 'required',
                'kota' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors(),
                    // 'errors' => $validator->errors()
                ], 400);
            }

            $provinsi = Province::find($request->provinsi);
            $kota = City::find($request->kota);
            $kecamatan = District::find($request->kecamatan);
            $kelurahan = Village::find($request->kelurahan);
            $benua = Benua::find($request->benua);
            $negara = Negara::find($request->negara);
            $jenisPerusahaan = JenisPerusahaan::find($request->jenis_perusahaan);
            $bidangPerusahaan = BidangPerusahaan::find($request->bidang_perusahaan);

            $lead->update([
                'nama_perusahaan' => $request->nama_perusahaan,
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

            // Setelah $lead berhasil dibuat
            $lead->kebutuhan()->sync($request->kebutuhan);


            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Leads ' . $request->nama_perusahaan . ' berhasil diupdate',
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
     *     path="/api/leads/available",
     *     summary="Mendapatkan daftar leads yang tersedia untuk aktivitas",
     *     description="Endpoint ini digunakan untuk mengambil leads yang tersedia untuk dilakukan aktivitas sales selanjutnya. Data difilter berdasarkan role user:
     *                 - Sales (29): hanya melihat leads mereka sendiri
     *                 - Team Leader (31): melihat leads seluruh anggota tim
     *                 - RO (6,8): melihat semua leads
     *                 - CRM (54,55,56): melihat leads berdasarkan assignment CRM",
     *     tags={"Leads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data leads tersedia",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data leads tersedia berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="AAAAA"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *                     @OA\Property(property="tgl", type="string", example="1 Januari 2025"),
     *                     @OA\Property(property="salesEmail", type="string", example=""),
     *                     @OA\Property(property="branchManagerEmail", type="string", example=""),
     *                     @OA\Property(property="branchManager", type="string", example=""),
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
    public function availableLeads()
    {
        try {
            // Pastikan Anda telah mengimpor kelas Leads, DB, dan Auth (jika di luar controller/model)
            // use App\Models\Leads;
            // use Illuminate\Support\Facades\DB;
            // use Illuminate\Support\Facades\Auth;

            $user = auth()->user();
            $query = Leads::with(['statusLeads', 'branch', 'kebutuhan', 'timSales', 'timSalesD']);

            // Role-based filtering
            if (in_array($user->role_id, [29, 30, 31, 32, 33])) {
                // Role 29: Individual Sales (hanya melihat Leads mereka sendiri)
                if ($user->role_id == 29) {
                    // Filter Leads berdasarkan TimSalesDetail yang memiliki user_id sama dengan user yang login
                    $query->whereHas('timSalesD', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                }
                // Role 31: Sales Leader (melihat Leads tim mereka)
                elseif ($user->role_id == 31) {
                    // 1. Dapatkan TimSalesDetail untuk user leader
                    $timSalesDetail = TimSalesDetail::where('user_id', $user->id)->first();

                    if ($timSalesDetail) {
                        // 2. Dapatkan semua user_id anggota tim (termasuk leader itu sendiri)
                        $memberSalesUserIds = TimSalesDetail::where('tim_sales_id', $timSalesDetail->tim_sales_id)
                            ->pluck('user_id')
                            ->toArray();

                        // 3. Filter Leads yang dimiliki oleh anggota tim tersebut
                        $query->whereHas('timSalesD', function ($q) use ($memberSalesUserIds) {
                            $q->whereIn('user_id', $memberSalesUserIds);
                        });
                    } else {
                        // Jika leader tidak terdaftar di m_tim_sales_d, kembalikan Leads kosong
                        $query->whereRaw('1 = 0');
                    }
                }
            }
            // RO roles
            elseif (in_array($user->role_id, [6, 8])) {
                // Implementasi filter RO, jika ada (saat ini kosong)
            }
            // CRM roles
            elseif (in_array($user->role_id, [54, 55, 56])) {
                if ($user->role_id == 54) {
                    $query->where('crm_id', $user->id);
                }
            }

            $data = $query->get();

            // Transformasi data
            $data->transform(function ($item) {
                // Pastikan kolom 'tgl_leads' tersedia di model Leads
                $item->tgl = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
                // Properti ini mungkin perlu diisi dengan data dari relasi 'timSalesD' dan 'branch' jika diperlukan
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
            // Penanganan error
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
            $query = Leads::with(['statusLeads', 'branch', 'kebutuhan', 'timSales', 'timSalesD'])
                ->whereNull('leads_id');

            // Role-based filtering
            $user = auth()->user();
            if (in_array($user->role_id, [29, 30, 31, 32, 33])) {
                if ($user->role_id == 29) {
                    $query->whereHas('timSalesD', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                } elseif ($user->role_id == 31) {
                    $tim = DB::table('m_tim_sales_d')->where('user_id', $user->id)->first();
                    if ($tim) {
                        $memberSales = DB::table('m_tim_sales_d')
                            ->where('tim_sales_id', $tim->tim_sales_id)
                            ->pluck('user_id')
                            ->toArray();
                        $query->whereHas('timSalesD', function ($q) use ($memberSales) {
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
}