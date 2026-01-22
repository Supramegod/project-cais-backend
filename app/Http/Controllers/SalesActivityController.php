<?php

namespace App\Http\Controllers;

use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use App\Models\SalesActivity;
use App\Models\SalesActivityFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Sales Activity",
 *     description="API untuk manajemen Sales Activity"
 * )
 */
class SalesActivityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/sales-activity/available-leads",
     *     summary="Get available leads for sales activity",
     *     description="Menampilkan daftar leads yang tersedia untuk sales activity berdasarkan hak akses user",
     *     tags={"Sales Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by nama perusahaan, PIC, or email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="has_active_kebutuhan",
     *         in="query",
     *         description="Filter leads with active kebutuhan",
     *         @OA\Schema(type="boolean", default=true)
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
     *                 @OA\Property(
     *                     property="leads",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="nama_perusahaan", type="string"),
     *                         @OA\Property(property="pic", type="string"),
     *                         @OA\Property(property="telp_perusahaan", type="string"),
     *                         @OA\Property(property="email", type="string"),
     *                         @OA\Property(property="kebutuhan_count", type="integer")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="last_page", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User tidak terautentikasi")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error"
     *     )
     * )
     */
    public function getAvailableLeads(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            // Query leads dengan filter berdasarkan role user
            $query = Leads::filterByUserRole($user)
                ->whereNull('deleted_at')
                ->select('id', 'nama_perusahaan', 'pic', 'telp_perusahaan', 'email')
                ->withCount([
                    'leadsKebutuhan' => function ($q) use ($user) {
                        // Hitung jumlah kebutuhan yang di-assign ke user ini
                        $q->whereNull('deleted_at');
                        $this->applyKebutuhanFilterByUser($q, $user);
                    }
                ])
                ->has('leadsKebutuhan', '>', 0) // Hanya leads yang memiliki kebutuhan
                ->orderBy('nama_perusahaan');

            // Filter pencarian
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nama_perusahaan', 'like', "%{$search}%")
                        ->orWhere('pic', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter hanya leads yang memiliki kebutuhan aktif
            if ($request->boolean('has_active_kebutuhan', true)) {
                $query->whereHas('leadsKebutuhan', function ($q) use ($user) {
                    $q->whereNull('deleted_at');
                    $this->applyKebutuhanFilterByUser($q, $user);
                });
            }

            $perPage = $request->input('per_page', 20);
            $leads = $query->paginate($perPage);

            // Format response
            $formattedLeads = $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'nama_perusahaan' => $lead->nama_perusahaan,
                    'pic' => $lead->pic,
                    'telp_perusahaan' => $lead->telp_perusahaan,
                    'email' => $lead->email,
                    'kebutuhan_count' => $lead->leads_kebutuhan_count,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'leads' => $formattedLeads,
                    'pagination' => [
                        'total' => $leads->total(),
                        'per_page' => $leads->perPage(),
                        'current_page' => $leads->currentPage(),
                        'last_page' => $leads->lastPage(),
                    ]
                ],
                'message' => 'Data leads berhasil diambil'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data leads',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sales-activity/list",
     *     summary="Get list of sales activities",
     *     description="Menampilkan daftar sales activity dengan filter",
     *     tags={"Sales Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="leads_id",
     *         in="query",
     *         description="Filter by leads ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="leads_kebutuhan_id",
     *         in="query",
     *         description="Filter by leads kebutuhan ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data sales activity berhasil diambil"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            // Ambil parameter filter
            $leadsId = $request->input('leads_id');
            $leadsKebutuhanId = $request->input('leads_kebutuhan_id');

            // Query dasar
            $query = SalesActivity::with([
                'lead' => function ($q) {
                    $q->select('id', 'nama_perusahaan', 'pic', 'telp_perusahaan');
                },
                'leadsKebutuhan' => function ($q) {
                    $q->select('id', 'kebutuhan_id', 'tim_sales_d_id')
                        ->with([
                            'kebutuhan' => function ($q2) {
                                $q2->select('id', 'nama');
                            }
                        ]);
                },
                'files',
                'creator' => function ($q) {
                    $q->select('id', 'full_name', 'email');
                }
            ]);

            // Filter berdasarkan leads_id
            if ($leadsId) {
                $query->where('leads_id', $leadsId);
            }

            // Filter berdasarkan leads_kebutuhan_id
            if ($leadsKebutuhanId) {
                $query->where('leads_kebutuhan_id', $leadsKebutuhanId);
            }

            // Filter berdasarkan hak akses user
            $user = Auth::user();
            if ($user) {
                // Gunakan scope yang sudah ada di model Leads untuk filtering
                $leadsIds = Leads::filterByUserRole($user)->pluck('id');
                $query->whereIn('leads_id', $leadsIds);
            }

            // Sorting dan pagination
            $query->orderBy('tgl_activity', 'desc')
                ->orderBy('created_at', 'desc');

            $perPage = $request->input('per_page', 15);
            $activities = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $activities,
                'message' => 'Data sales activity berhasil diambil'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data sales activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/sales-activity/add",
     *     summary="Create new sales activity with file upload",
     *     description="Menyimpan data sales activity baru dengan opsi upload file",
     *     tags={"Sales Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data aktivitas sales baru",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"leads_id", "leads_kebutuhan_id", "tgl_activity", "jenis_activity", "notulen"},
     *                 type="object",
     *                 @OA\Property(property="leads_id", type="integer", example=1),
     *                 @OA\Property(property="leads_kebutuhan_id", type="integer", example=1),
     *                 @OA\Property(property="tgl_activity", type="string", format="date", example="2024-01-15"),
     *                 @OA\Property(property="jenis_activity", type="string", example="Meeting"),
     *                 @OA\Property(property="notulen", type="string", example="Diskusi kebutuhan security"),
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
     *         description="Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sales activity berhasil ditambahkan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="leads_id", type="integer", example=1),
     *                 @OA\Property(property="leads_kebutuhan_id", type="integer", example=1),
     *                 @OA\Property(property="tgl_activity", type="string", example="2024-01-15"),
     *                 @OA\Property(property="jenis_activity", type="string", example="Meeting"),
     *                 @OA\Property(property="notulen", type="string", example="Diskusi kebutuhan security"),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(
     *                     property="files",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_file", type="string", example="document.pdf"),
     *                         @OA\Property(property="url_file", type="string", example="http://example.com/storage/sales-activity/document.pdf")
     *                     )
     *                 )
     *             )
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
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Anda tidak memiliki akses untuk menambahkan activity pada kebutuhan ini")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            // Ekstrak file jika ada (untuk multipart/form-data)
            $requestData = $request->all();

            // Validasi
            $validator = Validator::make($requestData, [
                'leads_id' => 'required|exists:sl_leads,id',
                'leads_kebutuhan_id' => 'required|exists:sl_leads_kebutuhan,id',
                'tgl_activity' => 'required|date',
                'jenis_activity' => 'required|string|max:255',
                'notulen' => 'required|string',
                'files' => 'nullable|array',
                'files.*' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240' // 10MB
            ], [
                'leads_id.required' => 'Leads ID wajib diisi',
                'leads_id.exists' => 'Leads tidak ditemukan',
                'leads_kebutuhan_id.required' => 'Leads Kebutuhan ID wajib diisi',
                'leads_kebutuhan_id.exists' => 'Leads Kebutuhan tidak ditemukan',
                'tgl_activity.required' => 'Tanggal activity wajib diisi',
                'tgl_activity.date' => 'Format tanggal tidak valid',
                'jenis_activity.required' => 'Jenis activity wajib diisi',
                'notulen.required' => 'Notulen wajib diisi',
                'files.*.file' => 'File yang diupload harus berupa file',
                'files.*.mimes' => 'File harus berformat: pdf, doc, docx, jpg, jpeg, atau png',
                'files.*.max' => 'Ukuran file maksimal 10MB',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            // Validasi: Pastikan leads_kebutuhan_id terkait dengan leads_id
            $leadsKebutuhan = LeadsKebutuhan::where('id', $request->leads_kebutuhan_id)
                ->where('leads_id', $request->leads_id)
                ->first();

            if (!$leadsKebutuhan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leads Kebutuhan tidak terkait dengan Leads yang dipilih'
                ], 422);
            }

            // Validasi hak akses user terhadap KEBUTUHAN ini
            $user = Auth::user();
            if ($user) {
                $hasAccess = $this->checkUserAccessToKebutuhan($user, $leadsKebutuhan);

                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses untuk menambahkan activity pada kebutuhan ini'
                    ], 403);
                }
            }

            // Simpan data activity
            $salesActivity = SalesActivity::create([
                'leads_id' => $request->leads_id,
                'leads_kebutuhan_id' => $request->leads_kebutuhan_id,
                'tgl_activity' => $request->tgl_activity,
                'jenis_activity' => $request->jenis_activity,
                'notulen' => $request->notulen,
                'created_by' => $user ? $user->full_name : 'System',
            ]);

            // Handle file uploads dari multipart/form-data
            if ($request->hasFile('files')) {
                $uploadedFiles = $request->file('files');

                foreach ($uploadedFiles as $file) {
                    $this->storeSalesActivityFile($salesActivity->id, $file);
                }
            }

            DB::commit();

            // Load relasi untuk response
            $salesActivity->load([
                'lead' => function ($q) {
                    $q->select('id', 'nama_perusahaan', 'pic');
                },
                'leadsKebutuhan.kebutuhan',
                'files',
                'creator'
            ]);

            return response()->json([
                'success' => true,
                'data' => $salesActivity,
                'message' => 'Sales activity berhasil ditambahkan'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in SalesActivityController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan sales activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sales-activity/view/{id}",
     *     summary="Get sales activity detail",
     *     description="Menampilkan detail sales activity dengan format mirip Customer Activity",
     *     tags={"Sales Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail sales activity berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="tgl_activity", type="string", format="date", example="2024-01-15"),
     *                 @OA\Property(property="jenis_activity", type="string", example="Meeting"),
     *                 @OA\Property(property="notulen", type="string", example="Diskusi kebutuhan security"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="lead",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT. Contoh Perusahaan"),
     *                     @OA\Property(property="pic", type="string", example="John Doe"),
     *                     @OA\Property(property="telp_perusahaan", type="string", example="021-12345678"),
     *                     @OA\Property(property="email", type="string", example="info@contoh.com")
     *                 ),
     *                 @OA\Property(
     *                     property="kebutuhan",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Security Guard")
     *                 ),
     *                 @OA\Property(
     *                     property="files",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_file", type="string", example="Notulen Meeting"),
     *                         @OA\Property(property="url_file", type="string", example="http://example.com/storage/sales-activity/file.pdf"),
     *                         @OA\Property(property="created_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(property="creator", type="string", example="John Doe")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Sales activity tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Anda tidak memiliki akses untuk melihat activity ini")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $salesActivity = SalesActivity::with([
                'lead' => function ($q) {
                    $q->select('id', 'nama_perusahaan', 'pic', 'telp_perusahaan', 'email');
                },
                'leadsKebutuhan' => function ($q) {
                    $q->select('id', 'kebutuhan_id', 'tim_sales_d_id')
                        ->with([
                            'kebutuhan' => function ($q2) {
                                $q2->select('id', 'nama');
                            },
                            'timSales' => function ($q2) {
                                $q2->select('id', 'nama_tim');
                            },
                            'timSalesD' => function ($q2) {
                                $q2->select('id', 'user_id')
                                    ->with([
                                        'user' => function ($q3) {
                                            $q3->select('id', 'full_name', 'email');
                                        }
                                    ]);
                            }
                        ]);
                },
                'files',
                'creator' => function ($q) {
                    $q->select('id', 'full_name', 'email');
                }
            ])->find($id);

            if (!$salesActivity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sales activity tidak ditemukan'
                ], 404);
            }

            // Validasi hak akses terhadap KEBUTUHAN
            $user = Auth::user();
            if ($user) {
                $leadsKebutuhan = LeadsKebutuhan::find($salesActivity->leads_kebutuhan_id);
                if (!$leadsKebutuhan) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data kebutuhan tidak ditemukan'
                    ], 404);
                }

                $hasAccess = $this->checkUserAccessToKebutuhan($user, $leadsKebutuhan);

                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses untuk melihat activity ini'
                    ], 403);
                }
            }

            // Format response seperti CustomerActivityController::view
            $activityData = [
                'id' => $salesActivity->id,
                'jenis_activity' => $salesActivity->jenis_activity,
                'notulen' => $salesActivity->notulen,
                'tgl_activity' => Carbon::parse($salesActivity->tgl_activity)->format('Y-m-d'),
                'created_at' => $salesActivity->created_at,
                'created_by' => $salesActivity->created_by,
                'files' => $salesActivity->files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'nama_file' => $file->nama_file,
                        'url_file' => $file->url_file,
                        'created_at' => Carbon::parse($file->created_at)->format('Y-m-d H:i:s')
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $activityData,
                'message' => 'Detail sales activity berhasil diambil'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in SalesActivityController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail sales activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/sales-activity/kebutuhan/{leadsId}",
     *     summary="Get kebutuhan by leads ID",
     *     description="Mendapatkan opsi leads_kebutuhan berdasarkan leads_id yang difilter berdasarkan sales yang login",
     *     tags={"Sales Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="leadsId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data kebutuhan berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="kebutuhan_id", type="integer"),
     *                     @OA\Property(property="nama", type="string"),
     *                     @OA\Property(property="tim_sales_d_id", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Anda tidak memiliki akses untuk leads ini")
     *         )
     *     )
     * )
     */
    public function getKebutuhanByLeads($leadsId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            // Validasi apakah user memiliki akses ke leads ini
            $hasAccessToLead = Leads::filterByUserRole($user)
                ->where('id', $leadsId)
                ->exists();

            if (!$hasAccessToLead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk leads ini'
                ], 403);
            }

            // Query kebutuhan dengan filter berdasarkan user yang login
            $query = LeadsKebutuhan::where('leads_id', $leadsId)
                ->whereNull('deleted_at')
                ->with([
                    'kebutuhan' => function ($q) {
                        $q->select('id', 'nama');
                    }
                ]);

            // Filter berdasarkan role dan tim sales user
            $this->applyKebutuhanFilterByUser($query, $user);

            $kebutuhanList = $query->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'kebutuhan_id' => $item->kebutuhan_id,
                        'nama' => $item->kebutuhan ? $item->kebutuhan->nama : null,
                        'tim_sales_d_id' => $item->tim_sales_d_id,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $kebutuhanList,
                'message' => 'Data kebutuhan berhasil diambil'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kebutuhan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sales-activity/stats",
     *     summary="Get sales activity statistics",
     *     description="Mendapatkan statistik sales activity",
     *     tags={"Sales Activity"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for statistics",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for statistics",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistik sales activity berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_activities", type="integer"),
     *                 @OA\Property(
     *                     property="by_jenis",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="jenis_activity", type="string"),
     *                         @OA\Property(property="total", type="integer")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="by_month",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="month", type="integer"),
     *                         @OA\Property(property="total", type="integer")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error"
     *     )
     * )
     */
    public function getStats(Request $request)
    {
        try {
            $user = Auth::user();

            // Dapatkan semua leads yang bisa diakses user
            $leadsQuery = Leads::filterByUserRole($user);

            // Filter lebih lanjut berdasarkan kebutuhan yang di-assign ke user
            $leadsIds = $leadsQuery->pluck('id');

            // Dapatkan kebutuhan-kebutuhan yang di-assign ke user ini
            $kebutuhanIdsQuery = LeadsKebutuhan::whereIn('leads_id', $leadsIds)
                ->whereNull('deleted_at');

            $this->applyKebutuhanFilterByUser($kebutuhanIdsQuery, $user);

            $kebutuhanIds = $kebutuhanIdsQuery->pluck('id');

            // Query activity berdasarkan kebutuhan yang di-assign ke user
            $query = SalesActivity::whereIn('leads_kebutuhan_id', $kebutuhanIds);

            // Filter berdasarkan tanggal
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('tgl_activity', [
                    $request->start_date,
                    $request->end_date
                ]);
            }

            $stats = [
                'total_activities' => $query->count(),
                'by_jenis' => $query->selectRaw('jenis_activity, COUNT(*) as total')
                    ->groupBy('jenis_activity')
                    ->get(),
                'by_month' => $query->selectRaw('MONTH(tgl_activity) as month, COUNT(*) as total')
                    ->groupByRaw('MONTH(tgl_activity)')
                    ->orderBy('month')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistik sales activity berhasil diambil'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method untuk menyimpan file sales activity
     *
     * @param int $activityId
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    private function storeSalesActivityFile($activityId, $file)
    {
        try {
            $fileExtension = $file->getClientOriginalExtension();
            $originalFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $fileName = $originalFileName . date("YmdHis") . rand(10000, 99999) . "." . $fileExtension;

            // Simpan file ke disk 'sales-activity' (harus dikonfigurasi di filesystems.php)
            Storage::disk('sales-activity')->put($fileName, file_get_contents($file));

            // Generate URL manual
            $fileUrl = url('document/sales-activity/' . $fileName);

            Log::info('Sales Activity File Generated URL: ' . $fileUrl);
            Log::info('Filename: ' . $fileName);

            // Simpan ke database
            SalesActivityFile::create([
                'activity_sales_id' => $activityId,
                'nama_file' => $file->getClientOriginalName(),
                'url_file' => $fileUrl,
                'created_by' => Auth::user()->full_name,
                'created_at' => Carbon::now()
            ]);

            return $fileName;

        } catch (\Exception $e) {
            Log::error('Error storing sales activity file: ' . $e->getMessage());
            throw new \Exception('Gagal menyimpan file: ' . $e->getMessage());
        }
    }

    /**
     * Helper method untuk memfilter kebutuhan berdasarkan user yang login
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Models\User $user
     * @return void
     */
    private function applyKebutuhanFilterByUser($query, $user)
    {
        // Superadmin bisa melihat semua
        if ($user->cais_role_id == 2) {
            return;
        }

        // Sales division
        if (in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            if ($user->cais_role_id == 29) {
                // Sales - hanya melihat kebutuhan yang diassign ke mereka
                $query->whereHas('timSalesD', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } elseif ($user->cais_role_id == 31) {
                // Sales Leader - melihat kebutuhan seluruh anggota tim
                $tim = \App\Models\TimSalesDetail::where('user_id', $user->id)->first();
                if ($tim) {
                    $memberSales = \App\Models\TimSalesDetail::where('tim_sales_id', $tim->tim_sales_id)
                        ->pluck('user_id')
                        ->toArray();

                    $query->whereHas('timSalesD', function ($q) use ($memberSales) {
                        $q->whereIn('user_id', $memberSales);
                    });
                }
            }
            // Untuk role 30, 32, 33 (Sales lainnya) - tanpa filter khusus
        }
        // RO division - tanpa filter khusus
        // CRM division - tanpa filter khusus
    }

    /**
     * Helper method untuk mengecek akses user terhadap kebutuhan tertentu
     *
     * @param \App\Models\User $user
     * @param \App\Models\LeadsKebutuhan $leadsKebutuhan
     * @return bool
     */
    private function checkUserAccessToKebutuhan($user, $leadsKebutuhan)
    {
        // Superadmin bisa akses semua
        if ($user->cais_role_id == 2) {
            return true;
        }

        // Sales division
        if (in_array($user->cais_role_id, [29, 30, 31, 32, 33])) {
            if ($user->cais_role_id == 29) {
                // Sales - hanya bisa akses kebutuhan yang diassign ke mereka
                return $leadsKebutuhan->timSalesD && $leadsKebutuhan->timSalesD->user_id == $user->id;
            } elseif ($user->cais_role_id == 31) {
                // Sales Leader - bisa akses kebutuhan seluruh anggota tim
                $tim = \App\Models\TimSalesDetail::where('user_id', $user->id)->first();
                if ($tim && $leadsKebutuhan->timSalesD) {
                    $memberSales = \App\Models\TimSalesDetail::where('tim_sales_id', $tim->tim_sales_id)
                        ->pluck('user_id')
                        ->toArray();

                    return in_array($leadsKebutuhan->timSalesD->user_id, $memberSales);
                }
                return false;
            }
            // Untuk role 30, 32, 33 (Sales lainnya) - bisa akses semua kebutuhan
            return true;
        }

        // RO division - bisa akses semua
        if (in_array($user->cais_role_id, [4, 5, 6, 8])) {
            return true;
        }

        // CRM division - bisa akses semua
        if (in_array($user->cais_role_id, [54, 55, 56])) {
            return true;
        }

        // Default: tidak ada akses
        return false;
    }
}