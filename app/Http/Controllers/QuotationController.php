<?php

namespace App\Http\Controllers;

use App\Events\QuotationCreated;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationSite;
use App\Models\TimSalesDetail;
use App\Services\QuotationDuplicationService;
use App\Services\RekontrakService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Quotation;
use App\Models\Leads;
use App\Services\QuotationService;
use App\Services\QuotationBusinessService;
use App\Http\Requests\QuotationStoreRequest;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\QuotationCollection;

/**
 * @OA\Tag(
 *     name="Quotations",
 *     description="API Endpoints for Quotation Management"
 * )
 */
class QuotationController extends Controller
{
    protected $quotationService;
    protected $quotationBusinessService;
    protected $quotationDuplicationService;
    public function __construct(
        QuotationService $quotationService,
        QuotationBusinessService $quotationBusinessService,
        QuotationDuplicationService $quotationDuplicationService
    ) {
        $this->quotationService = $quotationService;
        $this->quotationBusinessService = $quotationBusinessService;
        $this->quotationDuplicationService = $quotationDuplicationService;
    }

    /**
     * @OA\Get(
     *     path="/api/quotations/list",
     *     tags={"Quotations"},
     *     summary="Get all quotations (Optimized)",
     *     description="Retrieves a list of quotations with minimal data for list view",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Start date filter (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="End date filter (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Parameter(
     *         name="company",
     *         in="query",
     *         description="Company ID filter",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="kebutuhan_id",
     *         in="query",
     *         description="Service need ID filter",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Status quotation ID filter",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by company name (uses fulltext search)",
     *         required=false,
     *         @OA\Schema(type="string", example="PT ABC")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination (default: 1)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search_by",
     *         in="query",
     *         description="Column to search in (default: nama_perusahaan)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"nama_perusahaan", "nomor","kebutuhan"}, example="nama_perusahaan")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="QUOT/ION/LEAD-001/012024-00001"),
     *                     @OA\Property(property="step", type="integer", example=12),
     *                     @OA\Property(property="jumlah_site", type="string", example="Single Site"),
     *                     @OA\Property(property="company", type="string", example="PT ION Outsourcing"),
     *                     @OA\Property(property="kebutuhan", type="string", example="Security Service"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Example Company"),
     *                     @OA\Property(property="tgl_quotation", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="tgl_quotation_formatted", type="string", example="1 Januari 2024"),
     *                     @OA\Property(property="sl_quotation_site", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="nama_site", type="string", example="Head Office Jakarta")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="total_per_page", type="integer", example=15)
     *             ),
     *             @OA\Property(property="message", type="string", example="Quotations retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve quotations"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Quotation::select([
                'id',
                'nomor',
                'step',
                'jumlah_site',
                'company_id',
                'company',
                'kebutuhan',
                'nama_perusahaan',
                'tgl_quotation',
                'status_quotation_id',
                'created_at',
                'created_by',
            ])
                ->with([
                    'quotationSites:id,quotation_id,nama_site',
                    'statusQuotation:id,nama'
                ])
                ->byUserRole()
                ->notDeleted()
                ->orderBy('created_at', 'desc');

            if ($request->filled('search')) {
                $searchTerm = $request->search;
                // Ambil parameter search_by, defaultnya ke 'nama_perusahaan'
                $searchBy = $request->get('search_by', 'nama_perusahaan');

                if ($searchBy === 'nama_perusahaan') {
                    // --- LOGIKA FULLTEXT (Kencang untuk nama perusahaan) ---
                    if (str_contains($searchTerm, ' ')) {
                        $searchTerm = '"' . $searchTerm . '"';
                    } else {
                        $searchTerm = $searchTerm . '*';
                    }
                    $query->whereRaw("MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);

                } else {
                    $allowedColumns = ['nomor', 'kebutuhan'];
                    if (in_array($searchBy, $allowedColumns)) {
                        $query->where($searchBy, 'LIKE', '%' . $searchTerm . '%');
                    }
                }
            } else {
                // Filter tanggal default (hanya jalan kalau tidak sedang search)
                $tglDari = $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString());
                $tglSampai = $request->get('tgl_sampai', Carbon::today()->toDateString());
                $query->whereBetween('tgl_quotation', [$tglDari, $tglSampai]);
            }

            // Filter tambahan
            if ($request->filled('branch'))
                $query->where('branch_id', $request->branch);
            if ($request->filled('platform'))
                $query->where('platform_id', $request->platform);
            if ($request->filled('status'))
                $query->where('status_quotation_id', $request->status);
            if ($request->filled('company'))
                $query->where('company_id', $request->company);
            if ($request->filled('kebutuhan_id'))
                $query->where('kebutuhan_id', $request->kebutuhan_id);

            $data = $query->paginate($request->get('per_page', 15));

            $transformedData = $data->getCollection()->transform(function ($quotation) {
                return [
                    'id' => $quotation->id,
                    'nomor' => $quotation->nomor,
                    'step' => $quotation->step,
                    'jumlah_site' => $quotation->jumlah_site,
                    'company_id' => $quotation->company_id,
                    'company' => $quotation->company,
                    'kebutuhan' => $quotation->kebutuhan,
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'tgl_quotation' => $quotation->tgl_quotation,
                    'created_by' => $quotation->created_by,
                    'status_quotation' => $quotation->statusQuotation ? [
                        'id' => $quotation->statusQuotation->id,
                        'nama' => $quotation->statusQuotation->nama
                    ] : null,
                    'sl_quotation_site' => $quotation->quotationSites->map(function ($site) {
                        return [
                            'nama_site' => $site->nama_site
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'total' => $data->total(),
                    'total_per_page' => $data->count(),
                ],
                'message' => 'Quotations retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quotations',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/quotations/add/{tipe_quotation}",
     *     tags={"Quotations"},
     *     summary="Create new quotation",
     *     description="Creates a new quotation with basic information including site details",
     *     security={{"bearerAuth":{}}},
     *       @OA\Parameter(
     *         name="tipe_quotation",
     *         in="path",
     *         required=true,
     *         description="Type of quotation",
     *         @OA\Schema(type="string", enum={"baru", "revisi", "rekontrak","addendum"}, example="baru")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Quotation data with site information",
     *         @OA\JsonContent(
     *             required={"perusahaan_id","entitas","layanan","jumlah_site"},
     *             @OA\Property(property="perusahaan_id", type="integer", description="ID dari leads/perusahaan", example=1),
     *             @OA\Property(property="entitas", type="integer", description="ID company/entitas", example=1),
     *             @OA\Property(property="layanan", type="integer", description="ID layanan/kebutuhan", example=1),
     *             @OA\Property(property="jumlah_site", type="string", enum={"Single Site","Multi Site"}, description="Tipe penempatan site", example="Single Site"),
     *             @OA\Property(property="quotation_referensi_id", type="integer", description="ID quotation referensi untuk revisi/rekontrak", example=1),
     *             
     *             @OA\Property(
     *                 property="nama_site", 
     *                 type="string", 
     *                 description="Wajib diisi jika jumlah_site = Single Site", 
     *                 example="Head Office Jakarta",
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="provinsi", 
     *                 type="integer", 
     *                 description="Wajib diisi jika jumlah_site = Single Site", 
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="kota", 
     *                 type="integer", 
     *                 description="Wajib diisi jika jumlah_site = Single Site", 
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="penempatan", 
     *                 type="string", 
     *                 description="Wajib diisi jika jumlah_site = Single Site", 
     *                 example="Jakarta Pusat",
     *                 maxLength=255
     *             ),
     *             
     *             @OA\Property(
     *                 property="multisite",
     *                 type="array",
     *                 description="Wajib diisi jika jumlah_site = Multi Site",
     *                 @OA\Items(
     *                     type="string",
     *                     maxLength=255,
     *                     example="Site A"
     *                 ),
     *                 example={"Site A", "Site B"}
     *             ),
     *             @OA\Property(
     *                 property="provinsi_multi",
     *                 type="array",
     *                 description="Wajib diisi jika jumlah_site = Multi Site",
     *                 @OA\Items(
     *                     type="integer",
     *                     example=1
     *                 ),
     *                 example={1, 2}
     *             ),
     *             @OA\Property(
     *                 property="kota_multi",
     *                 type="array",
     *                 description="Wajib diisi jika jumlah_site = Multi Site",
     *                 @OA\Items(
     *                     type="integer",
     *                     example=1
     *                 ),
     *                 example={1, 2}
     *             ),
     *             @OA\Property(
     *                 property="penempatan_multi",
     *                 type="array",
     *                 description="Wajib diisi jika jumlah_site = Multi Site",
     *                 @OA\Items(
     *                     type="string",
     *                     maxLength=255,
     *                     example="Jakarta"
     *                 ),
     *                 example={"Jakarta", "Bandung"}
     *             ),
     *             
     *             @OA\Property(
     *                 property="tipe",
     *                 type="string",
     *                 enum={"Quotation Baru","revisi","Quotation Lanjutan"},
     *                 description="Tipe quotation (opsional)",
     *                 example="Quotation Baru"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Quotation created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="QUOT/ION/LEAD-001/012024-00001"),
     *                 @OA\Property(property="nama_perusahaan", type="string", example="PT Example Company"),
     *                 @OA\Property(property="jumlah_site", type="string", example="Single Site"),
     *                 @OA\Property(property="step", type="integer", example=1),
     *                 @OA\Property(property="status_quotation_id", type="integer", example=1),
     *                 @OA\Property(property="sl_quotation_site", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_site", type="string", example="Head Office"),
     *                         @OA\Property(property="provinsi", type="string", example="DKI Jakarta"),
     *                         @OA\Property(property="kota", type="string", example="Jakarta Pusat"),
     *                         @OA\Property(property="penempatan", type="string", example="Jakarta")
     *                     )
     *                 ),
     *                 @OA\Property(property="quotation_pics", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="John Doe"),
     *                         @OA\Property(property="jabatan", type="string", example="Director"),
     *                         @OA\Property(property="email", type="string", example="john@example.com")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Quotation created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="perusahaan_id", type="array",
     *                     @OA\Items(type="string", example="Perusahaan wajib dipilih")
     *                 ),
     *                 @OA\Property(property="nama_site", type="array",
     *                     @OA\Items(type="string", example="Nama site wajib diisi untuk single site")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create quotation"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */

    public function store(QuotationStoreRequest $request, string $tipe_quotation): JsonResponse
    {
        DB::beginTransaction();
        set_time_limit(0);
        try {
            $user = Auth::user();

            if (!in_array($tipe_quotation, ['baru', 'revisi', 'rekontrak', 'addendum'])) {
                throw new \Exception('Tipe quotation tidak valid');
            }

            if (in_array($tipe_quotation, ['revisi', 'rekontrak', 'addendum'])) {
                if (!$request->has('quotation_referensi_id') || !$request->quotation_referensi_id) {
                    throw new \Exception('Quotation referensi wajib dipilih untuk ' . $tipe_quotation);
                }
            }

            $quotationData = $this->quotationBusinessService->prepareQuotationData($request);

            $quotationReferensi = null;
            if ($request->has('quotation_referensi_id') && $request->quotation_referensi_id) {
                $quotationReferensi = Quotation::with([
                    'quotationDetails.quotationDetailHpps',
                    'quotationDetails.quotationDetailCosses',
                    'quotationDetails.wage',
                    'quotationDetails.quotationDetailRequirements',
                    'quotationDetails.quotationDetailTunjangans',
                    'leads',
                    'statusQuotation',
                    'quotationSites',
                    'quotationPics',
                    'quotationAplikasis',
                    'quotationKaporlaps',
                    'quotationDevices',
                    'quotationChemicals',
                    'quotationOhcs',
                    'quotationTrainings',
                    'quotationKerjasamas'
                ])->findOrFail($request->quotation_referensi_id);

                $quotationData['quotation_referensi_id'] = $quotationReferensi->id;
            }

            $quotationData['nomor'] = $this->quotationBusinessService->generateNomorByType(
                $request->perusahaan_id,
                $request->entitas,
                $tipe_quotation,
                $quotationReferensi
            );

            $quotationData['created_by'] = $user->full_name;
            $quotationData['tipe_quotation'] = $tipe_quotation;

            $quotation = Quotation::create($quotationData);

            Log::info('New Quotation created', [
                'id' => $quotation->id,
                'nomor' => $quotation->nomor,
                'tipe' => $tipe_quotation,
                'has_referensi' => $quotationReferensi !== null,
            ]);

            if ($tipe_quotation === 'baru' && $quotationReferensi === null) {
                $this->quotationBusinessService->createQuotationSites(
                    $quotation,
                    $request,
                    $user->full_name
                );

                Log::info('Sites created synchronously', [
                    'quotation_id' => $quotation->id,
                    'sites_count' => $quotation->quotationSites()->count(),
                ]);
            }

            QuotationCreated::dispatch($quotation, $request->all(), $tipe_quotation, $quotationReferensi, $user);

            DB::commit();

            // Reload untuk response
            $quotation->load([
                'quotationSites',
                'quotationPics',
                'quotationDetails',
                'statusQuotation',
            ]);

            $newSitesCount = $quotation->quotationSites->count();

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($quotation),
                'message' => 'Quotation ' . $tipe_quotation . ' created successfully',
                'metadata' => [
                    'sites_created' => $newSitesCount,
                    'tipe_quotation' => $tipe_quotation,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create quotation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create quotation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/quotations/view/{id}",
     *     tags={"Quotations"},
     *     summary="Get quotation details",
     *     description="Retrieves detailed information about a specific quotation including all related data",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Quotation retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found"),
     *             @OA\Property(property="error", type="string", example="No query results for model [App\\Models\\Quotation] 1")
     *         )
     *     )
     * )
     */
    // Di QuotationController
    public function show($id)
    {
        try {
            // Load semua relasi yang diperlukan
            $quotation = Quotation::with([
                'quotationDetails.quotationDetailHpps',
                'quotationDetails.quotationDetailCosses',
                'quotationDetails.wage',
                'quotationDetails.quotationDetailRequirements',
                'quotationDetails.quotationDetailTunjangans',
                'leads',
                'statusQuotation',
                'quotationSites',
                'quotationPics',
                'quotationAplikasis',
                'quotationKaporlaps',
                'quotationDevices',
                'quotationChemicals',
                'quotationOhcs',
                'quotationTrainings',
                'quotationKerjasamas',
                'logNotifications',
                'logApprovals',
            ])->findOrFail($id);

            // ✅ BENAR: Melewatkan model Quotation ke Resource
            return new QuotationResource($quotation);

        } catch (\Exception $e) {
            \Log::error("Error in quotation show: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Delete(
     *     path="/api/quotations/delete/{id}",
     *     tags={"Quotations"},
     *     summary="Delete quotation",
     *     description="Soft deletes a quotation and all its related data",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete quotation"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            $quotation = Quotation::notDeleted()->findOrFail($id);

            // Soft delete relations first
            $this->quotationBusinessService->softDeleteQuotationRelations($quotation, $user->full_name);

            // Update quotation without reloading
            $quotation->deleted_at = Carbon::now();
            $quotation->deleted_by = $user->full_name;
            $quotation->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Quotation deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quotations/{sourceId}/copy/{targetId}",
     *     tags={"Quotations"},
     *     summary="Copy quotation data",
     *     description="Copies all data from source quotation to target quotation",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="sourceId",
     *         in="path",
     *         description="Source quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="targetId",
     *         in="path",
     *         description="Target quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation copied successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Quotation copied successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to copy quotation"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function copy(Request $request, string $sourceId, string $targetId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $sourceQuotation = Quotation::with([
                'quotationSites',
                'quotationDetails',
                'quotationPics',
                'quotationAplikasis',
                'quotationKaporlaps',
                'quotationDevices',
                'quotationChemicals',
                'quotationOhcs',
                'quotationKerjasamas',
                'quotationTrainings'
            ])
                ->notDeleted()
                ->findOrFail($sourceId);

            $targetQuotation = Quotation::notDeleted()->findOrFail($targetId);

            $this->quotationService->copyQuotationData($sourceQuotation, $targetQuotation, Auth::user());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($targetQuotation->fresh()),
                'message' => 'Quotation copied successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to copy quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quotations/{id}/resubmit",
     *     tags={"Quotations"},
     *     summary="Resubmit quotation",
     *     description="Creates a new quotation version from an existing quotation with resubmit reason",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Original quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"alasan"},
     *             @OA\Property(property="alasan", type="string", example="Perubahan kebutuhan client")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Quotation resubmitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Quotation resubmitted successfully")
     *         )
     *     )
     * )
     */
    public function resubmit(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();

            // Get original quotation with all relations
            $originalQuotation = Quotation::with([
                'quotationDetails.quotationDetailHpps',
                'quotationDetails.quotationDetailCosses',
                'quotationDetails.wage',
                'quotationDetails.quotationDetailRequirements',
                'quotationDetails.quotationDetailTunjangans',
                'leads',
                'statusQuotation',
                'quotationSites',
                'quotationPics',
                'quotationAplikasis',
                'quotationKaporlaps',
                'quotationDevices',
                'quotationChemicals',
                'quotationOhcs',
                'quotationTrainings',
                'quotationKerjasamas'
            ])
                ->notDeleted()
                ->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'alasan' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            // Generate new quotation number for resubmit
            $newNomor = $this->quotationService->generateResubmitNomor($originalQuotation->nomor);

            // Create new quotation with resubmit data
            $newQuotation = Quotation::create([
                'nomor' => $newNomor,
                'tgl_quotation' => Carbon::now()->toDateString(),
                'leads_id' => $originalQuotation->leads_id,
                'nama_perusahaan' => $originalQuotation->nama_perusahaan,
                'kebutuhan_id' => $originalQuotation->kebutuhan_id,
                'kebutuhan' => $originalQuotation->kebutuhan,
                'company_id' => $originalQuotation->company_id,
                'company' => $originalQuotation->company,
                'jumlah_site' => $originalQuotation->jumlah_site,
                'step' => 1,
                'status_quotation_id' => 1, // Reset to draft
                'is_aktif' => 0,
                'alasan_resubmit' => $request->alasan,
                'quotation_sebelumnya_id' => $originalQuotation->id,
                'created_by' => $user->full_name
            ]);

            \Log::info('Resubmit: New quotation created', [
                'new_id' => $newQuotation->id,
                'original_id' => $originalQuotation->id,
                'nomor' => $newNomor
            ]);

            // Duplicate all data from original quotation
            $this->quotationDuplicationService->duplicateQuotationData(
                $newQuotation,
                $originalQuotation
            );

            // Deactivate original quotation
            $originalQuotation->update([
                'is_aktif' => 0,
                'updated_by' => $user->full_name
            ]);

            // Load relations for response
            $newQuotation->load([
                'quotationSites',
                'quotationPics',
                'quotationDetails',
                'statusQuotation'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($newQuotation),
                'message' => 'Quotation resubmitted successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to resubmit quotation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resubmit quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/quotations/{id}/submit-approval",
     *     tags={"Quotations"},
     *     summary="Submit quotation for approval",
     *     description="Submits quotation for approval at different levels based on user role (cais_role_id 96, 97, 40)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_approved"},
     *             @OA\Property(property="is_approved", type="boolean", example=true),
     *             @OA\Property(property="alasan", type="string", example="Quotation sudah sesuai dengan requirement")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation submitted for approval successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation approved successfully")
     *         )
     *     )
     * )
     */
    public function submitForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $quotation = Quotation::notDeleted()->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'is_approved' => 'required|boolean',
                'alasan' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()
                ], 422);
            }

            $result = $this->quotationService->submitForApproval($quotation, $request->all(), Auth::user());

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success']
                    ? ($request->is_approved ? 'Quotation approved successfully' : 'Quotation rejected successfully')
                    : $result['message']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit quotation for approval',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/quotations/{id}/reset-approval",
     *     tags={"Quotations"},
     *     summary="Reset approval quotation",
     *     description="Reset status approval quotation kembali ke draft. Hanya user dengan role tertentu yang dapat melakukan reset.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID Quotation yang akan direset",
     *         required=true,
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Approval berhasil direset",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Approval berhasil direset"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=123),
     *                 @OA\Property(property="nomor", type="string", example="QT/2025/01/0001"),
     *                 @OA\Property(property="status_quotation_id", type="integer", example=1),
     *                 @OA\Property(property="is_aktif", type="integer", example=0),
     *                 @OA\Property(property="ot1", type="string", nullable=true, example=null),
     *                 @OA\Property(property="ot2", type="string", nullable=true, example=null),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-12 10:30:00"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(
     *                     property="status_quotation",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Draft")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - User tidak memiliki akses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Anda tidak memiliki akses untuk reset approval.")
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
     *         response=404,
     *         description="Not Found - Quotation tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation tidak ditemukan")
     *         )
     *     )
     * )
     */
    public function resetApproval(Request $request, $id)
    {
        $quotation = Quotation::notDeleted()->findOrFail($id);
        $user = $request->user();
        $result = $this->quotationService->resetApproval($quotation, $user);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Approval berhasil direset',
            'data' => $result['data']
        ]);
    }
    /**
     * @OA\Get(
     *     path="/api/quotations/{id}/calculate",
     *     tags={"Quotations"},
     *     summary="Calculate quotation",
     *     description="Performs calculation for quotation including HPP, COSS, and financial details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Quotation calculated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to calculate quotation"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function calculate(string $id): JsonResponse
    {
        try {
            $quotation = Quotation::with([
                'quotationDetails',
                'quotationSites',
                'quotationDetails.quotationDetailTunjangans',
                'quotationDetails.quotationDetailHpps',
                'quotationDetails.quotationDetailCosses'
            ])
                ->notDeleted()
                ->findOrFail($id);

            $calculatedQuotation = $this->quotationService->calculateQuotation($quotation);

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($calculatedQuotation),
                'message' => 'Quotation calculated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/quotations/{id}/export-pdf",
     *     tags={"Quotations"},
     *     summary="Export quotation to PDF",
     *     description="Generates and returns PDF document for quotation",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to generate PDF"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function exportPdf(string $id): JsonResponse
    {
        try {
            $quotation = Quotation::with([
                'leads',
                'quotationSites',
                'quotationDetails',
                'quotationPics',
                'company'
            ])
                ->notDeleted()
                ->findOrFail($id);

            $calculatedQuotation = $this->quotationService->calculateQuotation($quotation);

            // In a real implementation, you would generate PDF here
            // For now, returning success response
            return response()->json([
                'success' => true,
                'data' => [
                    'quotation' => new QuotationResource($calculatedQuotation),
                    'pdf_url' => url("/api/quotations/{$id}/pdf-download"),
                    'message' => 'PDF export ready'
                ],
                'message' => 'PDF generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/quotations/{id}/status",
     *     tags={"Quotations"},
     *     summary="Get quotation status",
     *     description="Retrieves current status and approval progress of quotation",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Quotation ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="status_quotation_id", type="integer", example=3),
     *                 @OA\Property(property="status_quotation", type="string", example="Approved"),
     *                 @OA\Property(property="step", type="integer", example=12),
     *                 @OA\Property(property="approval_progress", type="object",
     *                     @OA\Property(property="ot1", type="string", example="approved"),
     *                     @OA\Property(property="ot2", type="string", example="approved"),
     *                     @OA\Property(property="ot3", type="string", example="pending")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Status retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function getStatus(string $id): JsonResponse
    {
        try {
            $quotation = Quotation::with(['statusQuotation'])
                ->notDeleted()
                ->findOrFail($id);

            $approvalProgress = [
                'ot1' => $quotation->status_ot1,
                'ot2' => $quotation->status_ot2,
                'ot3' => $quotation->status_ot3,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'status_quotation_id' => $quotation->status_quotation_id,
                    'status_quotation' => $quotation->statusQuotation->nama ?? 'Unknown',
                    'step' => $quotation->step,
                    'approval_progress' => $approvalProgress,
                    'is_aktif' => $quotation->is_aktif,
                    'can_create_spk' => $quotation->is_aktif == 1
                ],
                'message' => 'Status retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/quotations/available-leads/{tipe_quotation}",
     *     summary="Mendapatkan daftar leads yang tersedia untuk aktivitas berdasarkan tipe quotation",
     *     description="Endpoint ini digunakan untuk mengambil leads yang tersedia untuk dilakukan aktivitas sales selanjutnya.",
     *     tags={"Quotations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tipe_quotation",
     *         in="path",
     *         description="Tipe quotation untuk filter leads",
     *         required=true,
     *         @OA\Schema(type="string", enum={"baru", "revisi", "rekontrak"}, example="baru")
     *     ),
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
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *                     @OA\Property(property="pic", type="string", example="John Doe"),
     *                     @OA\Property(property="wilayah", type="string", example="Jakarta"),
     *                     @OA\Property(property="status_leads", type="string", example="New Lead")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function availableLeads($tipe_quotation)
    {
        try {
            // Validasi parameter tipe_quotation
            if (!in_array($tipe_quotation, ['baru', 'revisi', 'rekontrak', 'addendum'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tipe_quotation harus diisi dengan nilai: baru, revisi, rekontrak, atau addendum'
                ], 400);
            }

            $user = auth()->user();

            // Base query dengan relasi yang diperlukan
            $query = Leads::with([
                'statusLeads:id,nama',
                'branch:id,name'
            ])
                ->filterByUserRole();

            // Filter berdasarkan tipe quotation menggunakan switch case
            switch ($tipe_quotation) {
                case 'baru':
                    // Leads baru: status_leads_id = 1 (New Lead)
                    $query;
                    break;

                case 'rekontrak':
                    // Leads rekontrak: status_leads_id = 102
                    $query->where('status_leads_id', 102)
                        ->whereHas('quotations', function ($q) {
                            // Menggunakan nested parameter grouping untuk logic OR
                            $q->where(function ($innerQuery) {
                                $innerQuery->where('status_quotation_id', 3)
                                    ->orWhereNotNull('ot1');
                            });

                            // Contoh jika kontrak_akhir ingin diaktifkan kembali:
                            // $q->whereBetween('kontrak_akhir', [now(), now()->addMonths(1)]);
                        });
                    break;

                case 'revisi':
                    // Leads revisi: status_leads_id bukan 3 (misalnya exclude Draft)
                    // Atau bisa juga status tertentu untuk revisi
                    $query->where('status_leads_id', '!=', 3);
                    break;
            }

            // Order by terbaru
            $query->orderBy('created_at', 'desc');

            $leads = $query->get();

            // Mapping data sederhana
            $data = $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'nama_perusahaan' => $lead->nama_perusahaan,
                    'pic' => $lead->pic,
                    'wilayah' => $lead->branch->name ?? 'Unknown',
                    'status_leads_id' => $lead->status_leads_id,
                    'status_leads' => $lead->statusLeads->nama ?? 'Unknown',
                    'customer_id' => $lead->customer_id,
                    'telp_perusahaan' => $lead->telp_perusahaan,
                    'email' => $lead->email,
                    'created_at' => $lead->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => "Data leads untuk quotation {$tipe_quotation} berhasil diambil",
                'data' => $data,

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
     *     path="/api/quotations/reference/{leads_id}",
     *     tags={"Quotations"},
     *     summary="Get quotation references for revision or recontract",
     *     description="Retrieves list of quotations by leads ID that can be used as reference based on quotation type",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="leads_id",
     *         in="path",
     *         description="Leads ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="tipe_quotation",
     *         in="query",
     *         description="Type of quotation to filter references",
     *         required=true,
     *         @OA\Schema(type="string", enum={"baru", "revisi", "rekontrak", "addendum"}, example="revisi")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation references retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="QUOT/ION/LEAD-001/012024-00001"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Example Company"),
     *                     @OA\Property(property="tgl_quotation", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="jumlah_site", type="string", example="Single Site"),
     *                     @OA\Property(property="step", type="integer", example=12),
     *                     @OA\Property(property="is_aktif", type="boolean", example=true),
     *                     @OA\Property(property="status_quotation_id", type="integer", example=3),
     *                     @OA\Property(property="status_quotation", type="string", example="Approved")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Quotation references retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leads not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Leads not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve quotation references")
     *         )
     *     )
     * )
     */
    public function getReferenceQuotations(string $leadsId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipe_quotation' => 'required|in:baru,revisi,rekontrak,addendum'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Cek apakah leads exists dan memiliki quotation
            $leads = Leads::withoutTrashed()->findOrFail($leadsId);

            $totalQuotations = Quotation::where('leads_id', $leadsId)
                ->withoutTrashed()
                ->count();

            \Log::info("Leads {$leadsId} has {$totalQuotations} quotations");

            $quotations = $this->quotationBusinessService->getFilteredQuotations($leadsId, $request->tipe_quotation);

            return response()->json([
                'success' => true,
                'data' => $quotations,
                'debug' => [ // Tambahkan debug info
                    'leads_id' => $leadsId,
                    'tipe_quotation' => $request->tipe_quotation,
                    'total_quotations' => $totalQuotations,
                    'filtered_count' => count($quotations)
                ],
                'message' => 'Quotation references retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quotation references',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/quotations/hc-high-cost",
     *     summary="Get sites with HC >= 12 and cost per HC >= 6.5 million",
     *     tags={"Quotations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of sites meeting criteria",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="site_id", type="integer", example=1),
     *                 @OA\Property(property="nama_site", type="string", example="Site Jakarta Pusat"),
     *                 @OA\Property(property="jumlah_hc", type="integer", example=15),
     *                 @OA\Property(property="wilayah", type="string", example="Jakarta"),
     *                 @OA\Property(property="biaya_per_hc", type="number", format="float", example=6800000),
     *                 @OA\Property(property="total_biaya_site", type="number", format="float", example=102000000),
     *                 @OA\Property(property="quotation_id", type="integer", example=123),
     *                 @OA\Property(property="nomor_quotation", type="string", example="Q001/2024")
     *             ))
     *         )
     *     )
     * )
     */
    public function getSitesWithHighHcAndCost(Request $request)
    {
        set_time_limit(0);
        try {
            // Validasi parameter
            $request->validate([
                'min_hc' => 'nullable|integer|min:1',
                'min_cost_per_hc' => 'nullable|numeric|min:0',
            ]);

            $minHc = $request->input('min_hc', 12);
            $minCostPerHc = $request->input('min_cost_per_hc', 6500000);

            // Query menggunakan Eloquent dengan relasi
            $data = QuotationDetailHpp::with([
                'quotationDetail.quotationSite',
                'quotation.leads.branch.city.province',
                'quotationDetail.position'
            ])
                ->where('jumlah_hc', '>=', $minHc)
                ->where('total_biaya_per_personil', '>=', $minCostPerHc)
                ->whereHas('quotation', function ($query) {
                    $query->where('is_aktif', 1);
                })
                ->get();

            // Group by site untuk summary
            $groupedBySite = $data->groupBy(function ($item) {
                return $item->quotationDetail->quotation_site_id;
            })->map(function ($items) {
                $firstItem = $items->first();
                $site = $firstItem->quotationDetail->quotationSite;
                $quotation = $firstItem->quotation;
                $branch = $quotation->leads->branch;

                return [
                    'site_id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'wilayah' => $site->kota . ', ' . $site->provinsi,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'branch_name' => $branch->name,
                    'city_name' => $branch->city->name ?? null,
                    'province_name' => $branch->city->province->name ?? null,
                    'nomor_quotation' => $quotation->nomor,
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'jumlah_posisi' => $items->count(),
                    'total_hc' => $items->sum('jumlah_hc'),
                    'total_biaya' => (float) number_format($items->sum('total_biaya_all_personil'), 2, '.', ''),
                    'avg_biaya_per_hc' => $items->sum('jumlah_hc') > 0
                        ? (float) number_format($items->sum('total_biaya_all_personil') / $items->sum('jumlah_hc'), 2, '.', '')
                        : 0,
                    'posisi' => $items->map(function ($item) {
                        return [
                            'position_name' => $item->quotationDetail->position->name ?? null,
                            'jumlah_hc' => (int) $item->jumlah_hc,
                            'gaji_pokok' => (float) $item->gaji_pokok,
                            'total_tunjangan' => (float) $item->total_tunjangan,
                            'biaya_per_personil' => (float) $item->total_biaya_per_personil,
                            'total_biaya' => (float) $item->total_biaya_all_personil,
                        ];
                    })->values()->toArray()
                ];
            })->values();

            // Group by branch
            $groupedByBranch = $data->groupBy(function ($item) {
                return $item->quotation->leads->branch_id;
            })->map(function ($items, $branchId) {
                $firstItem = $items->first();
                $branch = $firstItem->quotation->leads->branch;

                $sites = $items->groupBy(function ($item) {
                    return $item->quotationDetail->quotation_site_id;
                })->map(function ($siteItems) {
                    $firstSite = $siteItems->first();
                    $site = $firstSite->quotationDetail->quotationSite;

                    return [
                        'site_id' => $site->id,
                        'nama_site' => $site->nama_site,
                        'wilayah' => $site->kota . ', ' . $site->provinsi,
                        'jumlah_posisi' => $siteItems->count(),
                        'total_hc' => $siteItems->sum('jumlah_hc'),
                        'total_biaya' => (float) number_format($siteItems->sum('total_biaya_all_personil'), 2, '.', ''),
                    ];
                })->values()->toArray();

                $totalHc = $items->sum('jumlah_hc');
                $totalBiaya = $items->sum('total_biaya_all_personil');

                return [
                    'branch_id' => $branchId,
                    'branch_name' => $branch->name,
                    'city_name' => $branch->city->name ?? null,
                    'province_name' => $branch->city->province->name ?? null,
                    'total_sites' => $items->groupBy(function ($item) {
                        return $item->quotationDetail->quotation_site_id;
                    })->count(),
                    'total_hc' => $totalHc,
                    'total_biaya' => (float) number_format($totalBiaya, 2, '.', ''),
                    'avg_cost_per_hc' => $totalHc > 0
                        ? (float) number_format($totalBiaya / $totalHc, 2, '.', '')
                        : 0,
                    'sites' => $sites,
                ];
            })->values();

            // Summary
            $summary = [
                'total_records' => $data->count(),
                'total_branches' => $data->groupBy(function ($item) {
                    return $item->quotation->leads->branch_id;
                })->count(),
                'total_sites' => $data->groupBy(function ($item) {
                    return $item->quotationDetail->quotation_site_id;
                })->count(),
                'total_hc' => $data->sum('jumlah_hc'),
                'total_biaya' => (float) number_format($data->sum('total_biaya_all_personil'), 2, '.', ''),
                'avg_cost_per_hc' => $data->sum('jumlah_hc') > 0
                    ? (float) number_format($data->sum('total_biaya_all_personil') / $data->sum('jumlah_hc'), 2, '.', '')
                    : 0,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'sites' => $groupedBySite,
                    'branches' => $groupedByBranch,
                    'summary' => $summary,
                    'criteria' => [
                        'min_hc' => $minHc,
                        'min_cost_per_hc' => number_format($minCostPerHc, 0, ',', '.'),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error("Error fetching sites with high HC and cost: " . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    /**
     * Check if site already exists for this leads (optimized)
     */
    private function checkSiteExists($leadsId, $namaSite, $provinsiId, $kotaId): bool
    {
        return QuotationSite::where('leads_id', $leadsId)
            ->whereRaw('LOWER(TRIM(nama_site)) = ?', [strtolower(trim($namaSite))])
            ->where('provinsi_id', $provinsiId)
            ->where('kota_id', $kotaId)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Create only new sites (skip existing ones)
     */
    private function createNewSitesOnly(Quotation $quotation, Request $request, string $createdBy): void
    {
        if ($request->jumlah_site == "Multi Site") {
            foreach ($request->multisite as $key => $value) {
                // Cek apakah site sudah existing
                $isExisting = $this->checkSiteExists(
                    $request->perusahaan_id,
                    $value,
                    $request->provinsi_multi[$key],
                    $request->kota_multi[$key]
                );

                if (!$isExisting) {
                    $this->quotationBusinessService->createQuotationSite(
                        $quotation,
                        $request,
                        $key,
                        true,
                        $createdBy
                    );
                } else {
                    \Log::info('Skip creating existing site', [
                        'nama_site' => $value,
                        'leads_id' => $request->perusahaan_id
                    ]);
                }
            }
        } else {
            // Cek apakah site sudah existing
            $isExisting = $this->checkSiteExists(
                $request->perusahaan_id,
                $request->nama_site,
                $request->provinsi,
                $request->kota
            );

            if (!$isExisting) {
                $this->quotationBusinessService->createQuotationSite(
                    $quotation,
                    $request,
                    null,
                    false,
                    $createdBy
                );
            } else {
                \Log::info('Skip creating existing site', [
                    'nama_site' => $request->nama_site,
                    'leads_id' => $request->perusahaan_id
                ]);
            }
        }
    }

    /**
     * Link quotation to existing sites
     */
    private function linkExistingSites(Quotation $quotation, Request $request, string $createdBy): void
    {
        if ($request->jumlah_site == "Multi Site") {
            foreach ($request->multisite as $key => $value) {
                // Cari site yang sudah ada
                $existingSite = QuotationSite::where('leads_id', $request->perusahaan_id)
                    ->where('nama_site', $value)
                    ->where('provinsi_id', $request->provinsi_multi[$key])
                    ->where('kota_id', $request->kota_multi[$key])
                    ->first();

                if ($existingSite) {
                    // Duplikat site untuk quotation baru (dengan quotation_id yang berbeda)
                    $newSite = $existingSite->replicate();
                    $newSite->quotation_id = $quotation->id;
                    $newSite->created_by = $createdBy;
                    $newSite->created_at = Carbon::now();
                    $newSite->save();

                    \Log::info('Linked to existing site (duplicated)', [
                        'old_site_id' => $existingSite->id,
                        'new_site_id' => $newSite->id,
                        'nama_site' => $value
                    ]);
                }
            }
        } else {
            // Cari site yang sudah ada
            $existingSite = QuotationSite::where('leads_id', $request->perusahaan_id)
                ->where('nama_site', $request->nama_site)
                ->where('provinsi_id', $request->provinsi)
                ->where('kota_id', $request->kota)
                ->first();

            if ($existingSite) {
                // Duplikat site untuk quotation baru
                $newSite = $existingSite->replicate();
                $newSite->quotation_id = $quotation->id;
                $newSite->created_by = $createdBy;
                $newSite->created_at = Carbon::now();
                $newSite->save();

                \Log::info('Linked to existing site (duplicated)', [
                    'old_site_id' => $existingSite->id,
                    'new_site_id' => $newSite->id,
                    'nama_site' => $request->nama_site
                ]);
            }
        }
    }
}