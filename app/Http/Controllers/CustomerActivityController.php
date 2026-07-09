<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Http\Requests\AssignCrmRequest;
use App\Http\Requests\AssignRoRequest;
use App\Http\Requests\StoreContractActivityRequest;
use App\Http\Requests\StoreCustomerActivityRequest;
use App\Http\Requests\UpdateContractStatusRequest;
use App\Http\Requests\UpdateCustomerActivityRequest;
use App\Mail\CustomerActivityEmail;
use App\Models\LeadsKebutuhan;
use App\Models\SalesActivity;
use App\Services\DynamicMailerService;
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
    private $dynamicMailerService;
    private $activityService;

    public function __construct(DynamicMailerService $dynamicMailerService, \App\Services\CustomerActivityService $activityService)
    {
        $this->dynamicMailerService = $dynamicMailerService;
        $this->activityService = $activityService;
    }
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
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Keyword pencarian (jika diisi, filter tanggal akan diabaikan)",
     *         required=false,
     *         @OA\Schema(type="string", example="PT ABC")
     *     ),
     *     @OA\Parameter(
     *         name="search_by",
     *         in="query",
     *         description="Kolom yang akan dicari (default: nama_perusahaan)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"nama_perusahaan", "tipe", "branch", "kebutuhan", "sales"}, example="nama_perusahaan")
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
        // 1. Inisialisasi Tanggal
        $tglDari = $request->tgl_dari ?: Carbon::now()->subMonths(3)->startOfMonth()->toDateString();
        $tglSampai = $request->tgl_sampai ?: Carbon::now()->toDateString();

        // Validasi Tanggal
        if ($request->tgl_dari && $request->tgl_sampai && Carbon::parse($tglDari)->gt(Carbon::parse($tglSampai))) {
            return $this->errorResponse('Tanggal dari tidak boleh melebihi tanggal sampai.', 422);
        }

            // 2. Base Query dengan Eager Loading (tanpa grouping per leads_id)
            $query = CustomerActivity::with([
                'leads:id,nama_perusahaan,branch_id',
                'leads.branch:id,name',
                'leads.kebutuhan:id,nama',
                'timSalesDetail:id,nama'
            ])->whereNull('deleted_at');

            // 3. Filter Tipe yang diizinkan
            $allowedTypes = ['Telepon', 'Online Meeting', 'Email', 'Kirim Berkas', 'Visit'];
            $query->whereIn('tipe', $allowedTypes);

            // 4. Logika Pencarian (Search) - sama seperti kode Anda
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $searchBy = $request->get('search_by', 'nama_perusahaan');

                if ($searchBy === 'nama_perusahaan') {
                    $searchTerm = str_contains($searchTerm, ' ')
                        ? '"' . $searchTerm . '"'
                        : $searchTerm . '*';
                    $query->whereRaw("MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
                } elseif (in_array($searchBy, ['tipe', 'branch', 'kebutuhan', 'sales'])) {
                    $query->where($searchBy, 'LIKE', '%' . $searchTerm . '%');
                }
            } else {
                $tglDari = $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString());
                $tglSampai = $request->get('tgl_sampai', Carbon::today()->toDateString());
                $query->whereBetween('tgl_activity', [$tglDari, $tglSampai]);
            }

            // 5. Filter Tambahan
            $query->whereHas('leads', function ($q) use ($request) {
                $q->filterByUserRole();
                if ($request->filled('branch')) {
                    $q->where('branch_id', $request->branch);
                }
                if ($request->filled('kebutuhan')) {
                    $q->whereHas('kebutuhan', function ($sq) use ($request) {
                        $sq->where('m_kebutuhan.id', $request->kebutuhan);
                    });
                }
            });
            if ($request->filled('user')) {
                $query->where('user_id', $request->user);
            }
            if ($request->filled('tipe')) {
                $query->where('tipe', $request->tipe);
            }

            // 6. Pagination
            $activities = $query->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->paginate($request->get('per_page', 15));

            // 7. Transformasi Data dengan conditional fields PER ITEM
            $activities->getCollection()->transform(function ($activity) {
                $base = [
                    'id' => $activity->id,
                    'nomor' => $activity->nomor,
                    'tgl_activity' => $activity->tgl_activity,
                    'tipe' => $activity->tipe,
                    'notes' => $activity->notes,
                    'status_leads_id' => $activity->status_leads_id,
                    'created_at' => $activity->getRawOriginal('created_at'),
                    'created_by' => $activity->created_by,
                    'nama_perusahaan' => $activity->leads?->nama_perusahaan ?? '-',
                    'kebutuhan' => $activity->leads?->kebutuhan->pluck('nama')->toArray() ?? [],
                    'branch' => $activity->leads?->branch?->name ?? '-',
                    'sales' => $activity->timSalesDetail?->nama ?? '-',
                    'leads_id' => $activity->leads_id,
                    'quotation_id' => $activity->quotation_id,
                    'spk_id' => $activity->spk_id,
                    'pks_id' => $activity->pks_id,
                ];

                // Tambahkan field spesifik berdasarkan tipe
                $tipeLower = strtolower($activity->tipe);
                if (in_array($tipeLower, ['telepon', 'online meeting'])) {
                    $base['start'] = $activity->start;
                    $base['end'] = $activity->end;
                    $base['durasi'] = $activity->durasi;
                    $base['tgl_realisasi'] = $activity->tgl_realisasi;
                } elseif ($tipeLower === 'visit') {
                    $base['tgl_realisasi'] = $activity->tgl_realisasi;
                    $base['jam_realisasi'] = $activity->jam_realisasi;
                    $base['jenis_visit'] = $activity->jenis_visit;
                }

                return $base;
            });

        // 8. Response (bespoke envelope: pagination + meta top-level keys)
        return response()->json([
            'success' => true,
            'message' => 'Data aktivitas berhasil diambil',
            'data' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
            ],
            'meta' => [
                'tgl_dari' => $tglDari,
                'tgl_sampai' => $tglSampai,
                'search_applied' => $request->search,
                'search_by' => $request->get('search_by', 'nama_perusahaan'),
                'filtered_types' => $allowedTypes
            ]
        ]);
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
        $activity = CustomerActivity::with(['files'])
            ->whereNull('deleted_at')
            ->find($id);

        if (!$activity) {
            return $this->notFoundResponse('Data tidak ditemukan.');
        }

            // Get current activity data only
            $activityData = [
                'id' => $activity->id,
                'nomor' => $activity->nomor,
                'nama_perusahaan' => $activity->leads?->nama_perusahaan ?? '-',
                'kebutuhan' => $activity->leads?->kebutuhan->pluck('nama')->toArray() ?? [],
                'branch' => $activity->leads?->branch?->name ?? '-',
                'sales' => $activity->timSalesDetail?->nama ?? '-',
                'tipe' => $activity->tipe,
                'notes' => $activity->notes_tipe ?? $activity->notes,
                'tgl_activity' => $activity->tgl_activity,
                'created_at' => $activity->getRawOriginal('created_at'),
                'created_by' => $activity->created_by,
                'activity_files' => $activity->files->isEmpty() ? null : $activity->files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'nama_file' => $file->nama_file,
                        'url_file' => $file->url_file,
                        'created_at' => $file->created_at
                    ];
                }),
            ];

            // Conditional fields berdasarkan tipe
            if (in_array(strtolower($activity->tipe), ['telepon', 'online meeting'])) {
                $activityData['start'] = $activity->start;
                $activityData['end'] = $activity->end;
                $activityData['durasi'] = $activity->durasi;
                $activityData['tgl_realisasi'] = $activity->tgl_realisasi;
            } elseif (strtolower($activity->tipe) === 'visit') {
                $activityData['tgl_realisasi'] = $activity->tgl_realisasi;
                $activityData['jam_realisasi'] = $activity->jam_realisasi;
                $activityData['jenis_visit'] = $activity->jenis_visit;
            }

        return $this->successResponse($activityData);
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
     *             mediaType="multipart/form-data",
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
     *                     property="files[]",
     *                     type="array",
     *                     description="File yang akan diupload (pdf, doc, docx, jpg, jpeg, png) maksimal 10MB per file",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
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
     *                         @OA\Property(property="url_file", type="string", example="http://example.com/document/customer-activity/NotulenMeeting20240701103000012345.pdf")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="File tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error")
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
    public function add(StoreCustomerActivityRequest $request): JsonResponse
    {
        // Check if leads exists and not deleted (guard di luar transaksi)
        $leads = Leads::find($request->leads_id);
        if (!$leads || $leads->deleted_at) {
            return $this->notFoundResponse('Leads tidak ditemukan atau sudah dihapus.');
        }

        $activity = $this->activityService->createActivity($request, $leads, $this->getAllowedFields());

        // Return with complete data
        $activity->load(['leads', 'files', 'statusLeads']);

        return $this->createdResponse($activity, 'Customer Activity berhasil dibuat dengan nomor: ' . $activity->nomor);
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
    public function update(UpdateCustomerActivityRequest $request, $id): JsonResponse
    {
        $activity = CustomerActivity::whereNull('deleted_at')->find($id);
        if (!$activity) {
            return $this->notFoundResponse('Data tidak ditemukan.');
        }

        $this->activityService->updateActivity($request, $activity, $this->getAllowedFields());

        // Reload relationships
        $activity->refresh();
        $activity->load(['leads', 'files', 'statusLeads']);

        return $this->successResponse($activity, 'Customer Activity berhasil diupdate');
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
        $activity = CustomerActivity::whereNull('deleted_at')->find($id);
        if (!$activity) {
            return $this->notFoundResponse('Data tidak ditemukan.');
        }

        $this->activityService->deleteActivity($activity);

        return $this->messageResponse('Customer Activity berhasil dihapus');
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
        $activities = CustomerActivity::with(['files', 'statusLeads'])
            ->where('leads_id', $leadsId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $leads = Leads::with(['kebutuhan', 'branch'])->find($leadsId);

        if (!$leads) {
            return $this->notFoundResponse('Leads tidak ditemukan.');
        }

        return $this->successResponse([
            'leads' => $leads,
            'activities' => $activities
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/customer-activities/send-email",
     *     summary="Send email notification with attachments using dynamic SMTP",
     *     description="Mengirim email notifikasi dengan attachment menggunakan konfigurasi SMTP user yang sedang login. Sistem akan otomatis membuat customer activity baru dengan tipe 'Email' dan menyimpan file attachment ke storage. Email menggunakan template profesional dengan branding perusahaan.",
     *     tags={"Customer Activity"},
     *     security={{"bearerAuth":{}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email data dengan support untuk multiple attachments (max 10MB per file)",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"subject", "body", "leads_id", "recipients[]"},
     *                 
     *                 @OA\Property(
     *                     property="subject",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Subject email",
     *                     example="Penawaran Kerja Sama - PT Shelter Indonesia"
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="body",
     *                     type="string",
     *                     description="Isi email (plain text atau dengan line breaks)",
     *                     example="Kepada Yth. Bapak/Ibu,\n\nBersama ini kami sampaikan penawaran kerja sama untuk layanan Laboratory Service.\n\nMohon dapat ditinjau dokumen terlampir.\n\nTerima kasih."
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="leads_id",
     *                     type="integer",
     *                     description="ID leads yang terkait dengan email ini",
     *                     example=1
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="recipients[]",
     *                     type="array",
     *                     description="Array email penerima (minimal 1). Gunakan recipients[0], recipients[1], dst.",
     *                     @OA\Items(
     *                         type="string",
     *                         format="email",
     *                         example="client@example.com"
     *                     )
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="cc[]",
     *                     type="array",
     *                     description="Array email CC (opsional). Gunakan cc[0], cc[1], dst.",
     *                     @OA\Items(
     *                         type="string",
     *                         format="email",
     *                         example="supervisor@shelter.com"
     *                     )
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="bcc[]",
     *                     type="array",
     *                     description="Array email BCC (opsional). Gunakan bcc[0], bcc[1], dst.",
     *                     @OA\Items(
     *                         type="string",
     *                         format="email",
     *                         example="archive@shelter.com"
     *                     )
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="attachments[]",
     *                     type="array",
     *                     description="Array file attachment (opsional, max 10MB per file). Format: pdf, doc, docx, xls, xlsx, jpg, jpeg, png",
     *                     @OA\Items(
     *                         type="string",
     *                         format="binary"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Success - Email berhasil dikirim ke semua penerima dan customer activity baru telah dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Email berhasil dikirim ke 2 penerima dan activity baru telah dibuat"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="mailer_used", type="string", example="user_123_smtp", description="SMTP mailer yang digunakan"),
     *                 @OA\Property(property="config_source", type="string", example="user_custom", description="Sumber konfigurasi: user_custom, user_default, atau system_default"),
     *                 @OA\Property(property="sender", type="string", example="John Doe <john@shelter.com>", description="Pengirim email"),
     *                 @OA\Property(property="recipients_count", type="integer", example=2, description="Jumlah penerima yang berhasil"),
     *                 @OA\Property(
     *                     property="recipients",
     *                     type="array",
     *                     description="List email penerima yang berhasil",
     *                     @OA\Items(type="string"),
     *                     example={"client@example.com", "manager@example.com"}
     *                 ),
     *                 @OA\Property(property="attachments_count", type="integer", example=2, description="Jumlah file attachment"),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     description="List nama file attachment",
     *                     @OA\Items(type="string"),
     *                     example={"proposal.pdf", "price_list.xlsx"}
     *                 ),
     *                 @OA\Property(
     *                     property="activity",
     *                     type="object",
     *                     description="Customer activity yang dibuat",
     *                     @OA\Property(property="id", type="integer", example=456),
     *                     @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-022025-00123"),
     *                     @OA\Property(property="tipe", type="string", example="Email"),
     *                     @OA\Property(property="tgl_activity", type="string", format="date", example="2025-02-13"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-02-13T10:30:00.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="cc",
     *                     type="array",
     *                     description="List email CC",
     *                     @OA\Items(type="string"),
     *                     example={"supervisor@shelter.com"}
     *                 ),
     *                 @OA\Property(
     *                     property="bcc",
     *                     type="array",
     *                     description="List email BCC",
     *                     @OA\Items(type="string"),
     *                     example={"archive@shelter.com"}
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=207,
     *         description="Multi-Status - Beberapa email berhasil dikirim, beberapa gagal",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Email berhasil dikirim ke 2 dari 3 penerima dan activity baru telah dibuat"),
     *             @OA\Property(property="partial_success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="mailer_used", type="string", example="user_123_smtp"),
     *                 @OA\Property(property="config_source", type="string", example="user_custom"),
     *                 @OA\Property(property="sender", type="string", example="John Doe <john@shelter.com>"),
     *                 @OA\Property(
     *                     property="success_recipients",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"client@example.com", "manager@example.com"}
     *                 ),
     *                 @OA\Property(
     *                     property="failed_recipients",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"invalid@example.com"}
     *                 ),
     *                 @OA\Property(property="attachments_count", type="integer", example=2),
     *                 @OA\Property(
     *                     property="attachments",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"proposal.pdf", "price_list.xlsx"}
     *                 ),
     *                 @OA\Property(
     *                     property="activity",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=456),
     *                     @OA\Property(property="nomor", type="string", example="CAT/LS/LS001-022025-00123"),
     *                     @OA\Property(property="tipe", type="string", example="Email"),
     *                     @OA\Property(property="tgl_activity", type="string", format="date", example="2025-02-13"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 ),
     *                 @OA\Property(property="success_count", type="integer", example=2),
     *                 @OA\Property(property="failed_count", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error - Data tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="message",
     *                 type="object",
     *                 description="Object berisi error validation",
     *                 example={
     *                     "subject": {"Subject email wajib diisi"},
     *                     "recipients": {"Minimal harus ada 1 penerima email"},
     *                     "attachments.0": {"File harus berformat: pdf, doc, docx, xls, xlsx, jpg, jpeg, atau png"},
     *                     "attachments.1": {"Ukuran file maksimal 10MB"}
     *                 }
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid atau tidak ada",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Not Found - Leads tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Leads dengan ID tersebut tidak ditemukan")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Server Error - Gagal mengirim email atau error sistem",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem: SMTP connection failed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Detail error jika semua email gagal",
     *                 @OA\Property(property="mailer_used", type="string", example="smtp"),
     *                 @OA\Property(property="config_source", type="string", example="system_default"),
     *                 @OA\Property(property="sender", type="string", example="system@shelter.com"),
     *                 @OA\Property(
     *                     property="failed_recipients",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"client1@example.com", "client2@example.com"}
     *                 ),
     *                 @OA\Property(
     *                     property="errors",
     *                     type="array",
     *                     @OA\Items(type="string"),
     *                     example={"Connection timeout", "Authentication failed"}
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function sendEmail(Request $request): JsonResponse
    {
        set_time_limit(0);

        try {
            // === AMBIL DAN BERSIHKAN INPUT UNTUK RECIPIENTS, CC, BCC ===
            $data = $request->all();

            foreach (['recipients', 'cc', 'bcc'] as $field) {
                if (isset($data[$field]) && is_array($data[$field])) {
                    // Hapus elemen null atau string kosong
                    $data[$field] = array_filter($data[$field], function ($value) {
                        return !is_null($value) && trim($value) !== '';
                    });

                    // Jika setelah filter kosong, set ke null (kecuali recipients akan dicek required)
                    if (empty($data[$field])) {
                        $data[$field] = null;
                    }
                }
            }

            // === VALIDASI REQUEST DENGAN DATA YANG SUDAH DIBERSIHKAN ===
            $validator = Validator::make($data, [
                'subject' => 'required|string|max:255',
                'body' => 'required|string',
                'leads_id' => 'required|exists:sl_leads,id',
                'recipients' => 'required|array|min:1',
                'recipients.*' => 'required|email',
                'cc' => 'nullable|array',
                'cc.*' => 'email',
                'bcc' => 'nullable|array',
                'bcc.*' => 'email',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png|max:10240',
            ], [
                // Custom messages (optional, bisa disesuaikan)
                'recipients.required' => 'Minimal harus ada 1 penerima email.',
                'recipients.*.email' => 'Format email penerima tidak valid.',
                'cc.*.email' => 'Format email CC tidak valid.',
                'bcc.*.email' => 'Format email BCC tidak valid.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            // === DAPATKAN USER DAN KONFIGURASI ===
            $user = Auth::user();

            // === SETUP DYNAMIC MAILER ===
            try {
                $mailerSetup = $this->dynamicMailerService->setupMailer($user);
            } catch (\Exception $e) {
                throw new \Exception('Gagal mengkonfigurasi email: ' . $e->getMessage());
            }

            $mailerName = $mailerSetup['name'];
            $fromConfig = $mailerSetup['config'];
            $configSource = $mailerSetup['config_source'];

            // === BUAT ACTIVITY BARU (TIPE EMAIL) ===
            $leads = Leads::find($request->leads_id);
            $nomor = $this->activityService->generateNomor($request->leads_id);
            $current_date_time = Carbon::now();

            // Gabungkan recipients (sudah terfilter) untuk disimpan di notes
            $recipientsList = implode(', ', $data['recipients'] ?? []);
            $notes = "Email dikirim ke: {$recipientsList}\nSubject: {$request->subject}\n\n{$request->body}";

            if (!empty($data['cc'])) {
                $notes .= "\nCC: " . implode(', ', $data['cc']);
            }
            if (!empty($data['bcc'])) {
                $notes .= "\nBCC: " . implode(', ', $data['bcc']);
            }

            // Tambahkan info attachments jika ada
            if ($request->hasFile('attachments')) {
                $attachmentCount = count($request->file('attachments'));
                $notes .= "\nAttachments: {$attachmentCount} file(s)";
            }

            // Buat activity baru
            $activityData = [
                'nomor' => $nomor,
                'leads_id' => $request->leads_id,
                'tgl_activity' => $current_date_time->toDateString(),
                'tipe' => 'Email',
                'notes' => $notes,
                'branch_id' => $leads->branch_id,
                'user_id' => $user->id,
                'created_by' => $user->full_name,
                'created_by_user_id' => $user->id,
                'created_at' => $current_date_time
            ];
            if ($user && in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
                // Untuk Sales, buat SalesActivity
                $activity = $this->activityService->createSalesActivity($request->leads_id, $notes);
            } else {
                // Untuk non-Sales, buat CustomerActivity
                $activity = CustomerActivity::create($activityData);
            }

            // === HANDLE ATTACHMENTS ===
            $attachmentFiles = [];
            $attachmentNames = [];

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    try {
                        // Simpan file ke storage
                        $fileName = $this->activityService->storeActivityFile($activity->id, $file);

                        // Simpan file object untuk dikirim via email
                        $attachmentFiles[] = $file;
                        $attachmentNames[] = $file->getClientOriginalName();

                        Log::info('File attached for email:', [
                            'activity_id' => $activity->id,
                            'filename' => $file->getClientOriginalName(),
                            'size' => $file->getSize()
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to process attachment:', [
                            'filename' => $file->getClientOriginalName(),
                            'error' => $e->getMessage()
                        ]);
                        // Lanjutkan dengan file lain meskipun ada yang gagal
                    }
                }
            }

            // === PREPARE EMAIL CONTENT ===
            $fullBody = $request->body;

            // === SEND EMAILS ===
            $sentCount = 0;
            $failedRecipients = [];
            $successRecipients = [];
            $totalRecipients = count($data['recipients'] ?? []);

            foreach (($data['recipients'] ?? []) as $index => $recipient) {
                $attempt = $index + 1;

                try {
                    // Buat email instance dengan attachments
                    $email = new CustomerActivityEmail(
                        $request->subject,
                        $fullBody,
                        $fromConfig['address'],
                        $fromConfig['name'],
                        $attachmentFiles
                    );

                    // Tambahkan CC jika ada (sudah terfilter)
                    if (!empty($data['cc'])) {
                        $email->cc($data['cc']);
                    }

                    // Tambahkan BCC jika ada (sudah terfilter)
                    if (!empty($data['bcc'])) {
                        $email->bcc($data['bcc']);
                    }

                    // Kirim email menggunakan mailer dinamis
                    Mail::mailer($mailerName)->to($recipient)->send($email);

                    $sentCount++;
                    $successRecipients[] = $recipient;

                    Log::info('Email sent successfully:', [
                        'recipient' => $recipient,
                        'attempt' => $attempt,
                        'attachments_count' => count($attachmentFiles)
                    ]);

                } catch (\Exception $e) {
                    Log::error('Failed to send email:', [
                        'recipient' => $recipient,
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);

                    $failedRecipients[] = [
                        'email' => $recipient,
                        'error' => $e->getMessage(),
                        'attempt' => $attempt
                    ];
                }

                // Delay kecil antar email untuk menghindari rate limit
                if ($attempt < $totalRecipients) {
                    usleep(50000); // 50ms delay
                }
            }

            DB::commit();

            // === RESPONSE ===
            if ($sentCount === $totalRecipients) {
                return response()->json([
                    'success' => true,
                    'message' => "Email berhasil dikirim ke {$sentCount} penerima dan activity baru telah dibuat",
                    'data' => [
                        'mailer_used' => $mailerName,
                        'config_source' => $configSource,
                        'sender' => "{$fromConfig['name']} <{$fromConfig['address']}>",
                        'recipients_count' => $sentCount,
                        'recipients' => $successRecipients,
                        'attachments_count' => count($attachmentFiles),
                        'attachments' => $attachmentNames,
                        'activity' => [
                            'id' => $activity->id,
                            'nomor' => $activity->nomor,
                            'tipe' => $activity->tipe,
                            'tgl_activity' => $activity->tgl_activity,
                            'created_at' => $activity->created_at
                        ],
                        'cc' => $data['cc'] ?? [],
                        'bcc' => $data['bcc'] ?? []
                    ]
                ], 201);
            } elseif ($sentCount > 0) {
                return response()->json([
                    'success' => true,
                    'message' => "Email berhasil dikirim ke {$sentCount} dari {$totalRecipients} penerima dan activity baru telah dibuat",
                    'partial_success' => true,
                    'data' => [
                        'mailer_used' => $mailerName,
                        'config_source' => $configSource,
                        'sender' => "{$fromConfig['name']} <{$fromConfig['address']}>",
                        'success_recipients' => $successRecipients,
                        'failed_recipients' => array_column($failedRecipients, 'email'),
                        'attachments_count' => count($attachmentFiles),
                        'attachments' => $attachmentNames,
                        'activity' => [
                            'id' => $activity->id,
                            'nomor' => $activity->nomor,
                            'tipe' => $activity->tipe,
                            'tgl_activity' => $activity->tgl_activity,
                            'created_at' => $activity->created_at
                        ],
                        'success_count' => $sentCount,
                        'failed_count' => count($failedRecipients)
                    ]
                ], 207);
            } else {
                // Rollback activity jika semua email gagal
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengirim email ke semua penerima',
                    'data' => [
                        'mailer_used' => $mailerName,
                        'config_source' => $configSource,
                        'sender' => "{$fromConfig['name']} <{$fromConfig['address']}>",
                        'failed_recipients' => array_column($failedRecipients, 'email'),
                        'errors' => array_column($failedRecipients, 'error')
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== CRITICAL ERROR in sendEmail ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper untuk test koneksi mailer
     */
    private function testMailerConnection($mailerName, $fromConfig): array
    {
        try {
            // Coba kirim email test ke diri sendiri
            Mail::mailer($mailerName)->raw('Test connection', function ($message) use ($fromConfig) {
                $message->to($fromConfig['address'])
                    ->subject('Test Connection - ' . date('Y-m-d H:i:s'))
                    ->from($fromConfig['address'], $fromConfig['name']);
            });

            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
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
        $members = DB::table('m_tim_sales_d')
            ->whereNull('deleted_at')
            ->where('tim_sales_id', $timSalesId)
            ->get();

        return $this->successResponse($members);
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
    public function addContractActivity(StoreContractActivityRequest $request): JsonResponse
    {
        $activity = $this->activityService->addContractActivity($request);

        return $this->createdResponse($activity, 'Contract Activity berhasil dibuat dengan nomor: ' . $activity->nomor);
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
        $activities = CustomerActivity::with(['files'])
            ->where('pks_id', $pksId)
            ->where('is_activity', 1)
            ->whereNull('deleted_at')
            ->orderBy('tgl_activity', 'desc')
            ->get();

        return $this->successResponse($activities);
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
    public function assignRO(AssignRoRequest $request): JsonResponse
    {
        $nomor = $this->activityService->assignRO($request);

        return $this->messageResponse('RO berhasil ditugaskan dengan nomor: ' . $nomor, 201);
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
    public function assignCRM(AssignCrmRequest $request): JsonResponse
    {
        $nomor = $this->activityService->assignCRM($request);

        return $this->messageResponse('CRM berhasil ditugaskan dengan nomor: ' . $nomor, 201);
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
    public function updateContractStatus(UpdateContractStatusRequest $request): JsonResponse
    {
        $nomor = $this->activityService->updateContractStatus($request);

        return $this->messageResponse('Status kontrak berhasil diupdate dengan nomor: ' . $nomor, 201);
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

        return $this->successResponse($issues);
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
    public function availableLeads(): JsonResponse
    {
        $user = Auth::user();

        // Gunakan scope dari model dengan select hanya kolom yang diperlukan
        $query = Leads::select([
            'id',
            'nama_perusahaan',
            'branch_id',
            'tgl_leads',
            'pic',
            'no_telp'
        ])
            ->with([
                'branch:id,name' // Hanya ambil id dan name dari branch
            ])
            ->availableForActivity($user);

        $data = $query->get();

        // Transformasi data
        $transformed = $data->map(function ($item) {
            return [
                'id' => $item->id,
                'nama_perusahaan' => $item->nama_perusahaan,
                'nama_branch' => $item->branch ? $item->branch->name : null,
                'tanggal_leads' => Carbon::parse($item->tgl_leads)->isoFormat('D MMMM Y'),
                'pic' => $item->pic,
                'no_telp_pic' => $item->no_telp
            ];
        });

        return $this->successResponse($transformed, 'Data leads tersedia berhasil diambil');
    }


    //=====halper functions=====//
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

}