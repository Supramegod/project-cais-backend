<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\CustomerActivity;
use App\Models\CustomerActivityFile;
use App\Models\Leads;
use App\Models\Pks;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Customer Activity",
 *     description="Endpoints untuk manajemen aktivitas customer"
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
                'leads:id,nama_perusahaan,branch_id',
                'leads.branch:id,name',
                'leads.kebutuhan:m_kebutuhan.id,m_kebutuhan.nama',
                'timSalesDetail:id,nama'
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
                $query->whereHas('leads.kebutuhan', function ($q) use ($request) {
                    $q->where('m_kebutuhan.id', $request->kebutuhan);
                });
            }

            if ($request->filled('tipe')) {
                $query->where('tipe', $request->tipe);
            }

            if ($request->filled('user')) {
                $query->where('user_id', $request->user);
            }

            // Filter berdasarkan role user
            $user = Auth::user();
            if (in_array($user->role_id, [29, 30, 31, 32, 33])) {
                // Logic filter untuk divisi sales - menampilkan hanya aktivitas user tersebut
                $query->where('user_id', $user->id);
            }

            // Order dan get data
            $activities = $query->orderBy('tgl_activity', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Map data untuk menampilkan hanya field yang diperlukan
            $mappedActivities = $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'nomor' => $activity->nomor,
                    'tgl_activity' => $activity->tgl_activity,
                    'tipe' => $activity->tipe,
                    'notes' => $activity->notes,
                    'status_leads_id' => $activity->status_leads_id,
                    'created_at' => $activity->created_at,
                    'nama_perusahaan' => $activity->leads?->nama_perusahaan,
                    'kebutuhan' => $activity->leads?->kebutuhan->first()?->nama,
                    'branch' => $activity->leads?->branch?->name,
                    'sales' => $activity->timSalesDetail?->nama,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $mappedActivities,
                'meta' => [
                    'total' => $mappedActivities->count(),
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
            $activity = CustomerActivity::with([
                'leads.branch',
                'leads.kebutuhan',
                'leads.timSales',
                'leads.timSalesD'
            ])->whereNull('deleted_at')
                ->find($id);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            // Map data sesuai form "1. Informasi Leads" di gambar
            $informasiLeads = [
                'leads_customer' => $activity->leads?->nama_perusahaan,
                'tanggal_activity' => $activity->tgl_activity,
                'wilayah' => $activity->leads?->branch?->name,
                'kebutuhan' => $activity->leads?->kebutuhan->first()?->nama,
                'tim_sales' => $activity->leads?->timSales?->nama,
                'sales' => $activity->leads?->timSalesD?->nama,
                'crm' => $activity->crm,
                'ro' => $activity->ro,
                'notes' => $activity->notes
            ];

            // Get all activities terkait leads ini (history activity)
            $activityLeads = CustomerActivity::where('leads_id', $activity->leads_id)
                ->whereNull('deleted_at')
                ->orderBy('tgl_activity', 'desc')
                ->get()
                ->map(function ($act) {
                    $baseData = [
                        'id' => $act->id,
                        'tipe' => $act->tipe,
                        'notes' => $act->notes_tipe ?? $act->notes,
                        'tgl_activity' => $act->tgl_activity
                    ];

                    // Conditional fields berdasarkan tipe (HARUS di dalam map)
                    if (in_array(strtolower($act->tipe), ['telepon', 'online meeting'])) {
                        // Untuk Telepon & Online Meeting
                        $baseData['start'] = $act->start;
                        $baseData['end'] = $act->end;
                        $baseData['durasi'] = $act->durasi;
                        $baseData['tgl_realisasi'] = $act->tgl_realisasi;
                    } elseif (strtolower($act->tipe) === 'visit') {
                        // Untuk Visit
                        $baseData['tgl_realisasi'] = $act->tgl_realisasi;
                        $baseData['jam_realisasi'] = $act->jam_realisasi;
                        $baseData['jenis_visit'] = $act->jenis_visit;
                    }

                    return $baseData;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'informasi_leads' => $informasiLeads,
                    'activity_leads' => $activityLeads
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in CustomerActivityController@view: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
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
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"leads_id", "tgl_activity", "tipe"},
     *                 type="object",
     *                 @OA\Property(property="leads_id", type="integer", example=1, description="ID leads yang terkait"),
     *                 @OA\Property(property="tgl_activity", type="string", format="date", example="2024-07-01", description="Tanggal aktivitas"),
     *                 @OA\Property(property="tipe", type="string", example="Telepon", description="Tipe aktivitas: Telepon, Email, Meeting, Visit, Online Meeting"),
     *                 @OA\Property(property="notes", type="string", example="Catatan aktivitas", description="Catatan atau keterangan aktivitas"),
     *                 @OA\Property(property="notes_tipe", type="string", example="Catatan spesifik tipe", description="Catatan berdasarkan tipe aktivitas"),
     *                 @OA\Property(property="tim_sales_id", type="integer", example=1, description="ID tim sales"),
     *                 @OA\Property(property="tim_sales_d_id", type="integer", example=1, description="ID detail tim sales"),
     *                 @OA\Property(property="status_leads_id", type="integer", example=1, description="ID status leads yang akan diupdate"),
     *                 @OA\Property(property="start", type="string", example="09:00", description="Jam mulai aktivitas (format: HH:mm)"),
     *                 @OA\Property(property="end", type="string", example="10:00", description="Jam selesai aktivitas (format: HH:mm)"),
     *                 @OA\Property(property="durasi", type="integer", example=60, description="Durasi aktivitas dalam menit"),
     *                 @OA\Property(property="tgl_realisasi", type="string", format="date", example="2024-07-01", description="Tanggal realisasi"),
     *                 @OA\Property(property="jam_realisasi", type="string", example="09:30", description="Jam realisasi (format: HH:mm)"),
     *                 @OA\Property(property="jenis_visit_id", type="integer", example=1, description="ID jenis visit (jika tipe = Visit)"),
     *                 @OA\Property(property="jenis_visit", type="string", example="Survey", description="Jenis visit"),
     *                 @OA\Property(property="notulen", type="string", example="Notulen meeting", description="Notulen atau hasil meeting"),
     *                 @OA\Property(property="email", type="string", format="email", example="email@example.com", description="Email customer"),
     *                 @OA\Property(property="penerima", type="string", example="John Doe", description="Penerima email/telepon"),
     *                 @OA\Property(property="link_bukti_foto", type="string", example="https://example.com/foto.jpg", description="Link bukti foto"),
     *                 @OA\Property(
     *                     property="files",
     *                     type="array",
     *                     description="Array file yang akan diupload",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"nama_file", "file_content"},
     *                         @OA\Property(property="nama_file", type="string", example="Notulen Meeting"),
     *                         @OA\Property(property="file_content", type="string", example="base64EncodedFileContent", description="File content dalam format base64"),
     *                         @OA\Property(property="extension", type="string", example="pdf", description="Ekstensi file (pdf, jpg, png, dll)")
     *                     )
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
     *                 @OA\Property(property="status_leads_id", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-07-01T10:30:00.000000Z"),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(
     *                     property="leads",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan")
     *                 ),
     *                 @OA\Property(
     *                     property="files",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_file", type="string", example="Notulen Meeting"),
     *                         @OA\Property(property="url_file", type="string", example="http://example.com/uploads/customer-activity/file.pdf")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error - Data tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="object", description="Object error dari validator")
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

            // Validate request menggunakan rules untuk ADD
            $validator = Validator::make(
                $request->all(),
                $this->getValidationRules(false), // isUpdate = false
                $this->getValidationMessages()
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            // Check if leads exists and not deleted
            // Mengganti Leads::whereNull('deleted_at')->find($request->leads_id);
            // karena sudah divalidasi dengan 'exists:sl_leads,id'
            $leads = Leads::find($request->leads_id);
            // Perlu menambahkan check deleted_at jika 'exists' tidak mengeceknya
            if (!$leads || $leads->deleted_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leads tidak ditemukan atau sudah dihapus.'
                ], 404);
            }


            $nomor = $this->generateNomor($request->leads_id); // Asumsi method ini ada
            $current_date_time = Carbon::now();

            // Prepare activity data using allowed fields
            $activityData = $request->only($this->getAllowedFields());
            $activityData['nomor'] = $nomor;
            $activityData['branch_id'] = $leads->branch_id;
            $activityData['user_id'] = Auth::id();
            $activityData['created_by'] = Auth::user()->full_name;
            $activityData['created_at'] = $current_date_time;

            $activity = CustomerActivity::create($activityData);

            // Handle file uploads
            if ($request->has('files') && is_array($request->files)) {
                foreach ($request->files as $fileData) {
                    $this->saveActivityFile($activity->id, $fileData); // Asumsi method ini ada
                }
            }

            // Update status leads jika ada
            if ($request->status_leads_id) {
                $leads->update([
                    'status_leads_id' => $request->status_leads_id,
                    'updated_by' => Auth::user()->full_name,
                    'updated_at' => $current_date_time
                ]);
            }

            DB::commit();

            // Return with complete data
            $activity->load(['leads', 'files', 'statusLeads']); // Asumsi relasi ini ada

            return response()->json([
                'success' => true,
                'message' => 'Customer Activity berhasil dibuat dengan nomor: ' . $nomor,
                'data' => $activity
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in CustomerActivityController@add: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/customer-activities/update/{id}",
     *     summary="Update customer activity",
     *     description="Mengupdate aktivitas customer berdasarkan ID. Field leads_id tidak dapat diupdate.",
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
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="tgl_activity", type="string", format="date", example="2024-07-02", description="Tanggal aktivitas"),
     *                 @OA\Property(property="tipe", type="string", example="Email", description="Tipe aktivitas: Telepon, Email, Meeting, Visit, Online Meeting"),
     *                 @OA\Property(property="notes", type="string", example="Catatan aktivitas updated", description="Catatan atau keterangan aktivitas"),
     *                 @OA\Property(property="notes_tipe", type="string", example="Catatan spesifik tipe updated", description="Catatan berdasarkan tipe aktivitas"),
     *                 @OA\Property(property="tim_sales_id", type="integer", example=2, description="ID tim sales"),
     *                 @OA\Property(property="tim_sales_d_id", type="integer", example=2, description="ID detail tim sales"),
     *                 @OA\Property(property="status_leads_id", type="integer", example=3, description="ID status leads yang akan diupdate"),
     *                 @OA\Property(property="start", type="string", example="10:00", description="Jam mulai aktivitas (format: HH:mm)"),
     *                 @OA\Property(property="end", type="string", example="11:00", description="Jam selesai aktivitas (format: HH:mm)"),
     *                 @OA\Property(property="durasi", type="integer", example=60, description="Durasi aktivitas dalam menit"),
     *                 @OA\Property(property="tgl_realisasi", type="string", format="date", example="2024-07-02", description="Tanggal realisasi"),
     *                 @OA\Property(property="jam_realisasi", type="string", example="10:30", description="Jam realisasi (format: HH:mm)"),
     *                 @OA\Property(property="jenis_visit_id", type="integer", example=2, description="ID jenis visit (jika tipe = Visit)"),
     *                 @OA\Property(property="jenis_visit", type="string", example="Presentasi", description="Jenis visit"),
     *                 @OA\Property(property="notulen", type="string", example="Updated notulen", description="Notulen atau hasil meeting"),
     *                 @OA\Property(property="email", type="string", format="email", example="newemail@example.com", description="Email customer"),
     *                 @OA\Property(property="penerima", type="string", example="Jane Doe", description="Penerima email/telepon"),
     *                 @OA\Property(property="link_bukti_foto", type="string", example="https://example.com/new-foto.jpg", description="Link bukti foto")
     *             )
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
     *                 @OA\Property(property="tgl_activity", type="string", format="date", example="2024-07-02"),
     *                 @OA\Property(property="tipe", type="string", example="Email"),
     *                 @OA\Property(property="notes", type="string", example="Catatan aktivitas updated"),
     *                 @OA\Property(property="status_leads_id", type="integer", example=3),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-07-02T15:45:00.000000Z"),
     *                 @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                 @OA\Property(
     *                     property="leads",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="status_leads_id", type="integer", example=3)
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
     *         response=422,
     *         description="Validation Error - Data tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="object", description="Object error dari validator")
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

            // Validate request menggunakan rules untuk UPDATE
            $validator = Validator::make(
                $request->all(),
                $this->getValidationRules(true), // isUpdate = true
                $this->getValidationMessages()
            );

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            // Get allowed fields (exclude leads_id untuk update)
            $allowedUpdateFields = array_diff($this->getAllowedFields(), ['leads_id']);
            $updateData = $request->only($allowedUpdateFields);

            // Only update if there's actual data
            if (!empty($updateData)) {
                $current_time = Carbon::now();
                $updateData['updated_by'] = Auth::user()->full_name;
                $updateData['updated_at'] = $current_time;

                $activity->update($updateData);

                // Update status leads jika ada dan berubah
                if ($request->has('status_leads_id') && $request->status_leads_id != $activity->leads->status_leads_id) {
                    $activity->leads->update([
                        'status_leads_id' => $request->status_leads_id,
                        'updated_by' => Auth::user()->full_name,
                        'updated_at' => $current_time
                    ]);
                }
            }

            DB::commit();

            // Reload relationships
            $activity->refresh();
            $activity->load(['leads', 'files', 'statusLeads']);

            return response()->json([
                'success' => true,
                'message' => 'Customer Activity berhasil diupdate',
                'data' => $activity
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in CustomerActivityController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
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
     *     path="/api/customer-activities/available",
     *     summary="Mendapatkan daftar leads yang tersedia untuk aktivitas",
     *     description="Endpoint ini digunakan untuk mengambil leads yang tersedia untuk dilakukan aktivitas sales selanjutnya. Data difilter berdasarkan role user:
     *                 - Sales (29): hanya melihat leads mereka sendiri
     *                 - Team Leader (31): melihat leads seluruh anggota tim
     *                 - RO (6,8): melihat semua leads
     *                 - CRM (54,55,56): melihat leads berdasarkan assignment CRM",
     *     tags={"Customer Activity"},
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
            $user = Auth::user();

            // Gunakan scope dari model
            $query = Leads::with(['statusLeads', 'branch', 'kebutuhan', 'timSales', 'timSalesD'])
                ->availableForActivity($user);

            $data = $query->get();

            // Transformasi data
            $data->transform(function ($item) {
                $item->tgl = Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y');
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

   
    //=====halper functions=====//
    /**
     * Menggabungkan dan mengelola validation rules untuk Add (Create) dan Update.
     *
     * @param bool $isUpdate True jika rules untuk update, false untuk add (create).
     * @return array
     */
    private function getValidationRules(bool $isUpdate = false): array
    {
        // Default rules
        $rules = [
            'tgl_activity' => 'required|date',
            'tipe' => 'required|string|in:Telepon,Email,Meeting,Visit,Online Meeting',
            'notes' => 'nullable|string',
            'notes_tipe' => 'nullable|string',
            'tim_sales_id' => 'nullable|exists:m_tim_sales,id',
            'tim_sales_d_id' => 'nullable|exists:m_tim_sales_d,id',
            'status_leads_id' => 'nullable|exists:m_status_leads,id',
            'start' => 'nullable|date_format:H:i',
            'end' => 'nullable|date_format:H:i|after:start',
            'durasi' => 'nullable|integer|min:0',
            'tgl_realisasi' => 'nullable|date',
            'jam_realisasi' => 'nullable|date_format:H:i',
            'jenis_visit_id' => 'nullable|integer',
            'jenis_visit' => 'nullable|string',
            'notulen' => 'nullable|string',
            'email' => 'nullable|email',
            'penerima' => 'nullable|string',
            'link_bukti_foto' => 'nullable|url',
        ];

        if (!$isUpdate) {
            // Rules khusus untuk ADD (Create)
            $rules['leads_id'] = 'required|exists:sl_leads,id';
            $rules['tgl_realisasi'] .= '|after_or_equal:tgl_activity'; // Tambahkan rule ini hanya untuk ADD
            $rules['files'] = 'nullable|array';
            $rules['files.*.nama_file'] = 'required_with:files|string|max:255';
            $rules['files.*.file_content'] = 'required_with:files|string';
        } else {
            // Rules khusus untuk UPDATE
            // Ubah rule 'required' di tgl_activity dan tipe menjadi 'sometimes|required'
            $rules['tgl_activity'] = 'sometimes|' . $rules['tgl_activity'];
            $rules['tipe'] = 'sometimes|' . $rules['tipe'];
            // Hapus rule files untuk update jika tidak diperlukan
            unset($rules['files'], $rules['files.*.nama_file'], $rules['files.*.file_content']);
        }

        return $rules;
    }

    /**
     * Custom validation messages.
     *
     * @return array
     */
    private function getValidationMessages(): array
    {
        return [
            // Required messages
            'leads_id.required' => 'Leads wajib dipilih.',
            'leads_id.exists' => 'Leads yang dipilih tidak valid.',
            'tgl_activity.required' => 'Tanggal activity wajib diisi.',
            'tgl_activity.date' => 'Format tanggal activity tidak valid.',
            'tipe.required' => 'Tipe activity wajib dipilih.',
            'tipe.in' => 'Tipe activity harus salah satu dari: Telepon, Email, Meeting, Visit, atau Online Meeting.',

            // Tim Sales validation
            'tim_sales_id.exists' => 'Tim Sales yang dipilih tidak valid.',
            'tim_sales_d_id.exists' => 'Sales yang dipilih tidak valid.',

            // Status leads validation
            'status_leads_id.exists' => 'Status leads yang dipilih tidak valid.',

            // Time validation
            'start.date_format' => 'Format jam mulai harus HH:MM (contoh: 09:00).',
            'end.date_format' => 'Format jam selesai harus HH:MM (contoh: 10:00).',
            'end.after' => 'Jam selesai harus lebih besar dari jam mulai.',
            'jam_realisasi.date_format' => 'Format jam realisasi harus HH:MM (contoh: 09:00).',

            // Duration validation
            'durasi.integer' => 'Durasi harus berupa angka.',
            'durasi.min' => 'Durasi tidak boleh kurang dari 0.',

            // Date validation
            'tgl_realisasi.date' => 'Format tanggal realisasi tidak valid.',
            'tgl_realisasi.after_or_equal' => 'Tanggal realisasi tidak boleh sebelum tanggal activity.',

            // Email validation
            'email.email' => 'Format email tidak valid.',

            // URL validation
            'link_bukti_foto.url' => 'Format link bukti foto tidak valid.',

            // File validation
            'files.array' => 'Format files harus berupa array.',
            'files.*.nama_file.required_with' => 'Nama file wajib diisi.',
            'files.*.nama_file.max' => 'Nama file maksimal 255 karakter.',
            'files.*.file_content.required_with' => 'Konten file wajib diisi.',
        ];
    }

    /**
     * Fields yang diizinkan untuk create/update.
     *
     * @return array
     */
    private function getAllowedFields(): array
    {
        return [
            'leads_id',
            'tgl_activity',
            'tipe',
            'notes',
            'notes_tipe',
            'tim_sales_id',
            'tim_sales_d_id',
            'status_leads_id',
            'start',
            'end',
            'durasi',
            'tgl_realisasi',
            'jam_realisasi',
            'jenis_visit_id',
            'jenis_visit',
            'notulen',
            'email',
            'penerima',
            'link_bukti_foto'
        ];
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
        try {
            if (isset($fileData['file_content']) && isset($fileData['nama_file'])) {
                // Decode base64
                $fileContent = base64_decode($fileData['file_content']);

                // Validate decoded content
                if ($fileContent === false) {
                    throw new \Exception('Gagal decode file content. Pastikan file dalam format base64.');
                }

                // Generate unique filename
                $extension = $fileData['extension'] ?? 'pdf'; // Asumsi extension bisa dikirim
                $fileName = Str::slug($fileData['nama_file']) . '_' . time() . '_' . uniqid() . '.' . $extension;

                // Use Storage facade properly
                // Pastikan 'bukti-activity' adalah disk yang dikonfigurasi di config/filesystems.php
                Storage::disk('bukti-activity')->put($fileName, $fileContent);

                // Build URL properly
                // Sesuaikan dengan konfigurasi URL untuk disk 'bukti-activity' Anda
                // Contoh: Storage::disk('bukti-activity')->url($fileName);
                $fileUrl = url('public/uploads/customer-activity/' . $fileName);

                CustomerActivityFile::create([
                    'customer_activity_id' => $activityId,
                    'nama_file' => $fileData['nama_file'],
                    'url_file' => $fileUrl,
                    'created_by' => Auth::user()->full_name,
                    'created_at' => Carbon::now()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error saving activity file: ' . $e->getMessage());
            // Lemparkan exception untuk memicu DB::rollBack() di metode pemanggil (add)
            throw new \Exception('Gagal menyimpan file: ' . $e->getMessage());
        }
    }
}