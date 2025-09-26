<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\CustomerActivity;
use App\Models\CustomerActivityFile;
use App\Models\Leads;
use App\Models\Pks;

/**
 * @OA\Tag(
 *     name="Customer Activity",
 *     description="Endpoints untuk manajemen aktivitas customer (BELUM JADI)"
 * )
 */
class CustomerActivityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/customer-activities/list",
     *     summary="Get list customer activities dengan filter",
     *     description="Mengambil daftar aktivitas customer dengan berbagai filter dan pagination",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Tanggal dari (format: Y-m-d), default: 3 bulan yang lalu",
     *         required=false,
     *         @OA\Schema(type="string", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="Tanggal sampai (format: Y-m-d), default: hari ini",
     *         required=false,
     *         @OA\Schema(type="string", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="branch",
     *         in="query",
     *         description="Filter by branch ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="user",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="kebutuhan",
     *         in="query",
     *         description="Filter by kebutuhan ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="tipe",
     *         in="query",
     *         description="Filter by tipe activity (Telepon, Email, Meeting, Visit)",
     *         required=false,
     *         @OA\Schema(type="string", example="Telepon")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Data aktivitas customer berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", 
     *                 type="array", 
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-092024-00001"),
     *                     @OA\Property(property="tgl_activity", type="string", format="date", example="2024-09-23"),
     *                     @OA\Property(property="tipe", type="string", example="Telepon"),
     *                     @OA\Property(property="leads_id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="branch", type="string", example="Jakarta Pusat"),
     *                     @OA\Property(property="kebutuhan", type="string", example="Laboratory Service"),
     *                     @OA\Property(property="sales", type="string", example="John Doe"),
     *                     @OA\Property(property="keterangan", type="string", example="Follow up penawaran"),
     *                     @OA\Property(property="notes", type="string", example="Customer tertarik dengan penawaran"),
     *                     @OA\Property(property="status_leads_id", type="integer", example=2),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-23T10:30:00.000000Z"),
     *                     @OA\Property(
     *                         property="leads",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                         @OA\Property(
     *                             property="branch",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="nama", type="string", example="Jakarta Pusat")
     *                         ),
     *                         @OA\Property(
     *                             property="kebutuhan",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="nama", type="string", example="Laboratory Service")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error - Tanggal tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Tanggal dari tidak boleh melebihi tanggal sampai.")
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
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server.")
     *         )
     *     )
     * )
     */
    public function list(Request $request): JsonResponse
    {
        try {
            // Set default tanggal jika tidak ada parameter
            $tglDari = $request->tgl_dari ?: Carbon::now()->subMonths(3)->startOfMonth()->toDateString();
            $tglSampai = $request->tgl_sampai ?: Carbon::now()->toDateString();

            // Validasi tanggal hanya jika kedua parameter ada
            if ($request->tgl_dari && $request->tgl_sampai) {
                if (Carbon::parse($tglDari)->gt(Carbon::parse($tglSampai))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tanggal dari tidak boleh melebihi tanggal sampai.'
                    ], 422);
                }
            }

            // Query dasar dengan eager loading
            $query = CustomerActivity::with([
                'leads.branch',
                'leads.kebutuhan',
                'timSalesDetail'
            ])->whereNull('deleted_at');

            // Filter tanggal - hanya diterapkan jika ada parameter tanggal
            if ($request->tgl_dari || $request->tgl_sampai) {
                $query->whereBetween('tgl_activity', [$tglDari, $tglSampai]);
            }

            // Apply filters - hanya jika parameter ada dan tidak null/empty
            if ($request->filled('branch')) {
                $query->whereHas('leads', function ($q) use ($request) {
                    $q->where('branch_id', $request->branch);
                });
            }

            if ($request->filled('kebutuhan')) {
                $query->whereHas('leads', function ($q) use ($request) {
                    $q->where('kebutuhan_id', $request->kebutuhan);
                });
            }

            if ($request->filled('tipe')) {
                $query->where('tipe', $request->tipe);
            }

            if ($request->filled('user')) {
                $query->where('user_id', $request->user);
            }

            // Filter berdasarkan role user - FIXED: Menambahkan logika yang benar
            $user = Auth::user();
            if (in_array($user->role_id, [29, 30, 31, 32, 33])) {
                // Logic filter untuk divisi sales - menampilkan hanya aktivitas user tersebut
                $query->where('user_id', $user->id);
            }

            // Order dan get data
            $activities = $query->orderBy('tgl_activity', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities,
                'meta' => [
                    'total' => $activities->count(),
                    'tgl_dari' => $tglDari,
                    'tgl_sampai' => $tglSampai,
                    'filters_applied' => [
                        'branch' => $request->branch,
                        'kebutuhan' => $request->kebutuhan,
                        'tipe' => $request->tipe,
                        'user' => $request->user
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in CustomerActivityController@list: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/customer-activities/view/{id}",
     *     summary="Get detail customer activity",
     *     description="Mengambil detail aktivitas customer berdasarkan ID termasuk file dan status leads",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID aktivitas customer",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Detail aktivitas customer",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", 
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-092024-00001"),
     *                 @OA\Property(property="tgl_activity", type="string", format="date", example="2024-09-23"),
     *                 @OA\Property(property="tipe", type="string", example="Telepon"),
     *                 @OA\Property(property="notes", type="string", example="Customer tertarik dengan penawaran"),
     *                 @OA\Property(property="start", type="string", example="09:00"),
     *                 @OA\Property(property="end", type="string", example="10:00"),
     *                 @OA\Property(property="durasi", type="integer", example=60),
     *                 @OA\Property(property="tgl_realisasi", type="string", format="date", example="2024-09-23"),
     *                 @OA\Property(property="jam_realisasi", type="string", example="09:30"),
     *                 @OA\Property(property="notulen", type="string", example="Notulen meeting dengan customer"),
     *                 @OA\Property(property="email", type="string", example="customer@example.com"),
     *                 @OA\Property(
     *                     property="leads",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="contact_person", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="phone", type="string", example="021-12345678")
     *                 ),
     *                 @OA\Property(
     *                     property="files",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_file", type="string", example="Notulen Meeting"),
     *                         @OA\Property(property="url_file", type="string", example="http://example.com/uploads/customer-activity/file.pdf"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="status_leads",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="nama", type="string", example="Follow Up")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found - Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan.")
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
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server.")
     *         )
     *     )
     * )
     */
    public function view($id): JsonResponse
    {
        try {
            $activity = CustomerActivity::with(['leads', 'files', 'statusLeads'])
                ->whereNull('deleted_at')
                ->find($id);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $activity
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/customer-activities/add",
     *     summary="Create new customer activity",
     *     description="Membuat aktivitas customer baru dengan opsi upload file",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data aktivitas customer baru",
     *         @OA\JsonContent(
     *             required={"leads_id", "tgl_activity", "tipe"},
     *             @OA\Property(property="leads_id", type="integer", example=1, description="ID leads yang terkait"),
     *             @OA\Property(property="tgl_activity", type="string", format="date", example="2024-07-01", description="Tanggal aktivitas"),
     *             @OA\Property(property="tipe", type="string", example="Telepon", description="Tipe aktivitas: Telepon, Email, Meeting, Visit"),
     *             @OA\Property(property="notes", type="string", example="Catatan aktivitas", description="Catatan atau keterangan aktivitas"),
     *             @OA\Property(property="tim_sales_id", type="integer", example=1, description="ID tim sales"),
     *             @OA\Property(property="tim_sales_d_id", type="integer", example=1, description="ID detail tim sales"),
     *             @OA\Property(property="status_leads_id", type="integer", example=1, description="ID status leads yang akan diupdate"),
     *             @OA\Property(property="start", type="string", example="09:00", description="Jam mulai aktivitas"),
     *             @OA\Property(property="end", type="string", example="10:00", description="Jam selesai aktivitas"),
     *             @OA\Property(property="durasi", type="integer", example=60, description="Durasi aktivitas dalam menit"),
     *             @OA\Property(property="tgl_realisasi", type="string", format="date", example="2024-07-01", description="Tanggal realisasi"),
     *             @OA\Property(property="jam_realisasi", type="string", example="09:30", description="Jam realisasi"),
     *             @OA\Property(property="jenis_visit_id", type="integer", example=1, description="ID jenis visit (jika tipe = Visit)"),
     *             @OA\Property(property="notulen", type="string", example="Notulen meeting", description="Notulen atau hasil meeting"),
     *             @OA\Property(property="email", type="string", format="email", example="email@example.com", description="Email customer"),
     *             @OA\Property(
     *                 property="files",
     *                 type="array",
     *                 description="Array file yang akan diupload",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="nama_file", type="string", example="Notulen Meeting"),
     *                     @OA\Property(property="file_content", type="string", example="base64EncodedFileContent", description="File content dalam format base64")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Success - Customer Activity berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer Activity berhasil dibuat dengan nomor: CAT/LS/LS001-092024-00001"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-092024-00001"),
     *                 @OA\Property(property="leads_id", type="integer", example=1),
     *                 @OA\Property(property="tgl_activity", type="string", format="date", example="2024-07-01"),
     *                 @OA\Property(property="tipe", type="string", example="Telepon"),
     *                 @OA\Property(property="notes", type="string", example="Catatan aktivitas"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error - Data tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="leads_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The leads id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="tgl_activity",
     *                     type="array",
     *                     @OA\Items(type="string", example="The tgl activity field is required.")
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
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server.")
     *         )
     *     )
     * )
     */
    public function add(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'leads_id' => 'required|exists:sl_leads,id',
                'tgl_activity' => 'required|date',
                'tipe' => 'required|string',
                'tgl_realisasi' => 'nullable|date',
                'files' => 'nullable|array',
                'files.*.nama_file' => 'required_with:files|string',
                'files.*.file_content' => 'required_with:files|string' // base64 encoded file
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $nomor = $this->generateNomor($request->leads_id);
            $current_date_time = Carbon::now();

            $activityData = $request->only([
                'leads_id',
                'tgl_activity',
                'tipe',
                'notes',
                'tim_sales_id',
                'tim_sales_d_id',
                'status_leads_id',
                'start',
                'end',
                'durasi',
                'tgl_realisasi',
                'jam_realisasi',
                'jenis_visit_id',
                'notulen',
                'email'
            ]);

            $activityData['nomor'] = $nomor;
            $activityData['branch_id'] = Leads::find($request->leads_id)->branch_id;
            $activityData['user_id'] = Auth::id();
            $activityData['created_by'] = Auth::user()->full_name;
            $activityData['created_at'] = $current_date_time;

            $activity = CustomerActivity::create($activityData);

            // Handle file uploads
            if ($request->has('files')) {
                foreach ($request->files as $fileData) {
                    $this->saveActivityFile($activity->id, $fileData);
                }
            }

            // Update status leads jika ada
            if ($request->status_leads_id) {
                Leads::where('id', $request->leads_id)
                    ->update(['status_leads_id' => $request->status_leads_id]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer Activity berhasil dibuat dengan nomor: ' . $nomor,
                'data' => $activity
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/customer-activities/update/{id}",
     *     summary="Update customer activity",
     *     description="Mengupdate aktivitas customer berdasarkan ID",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID aktivitas customer yang akan diupdate",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data yang akan diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="tgl_activity", type="string", format="date", example="2024-07-01"),
     *             @OA\Property(property="tipe", type="string", example="Email"),
     *             @OA\Property(property="notes", type="string", example="Catatan aktivitas updated"),
     *             @OA\Property(property="tim_sales_id", type="integer", example=2),
     *             @OA\Property(property="tim_sales_d_id", type="integer", example=2),
     *             @OA\Property(property="status_leads_id", type="integer", example=3),
     *             @OA\Property(property="start", type="string", example="10:00"),
     *             @OA\Property(property="end", type="string", example="11:00"),
     *             @OA\Property(property="durasi", type="integer", example=60),
     *             @OA\Property(property="tgl_realisasi", type="string", format="date", example="2024-07-02"),
     *             @OA\Property(property="jam_realisasi", type="string", example="10:30"),
     *             @OA\Property(property="jenis_visit_id", type="integer", example=2),
     *             @OA\Property(property="notulen", type="string", example="Updated notulen"),
     *             @OA\Property(property="email", type="string", format="email", example="newemail@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Customer Activity berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer Activity berhasil diupdate"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-092024-00001"),
     *                 @OA\Property(property="tgl_activity", type="string", format="date", example="2024-07-01"),
     *                 @OA\Property(property="tipe", type="string", example="Email"),
     *                 @OA\Property(property="notes", type="string", example="Catatan aktivitas updated"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_by", type="string", example="John Doe")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found - Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(property="errors", type="object")
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
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server.")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $activity = CustomerActivity::whereNull('deleted_at')->find($id);
            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'tgl_activity' => 'sometimes|required|date',
                'tipe' => 'sometimes|required|string',
                'tgl_realisasi' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->only([
                'tgl_activity',
                'tipe',
                'notes',
                'tim_sales_id',
                'tim_sales_d_id',
                'status_leads_id',
                'start',
                'end',
                'durasi',
                'tgl_realisasi',
                'jam_realisasi',
                'jenis_visit_id',
                'notulen',
                'email'
            ]);

            $updateData['updated_by'] = Auth::user()->full_name;
            $updateData['updated_at'] = Carbon::now();

            $activity->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer Activity berhasil diupdate',
                'data' => $activity
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/customer-activities/delete/{id}",
     *     summary="Delete customer activity",
     *     description="Menghapus aktivitas customer (soft delete)",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID aktivitas customer yang akan dihapus",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Customer Activity berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer Activity berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found - Data tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan.")
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
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server.")
     *         )
     *     )
     * )
     */
    public function delete($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $activity = CustomerActivity::whereNull('deleted_at')->find($id);
            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            $activity->update([
                'deleted_at' => Carbon::now(),
                'deleted_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer Activity berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customer-activities/leads/{leadsId}/track",
     *     summary="Track activities by leads ID",
     *     description="Mengambil riwayat aktivitas customer berdasarkan leads ID untuk tracking progress",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="leadsId",
     *         in="path",
     *         required=true,
     *         description="ID leads yang akan di-track aktivitasnya",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Data tracking aktivitas leads",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="leads",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="contact_person", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="phone", type="string", example="021-12345678"),
     *                     @OA\Property(property="alamat", type="string", example="Jl. Contoh No. 123"),
     *                     @OA\Property(
     *                         property="kebutuhan",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="Laboratory Service")
     *                     ),
     *                     @OA\Property(
     *                         property="branch",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="Jakarta Pusat")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="activities",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-092024-00001"),
     *                         @OA\Property(property="tgl_activity", type="string", format="date", example="2024-09-23"),
     *                         @OA\Property(property="tipe", type="string", example="Telepon"),
     *                         @OA\Property(property="notes", type="string", example="Customer tertarik dengan penawaran"),
     *                         @OA\Property(property="start", type="string", example="09:00"),
     *                         @OA\Property(property="end", type="string", example="10:00"),
     *                         @OA\Property(property="durasi", type="integer", example=60),
     *                         @OA\Property(property="tgl_realisasi", type="string", format="date", example="2024-09-23"),
     *                         @OA\Property(property="jam_realisasi", type="string", example="09:30"),
     *                         @OA\Property(property="notulen", type="string", example="Customer menunjukkan minat tinggi"),
     *                         @OA\Property(property="email", type="string", example="customer@example.com"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-23T10:30:00.000000Z"),
     *                         @OA\Property(property="created_by", type="string", example="Sales Manager"),
     *                         @OA\Property(
     *                             property="files",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="nama_file", type="string", example="Proposal Penawaran"),
     *                                 @OA\Property(property="url_file", type="string", example="http://example.com/uploads/customer-activity/proposal.pdf"),
     *                                 @OA\Property(property="created_at", type="string", format="date-time")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="status_leads",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="nama", type="string", example="Follow Up"),
     *                             @OA\Property(property="keterangan", type="string", example="Menunggu keputusan customer")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found - Leads tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Leads tidak ditemukan.")
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
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server.")
     *         )
     *     )
     * )
     */
    public function trackActivity($leadsId): JsonResponse
    {
        try {
            $activities = CustomerActivity::with(['files', 'statusLeads'])
                ->where('leads_id', $leadsId)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->get();

            $leads = Leads::with(['kebutuhan', 'branch'])->find($leadsId);

            if (!$leads) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leads tidak ditemukan.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'leads' => $leads,
                    'activities' => $activities
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * Generate nomor customer activity
     */
    private function generateNomor($leadsId): string
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        $prefix = "CAT/";
        if ($leads) {
            switch ($leads->kebutuhan_id) {
                case 2:
                    $prefix .= "LS/";
                    break;
                case 1:
                    $prefix .= "SG/";
                    break;
                case 3:
                    $prefix .= "CS/";
                    break;
                case 4:
                    $prefix .= "LL/";
                    break;
                default:
                    $prefix .= "NN/";
                    break;
            }
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
     * Save activity file
     */
    private function saveActivityFile($activityId, $fileData): void
    {
        // Handle base64 file upload
        if (isset($fileData['file_content'])) {
            $fileContent = base64_decode($fileData['file_content']);
            $fileName = $fileData['nama_file'] . '_' . time() . '.pdf'; // Adjust extension as needed

            Storage::disk('bukti-activity')->put($fileName, $fileContent);

            $fileUrl = env('APP_URL') . '/public/uploads/customer-activity/' . $fileName;

            CustomerActivityFile::create([
                'customer_activity_id' => $activityId,
                'nama_file' => $fileData['nama_file'],
                'url_file' => $fileUrl,
                'created_by' => Auth::user()->full_name,
                'created_at' => Carbon::now()
            ]);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/customer-activities/send-email",
     *     summary="Send email notification",
     *     description="Mengirim email notifikasi terkait customer activity",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"subject", "body", "recipients"},
     *             @OA\Property(property="subject", type="string", example="Update Customer Activity"),
     *             @OA\Property(property="body", type="string", example="Customer activity telah diupdate"),
     *             @OA\Property(
     *                 property="recipients", 
     *                 type="array", 
     *                 description="Array email penerima",
     *                 @OA\Items(
     *                     type="string", 
     *                     format="email",
     *                     example="customer@example.com"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email berhasil dikirim",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Email berhasil dikirim")
     *         )
     *     )
     * )
     */
    public function sendEmail(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                'recipients' => 'required|array',
                'recipients.*' => 'email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            foreach ($request->recipients as $recipient) {
                // Mail::to($recipient)->send(new CustomerActivityEmail($request->subject, $request->body));
            }

            return response()->json([
                'success' => true,
                'message' => 'Email berhasil dikirim'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim email'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customer-activities/tim-sales/{timSalesId}/members",
     *     summary="Get tim sales members",
     *     description="Mengambil daftar member dari tim sales berdasarkan ID tim sales",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="timSalesId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="John Doe"),
     *                     @OA\Property(property="user_id", type="integer", example=123),
     *                     @OA\Property(property="tim_sales_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getTimSalesMembers($timSalesId): JsonResponse
    {
        try {
            $members = DB::table('m_tim_sales_d')
                ->whereNull('deleted_at')
                ->where('tim_sales_id', $timSalesId)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $members
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/customer-activities/contract/add",
     *     summary="Create contract activity",
     *     description="Membuat aktivitas untuk kontrak (PKS)",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pks_id", "tgl_activity", "tipe"},
     *             @OA\Property(property="pks_id", type="integer", example=1),
     *             @OA\Property(property="tgl_activity", type="string", format="date", example="2024-07-01"),
     *             @OA\Property(property="tipe", type="string", example="Meeting"),
     *             @OA\Property(property="notes", type="string", example="Meeting kontrak"),
     *             @OA\Property(property="start", type="string", example="09:00"),
     *             @OA\Property(property="end", type="string", example="10:00"),
     *             @OA\Property(property="durasi", type="integer", example=60),
     *             @OA\Property(property="notulen", type="string", example="Hasil meeting kontrak")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contract Activity berhasil dibuat"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function addContractActivity(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'pks_id' => 'required|exists:sl_pks,id',
                'tgl_activity' => 'required|date',
                'tipe' => 'required|string',
                'tgl_realisasi' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $pks = Pks::find($request->pks_id);
            $leads = Leads::find($pks->leads_id);
            $nomor = $this->generateNomor($pks->leads_id);
            $current_date_time = Carbon::now();

            $activityData = $request->only([
                'tgl_activity',
                'tipe',
                'notes',
                'start',
                'end',
                'durasi',
                'tgl_realisasi',
                'jam_realisasi',
                'jenis_visit_id',
                'notulen',
                'email'
            ]);

            $activityData['nomor'] = $nomor;
            $activityData['pks_id'] = $request->pks_id;
            $activityData['leads_id'] = $leads->id;
            $activityData['branch_id'] = $leads->branch_id;
            $activityData['is_activity'] = 1;
            $activityData['user_id'] = Auth::id();
            $activityData['created_by'] = Auth::user()->full_name;
            $activityData['created_at'] = $current_date_time;

            $activity = CustomerActivity::create($activityData);

            // Handle file uploads jika ada
            if ($request->has('files')) {
                foreach ($request->files as $fileData) {
                    $this->saveActivityFile($activity->id, $fileData);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contract Activity berhasil dibuat dengan nomor: ' . $nomor,
                'data' => $activity
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customer-activities/contract/{pksId}/list",
     *     summary="Get contract activities",
     *     description="Mengambil daftar aktivitas untuk kontrak tertentu",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="pksId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", 
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-092024-00001"),
     *                     @OA\Property(property="tgl_activity", type="string", format="date", example="2024-09-23"),
     *                     @OA\Property(property="tipe", type="string", example="Meeting"),
     *                     @OA\Property(property="notes", type="string", example="Meeting kontrak"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="files",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="nama_file", type="string", example="Dokumen Meeting"),
     *                             @OA\Property(property="url_file", type="string", example="http://example.com/file.pdf")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getContractActivities($pksId): JsonResponse
    {
        try {
            $activities = CustomerActivity::with(['files'])
                ->where('pks_id', $pksId)
                ->where('is_activity', 1)
                ->whereNull('deleted_at')
                ->orderBy('tgl_activity', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/customer-activities/assign-ro",
     *     summary="Assign RO to leads",
     *     description="Menugaskan RO ke leads melalui customer activity",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leads_id", "ro_id", "notes"},
     *             @OA\Property(property="leads_id", type="integer", example=1),
     *             @OA\Property(property="pks_id", type="integer", example=1, description="Optional: untuk kontrak"),
     *             @OA\Property(property="ro_id", type="integer", example=123),
     *             @OA\Property(property="ro_team", type="array", @OA\Items(type="integer"), example={124, 125}),
     *             @OA\Property(property="notes", type="string", example="Penugasan RO untuk leads ini")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="RO berhasil ditugaskan")
     *         )
     *     )
     * )
     */
    public function assignRO(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'leads_id' => 'required|exists:sl_leads,id',
                'ro_id' => 'required|integer',
                'ro_team' => 'nullable|array',
                'ro_team.*' => 'integer',
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $leads = Leads::find($request->leads_id);
            $nomor = $this->generateNomor($request->leads_id);
            $current_date_time = Carbon::now();

            // Get RO name
            $roUser = DB::connection('mysqlhris')
                ->table('m_user')
                ->where('id', $request->ro_id)
                ->first();

            // Create activity
            $activityData = [
                'nomor' => $nomor,
                'tgl_activity' => $current_date_time->toDateString(),
                'leads_id' => $request->leads_id,
                'branch_id' => $leads->branch_id,
                'tipe' => 'Pilih RO',
                'notes' => $request->notes,
                'ro_id' => $request->ro_id,
                'ro' => $roUser ? $roUser->full_name : null,
                'is_activity' => 1,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_at' => $current_date_time
            ];

            if ($request->pks_id) {
                $activityData['pks_id'] = $request->pks_id;
            }

            CustomerActivity::create($activityData);

            // Update leads
            $updateData = [
                'ro_id' => $request->ro_id,
                'ro' => $roUser ? $roUser->full_name : null,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ];

            if ($request->ro_team) {
                $updateData['ro_id_1'] = $request->ro_team[0] ?? null;
                $updateData['ro_id_2'] = $request->ro_team[1] ?? null;
                $updateData['ro_id_3'] = $request->ro_team[2] ?? null;
            }

            Leads::where('id', $request->leads_id)->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'RO berhasil ditugaskan dengan nomor: ' . $nomor
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/customer-activities/assign-crm",
     *     summary="Assign CRM to leads",
     *     description="Menugaskan CRM ke leads melalui customer activity",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leads_id", "crm_id", "notes"},
     *             @OA\Property(property="leads_id", type="integer", example=1),
     *             @OA\Property(property="pks_id", type="integer", example=1, description="Optional: untuk kontrak"),
     *             @OA\Property(property="crm_id", type="integer", example=123),
     *             @OA\Property(property="crm_team", type="array", @OA\Items(type="integer"), example={124, 125}),
     *             @OA\Property(property="notes", type="string", example="Penugasan CRM untuk leads ini")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="CRM berhasil ditugaskan")
     *         )
     *     )
     * )
     */
    public function assignCRM(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'leads_id' => 'required|exists:sl_leads,id',
                'crm_id' => 'required|integer',
                'crm_team' => 'nullable|array',
                'crm_team.*' => 'integer',
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $leads = Leads::find($request->leads_id);
            $nomor = $this->generateNomor($request->leads_id);
            $current_date_time = Carbon::now();

            // Get CRM name
            $crmUser = DB::connection('mysqlhris')
                ->table('m_user')
                ->where('id', $request->crm_id)
                ->first();

            // Create activity
            $activityData = [
                'nomor' => $nomor,
                'tgl_activity' => $current_date_time->toDateString(),
                'leads_id' => $request->leads_id,
                'branch_id' => $leads->branch_id,
                'tipe' => 'Pilih CRM',
                'notes' => $request->notes,
                'crm_id' => $request->crm_id,
                'crm' => $crmUser ? $crmUser->full_name : null,
                'is_activity' => 1,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_at' => $current_date_time
            ];

            if ($request->pks_id) {
                $activityData['pks_id'] = $request->pks_id;
            }

            CustomerActivity::create($activityData);

            // Update leads
            $updateData = [
                'crm_id' => $request->crm_id,
                'crm' => $crmUser ? $crmUser->full_name : null,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ];

            if ($request->crm_team) {
                $updateData['crm_id_1'] = $request->crm_team[0] ?? null;
                $updateData['crm_id_2'] = $request->crm_team[1] ?? null;
            }

            Leads::where('id', $request->leads_id)->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'CRM berhasil ditugaskan dengan nomor: ' . $nomor
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/customer-activities/update-contract-status",
     *     summary="Update contract status",
     *     description="Update status kontrak PKS melalui customer activity",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pks_id", "status_pks_id", "notes"},
     *             @OA\Property(property="pks_id", type="integer", example=1),
     *             @OA\Property(property="status_pks_id", type="integer", example=2),
     *             @OA\Property(property="notes", type="string", example="Update status kontrak")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Status kontrak berhasil diupdate")
     *         )
     *     )
     * )
     */
    public function updateContractStatus(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'pks_id' => 'required|exists:sl_pks,id',
                'status_pks_id' => 'required|exists:m_status_pks,id',
                'notes' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $pks = Pks::find($request->pks_id);
            $leads = Leads::find($pks->leads_id);
            $nomor = $this->generateNomor($pks->leads_id);
            $current_date_time = Carbon::now();

            // Create activity
            CustomerActivity::create([
                'nomor' => $nomor,
                'pks_id' => $request->pks_id,
                'tgl_activity' => $current_date_time->toDateString(),
                'leads_id' => $leads->id,
                'branch_id' => $leads->branch_id,
                'tipe' => 'Update Status',
                'notes' => $request->notes,
                'is_activity' => 1,
                'user_id' => Auth::id(),
                'created_by' => Auth::user()->full_name,
                'created_at' => $current_date_time
            ]);

            // Update PKS status
            Pks::where('id', $request->pks_id)->update([
                'status_pks_id' => $request->status_pks_id,
                'updated_at' => $current_date_time,
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status kontrak berhasil diupdate dengan nomor: ' . $nomor
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customer-activities/issues/{pksId}",
     *     summary="Get contract issues",
     *     description="Mengambil daftar issue untuk kontrak tertentu",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="pksId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="judul", type="string", example="Issue Kontrak"),
     *                     @OA\Property(property="jenis_keluhan", type="string", example="Teknis"),
     *                     @OA\Property(property="kolaborator", type="string", example="Tim Support"),
     *                     @OA\Property(property="deskripsi", type="string", example="Deskripsi masalah"),
     *                     @OA\Property(property="url_lampiran", type="string", example="http://example.com/file.pdf"),
     *                     @OA\Property(property="status", type="string", example="Open"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="created_by", type="string", example="Admin"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_by", type="string", example="Admin")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getContractIssues($pksId): JsonResponse
    {
        try {
            $issues = DB::table('sl_issue')
                ->select([
                    'id',
                    'judul',
                    'jenis_keluhan',
                    'kolaborator',
                    'deskripsi',
                    'url_lampiran',
                    'status',
                    'created_at',
                    'created_by',
                    'updated_at',
                    'updated_by'
                ])
                ->whereNull('deleted_at')
                ->where('pks_id', $pksId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $issues
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customer-activities/paginated",
     *     summary="Get paginated customer activities",
     *     description="Mengambil daftar customer activities dengan pagination untuk feed",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Halaman",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Jumlah data per halaman",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", 
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-092024-00001"),
     *                     @OA\Property(property="tgl_activity", type="string", format="date", example="2024-09-23"),
     *                     @OA\Property(property="tipe", type="string", example="Telepon"),
     *                     @OA\Property(property="keterangan", type="string", example="Follow up customer"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="nama", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="sales", type="string", example="John Doe"),
     *                     @OA\Property(property="kebutuhan", type="string", example="Laboratory Service"),
     *                     @OA\Property(property="status_leads", type="string", example="Follow Up")
     *                 )
     *             ),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="total", type="integer", example=50),
     *             @OA\Property(property="last_page", type="integer", example=5)
     *         )
     *     )
     * )
     */
    public function getPaginatedActivities(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            $db2 = DB::connection('mysqlhris')->getDatabaseName();

            $query = DB::table('sl_customer_activity')
                ->join('sl_leads', 'sl_leads.id', 'sl_customer_activity.leads_id')
                ->leftJoin('m_tim_sales_d', 'sl_leads.tim_sales_d_id', '=', 'm_tim_sales_d.id')
                ->leftJoin('m_kebutuhan', 'sl_leads.kebutuhan_id', '=', 'm_kebutuhan.id')
                ->leftJoin('m_status_leads', 'sl_customer_activity.status_leads_id', '=', 'm_status_leads.id')
                ->select([
                    'sl_customer_activity.id',
                    'sl_customer_activity.nomor',
                    'sl_customer_activity.tgl_activity',
                    'sl_customer_activity.tipe',
                    'sl_customer_activity.notes as keterangan',
                    'sl_customer_activity.created_at',
                    'sl_leads.nama_perusahaan as nama',
                    'm_tim_sales_d.nama as sales',
                    'm_kebutuhan.nama as kebutuhan',
                    'm_status_leads.nama as status_leads'
                ])
                ->whereNull('sl_customer_activity.deleted_at')
                ->orderBy('sl_customer_activity.tgl_activity', 'desc');

            // Apply filters if provided
            if ($request->tgl_dari && $request->tgl_sampai) {
                $query->whereBetween('sl_customer_activity.tgl_activity', [$request->tgl_dari, $request->tgl_sampai]);
            }

            if ($request->branch) {
                $query->where('sl_leads.branch_id', $request->branch);
            }

            if ($request->kebutuhan) {
                $query->where('m_kebutuhan.id', $request->kebutuhan);
            }

            if ($request->tipe) {
                $query->where('sl_customer_activity.tipe', $request->tipe);
            }

            $total = $query->count();
            $results = $query->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $results,
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.'
            ], 500);
        }
    }
}