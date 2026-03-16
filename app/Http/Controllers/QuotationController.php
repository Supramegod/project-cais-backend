<?php

namespace App\Http\Controllers;

use App\Events\QuotationCreated;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\LeadsKebutuhan;
use App\Models\LogApproval;
use App\Models\LogNotification;
use App\Models\QuotationDetailHpp;
use App\Models\QuotationSite;
use App\Models\TimSalesDetail;
use App\Models\User;
use App\Services\AddendumService;
use App\Services\QuotationDuplicationService;
use App\Services\QuotationNotificationService;
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
        QuotationDuplicationService $quotationDuplicationService,
        QuotationNotificationService $quotationNotificationService
    ) {
        $this->quotationService = $quotationService;
        $this->quotationBusinessService = $quotationBusinessService;
        $this->quotationDuplicationService = $quotationDuplicationService;
        $this->quotationNotificationService = $quotationNotificationService;
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
     *         @OA\Schema(type="string", enum={"nama_perusahaan", "nomor","kebutuhan","created_by"}, example="nama_perusahaan")
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
                'leads_id',            // ✅ WAJIB — untuk byUserRole dan eager load
                'nomor',
                'step',
                'jumlah_site',
                'company_id',
                'company',
                'kebutuhan',
                'nama_perusahaan',
                'tgl_quotation',
                'status_quotation_id', // ✅ sudah ada, untuk eager load statusQuotation
                'created_at',
                'created_by',
            ])
                ->with([
                    'quotationSites:id,quotation_id,nama_site',
                    'statusQuotation:id,nama',
                ])
                ->byUserRole()
                ->orderBy('created_at', 'desc');

            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $searchBy = $request->get('search_by', 'nama_perusahaan');

                if ($searchBy === 'nama_perusahaan') {
                    $searchTerm = str_contains($searchTerm, ' ')
                        ? '"' . $searchTerm . '"'
                        : $searchTerm . '*';
                    $query->whereRaw("MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)", [$searchTerm]);
                } elseif (in_array($searchBy, ['nomor', 'kebutuhan', 'created_by'])) {
                    $query->where($searchBy, 'LIKE', '%' . $searchTerm . '%');
                }
            } else {
                $tglDari = $request->get('tgl_dari', Carbon::today()->subMonths(6)->toDateString());
                $tglSampai = $request->get('tgl_sampai', Carbon::today()->toDateString());
                $query->whereBetween('tgl_quotation', [$tglDari, $tglSampai]);
            }

            if ($request->filled('branch'))
                $query->whereHas('leads', fn($q) => $q->where('branch_id', $request->branch));
            if ($request->filled('platform'))
                $query->whereHas('leads', fn($q) => $q->where('platform_id', $request->platform));
            if ($request->filled('status'))
                $query->where('status_quotation_id', $request->status);
            if ($request->filled('company'))
                $query->where('company_id', $request->company);
            if ($request->filled('kebutuhan_id'))
                $query->where('kebutuhan_id', $request->kebutuhan_id);

            $data = $query->paginate($request->get('per_page', 15));

            $data->getCollection()->transform(function ($quotation) {
                return [
                    'id' => $quotation->id,
                    'nomor' => $quotation->nomor,
                    'step' => $quotation->step,
                    'jumlah_site' => $quotation->jumlah_site,
                    'company_id' => $quotation->company_id,
                    'company' => $quotation->company,
                    'kebutuhan' => $quotation->kebutuhan,
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'tgl_quotation' => $quotation->getRawOriginal('tgl_quotation'),
                    'created_by' => $quotation->created_by,
                    'status_quotation' => $quotation->statusQuotation
                        ? ['id' => $quotation->statusQuotation->id, 'nama' => $quotation->statusQuotation->nama]
                        : null,
                    'sl_quotation_site' => $quotation->quotationSites->map(fn($site) => [
                        'nama_site' => $site->nama_site,
                    ]),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'total' => $data->total(),
                    'total_per_page' => $data->count(),
                ],
                'message' => 'Quotations retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quotations',
                'error' => $e->getMessage(),
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
        set_time_limit(0);
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
            // TAMBAHKAN: with(['quotationDetails.wage']) agar data THR terbaca di Service
            $quotation = Quotation::notDeleted()
                ->with(['quotationDetails.wage'])
                ->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'is_approved' => 'required|boolean',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors() // Lebih rapi jika dipisah
                ], 422);
            }

            // Panggil service yang sudah kita update logikanya tadi
            $result = $this->submitApproval($quotation, $request->all(), Auth::user());

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => $request->is_approved ? 'Quotation approved successfully' : 'Quotation rejected successfully',
                'data' => $result['data'] ?? null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process approval',
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
        $result = $this->reset_Approval($quotation, $user);

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
            $query = Leads::select('id', 'nama_perusahaan', 'pic', 'status_leads_id', 'branch_id', 'customer_id')
                ->with([
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

            // ✅ 1 query — cukup untuk validasi leads exists
            Leads::withoutTrashed()->findOrFail($leadsId);

            $quotations = $this->getFilteredQuotations($leadsId, $request->tipe_quotation);

            return response()->json([
                'success' => true,
                'data' => $quotations,
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
    private const ROLE_GM_1 = 10;
    private const ROLE_GM_2 = 53;
    private const ROLE_DIREKTUR_SALES = 96;
    private const ROLE_DIREKTUR_KEUANGAN = 97;

    public function submitApproval(Quotation $quotation, array $data, User $user): array
    {
        $isApproved = filter_var($data['is_approved'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $currentDateTime = Carbon::now();
        $notes = $data['notes'] ?? null;

        return match ($user->cais_role_id) {
            self::ROLE_GM_1 => $this->handleGM1Approval($quotation, $isApproved, $notes, $user, $currentDateTime),
            self::ROLE_GM_2 => $this->handleGM2Approval($quotation, $isApproved, $notes, $user, $currentDateTime),
            self::ROLE_DIREKTUR_SALES => $this->handleSalesApproval($quotation, $isApproved, $notes, $user, $currentDateTime),
            self::ROLE_DIREKTUR_KEUANGAN => $this->handleKeuanganApproval($quotation, $isApproved, $notes, $user, $currentDateTime),
            default => ['success' => false, 'message' => 'User tidak memiliki akses approval.'],
        };
    }

    // ============================================================
// LEVEL 3 - GM 1
// ============================================================
    private function handleGM1Approval(
        Quotation $quotation,
        bool $isApproved,
        ?string $notes,
        User $user,
        Carbon $now
    ): array {

        $quotation->update([
            'ot3' => $isApproved ? $user->full_name : null,
            'status_quotation_id' => $isApproved ? 2 : 8,
            'is_aktif' => 0,
            'updated_at' => $now,
            'updated_by' => $user->full_name,
        ]);

        $this->logApproval($quotation, $user, $isApproved, $notes, tingkat: 1, now: $now);

        $freshQuotation = $quotation->fresh();
        if (!empty($freshQuotation->ot4)) {
            $this->notifyDirSales($freshQuotation, $now);
        }

        // Jika reject, notifikasi sales agar tahu quotation ditolak
        if (!$isApproved) {
            $this->sendNotificationToSales($quotation->fresh(), $user, $isApproved, $notes);
        }

        return ['success' => true, 'data' => $quotation->fresh()];
    }

    // ============================================================
// LEVEL 4 - GM 2
// ============================================================
    private function handleGM2Approval(
        Quotation $quotation,
        bool $isApproved,
        ?string $notes,
        User $user,
        Carbon $now
    ): array {
        $quotation->update([
            'ot4' => $isApproved ? $user->full_name : null,
            'status_quotation_id' => $isApproved ? 2 : 8,  // sama
            'is_aktif' => 0,
            'updated_at' => $now,
            'updated_by' => $user->full_name,
        ]);

        $this->logApproval($quotation, $user, $isApproved, $notes, tingkat: 1, now: $now);

        $freshQuotation = $quotation->fresh();
        if (!empty($freshQuotation->ot3)) {
            $this->notifyDirSales($freshQuotation, $now);
        }


        // Jika reject, notifikasi sales agar tahu quotation ditolak
        if (!$isApproved) {
            $this->sendNotificationToSales($quotation->fresh(), $user, $isApproved, $notes);
        }

        return ['success' => true, 'data' => $quotation->fresh()];
    }

    // ============================================================
// LEVEL 1 - Direktur Sales
// ============================================================
    private function handleSalesApproval(
        Quotation $quotation,
        bool $isApproved,
        ?string $notes,
        User $user,
        Carbon $now
    ): array {
        // Guard clause — pastikan kedua GM sudah approve terlebih dahulu
        if (empty($quotation->ot3) || empty($quotation->ot4)) {
            return [
                'success' => false,
                'message' => 'Quotation harus disetujui oleh GM 1 dan GM 2 terlebih dahulu.',
            ];
        }

        $needsLevel2 = $isApproved && $this->requiresLevel2Approval($quotation);

        $quotation->update([
            'ot1' => $user->full_name,
            'status_quotation_id' => $isApproved ? ($needsLevel2 ? 2 : 3) : 8,
            'is_aktif' => $isApproved ? ($needsLevel2 ? 0 : 1) : 0,
            'updated_at' => $now,
            'updated_by' => $user->full_name,
        ]);

        $this->logApproval($quotation, $user, $isApproved, $notes, tingkat: 2, now: $now);

        if ($needsLevel2) {
            $this->notifyDirKeu($quotation->fresh(), $now);
        }

        return $this->finalizeApproval($quotation, $user, $isApproved, $notes);
    }

    // ============================================================
// LEVEL 2 - Direktur Keuangan  (tidak ada perubahan)
// ============================================================
    private function handleKeuanganApproval(
        Quotation $quotation,
        bool $isApproved,
        ?string $notes,
        User $user,
        Carbon $now
    ): array {
        if (empty($quotation->ot1)) {
            return ['success' => false, 'message' => 'Quotation belum disetujui oleh Direktur Sales.'];
        }

        $quotation->update([
            'ot2' => $user->full_name,
            'status_quotation_id' => $isApproved ? 3 : 8,
            'is_aktif' => $isApproved ? 1 : 0,
            'updated_at' => $now,
            'updated_by' => $user->full_name,
        ]);

        $this->logApproval($quotation, $user, $isApproved, $notes, tingkat: 3, now: $now);

        return $this->finalizeApproval($quotation, $user, $isApproved, $notes);
    }


    // ============================================================
// HELPERS
// ============================================================

    /** Cek apakah butuh eskalasi ke Direktur Keuangan */
    private function requiresLevel2Approval(Quotation $quotation): bool
    {
        // 1. Pastikan relasi sudah di-load agar pengecekan THR akurat
        $quotation->loadMissing('quotationDetails.wage');


        $hasNonProvisionalThr = $quotation->quotationDetails->contains(function ($detail) {
            $thr = strtolower(trim($detail->wage->thr ?? ''));
            return !empty($thr) && !in_array($thr, ['diprovisikan', 'tidak ada']);
        });

        $isLongTop = trim($quotation->top) === 'Lebih Dari 7 Hari';



        return $isLongTop || $hasNonProvisionalThr;
    }

    /** Proses akhir setelah approve: notif sales + addendum */
    private function finalizeApproval(
        Quotation $quotation,
        User $user,
        bool $isApproved,
        ?string $notes
    ): array {
        // Panggil fresh() SEKALI saja
        $freshQuotation = $quotation->fresh();

        $this->sendNotificationToSales($freshQuotation, $user, $isApproved, $notes);

        if (
            $isApproved
            && $freshQuotation->status_quotation_id === 3
            && $freshQuotation->tipe_quotation === 'addendum'
        ) {
            app(AddendumService::class)->process($freshQuotation);
        }

        return ['success' => true, 'data' => $freshQuotation];
    }

    /** Wrapper logging agar tidak duplikat di setiap handler */
    private function logApproval(
        Quotation $quotation,
        User $user,
        bool $isApproved,
        ?string $notes,
        int $tingkat,
        Carbon $now
    ): void {
        LogApproval::create([
            'tabel' => 'quotation',
            'doc_id' => $quotation->id,
            'tingkat' => $tingkat,
            'is_approve' => $isApproved,
            'note' => $notes,
            'user_id' => $user->id,
            'approval_date' => $now,
            'created_at' => $now,
            'created_by' => $user->full_name,
        ]);
    }

    private function sendNotificationToSales(Quotation $quotation, User $approver, bool $isApproved, ?string $notes): void
    {
        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        if (!$leadsKebutuhan || !$leadsKebutuhan->timSalesD) {
            return;
        }

        $salesUserId = $leadsKebutuhan->timSalesD->user_id ?? null;
        if (!$salesUserId) {
            return;
        }

        $status = $isApproved ? 'disetujui' : 'ditolak';
        $approverRole = $approver->cais_role_id == 96 ? 'Direktur Sales' : 'Direktur Keuangan';
        $msg = "Quotation dengan nomor: {$quotation->nomor} telah {$status} oleh {$approverRole}.";

        if ($notes) {
            $msg .= " Catatan: {$notes}";
        }

        LogNotification::create([
            'user_id' => $salesUserId,
            'doc_id' => $quotation->id,
            'transaksi' => 'Quotation',
            'tabel' => 'sl_quotation',
            'pesan' => $msg,
            'is_read' => 0,
            'created_at' => Carbon::now(),
            'created_by' => $approver->full_name
        ]);

        // $approvalUrl = 'https://caisshelter.pages.dev/quotation/view/' . $quotation->id;
        // $this->quotationNotificationService->sendApprovalNotification(
        //     quotation: $quotation,
        //     creatorName: $approver->full_name,
        //     approvalUrl: $approvalUrl,
        //     overrideRecipients: [$salesUserId]
        // );
    }

    // 2. Di submitForApproval ketika Dir Sales approve
    private function notifyDirKeu(Quotation $quotation, Carbon $currentDateTime): void
    {
        $dirKeu = [27928, 16986, 127823];

        $hasNonProvisionalThr = $quotation->quotationDetails->contains(function ($detail) {
            $thr = strtolower(trim($detail->wage->thr ?? ''));
            return $thr !== 'diprovisikan';
        });

        if (!($quotation->top == 'Lebih Dari 7 Hari' || $hasNonProvisionalThr)) {
            return;
        }

        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        $creatorName = $leadsKebutuhan->timSalesD->nama ?? Auth::user()->full_name;
        $msg = "Quotation dengan nomor: {$quotation->nomor} telah disetujui Direktur Sales dan membutuhkan persetujuan Direktur Keuangan.";

        foreach ($dirKeu as $userId) {
            LogNotification::create([
                'user_id' => $userId,
                'doc_id' => $quotation->id,
                'transaksi' => 'Quotation',
                'tabel' => 'sl_quotation',
                'pesan' => $msg,
                'is_read' => 0,
                'created_at' => $currentDateTime,
                'created_by' => $creatorName
            ]);
        }

        $approvalUrl = 'https://caisshelter.pages.dev/quotation/view/' . $quotation->id;
        // notifyDirKeu
        $this->quotationNotificationService->sendApprovalNotification(
            quotation: $quotation,
            creatorName: $creatorName,
            approvalUrl: $approvalUrl,
            overrideRecipients: QuotationNotificationService::DIR_KEU  // eksplisit
        );
    }
    private function notifyDirSales(Quotation $quotation, Carbon $currentDateTime): void
    {
        $dirSales = [27927, 127822];

        $leadsKebutuhan = LeadsKebutuhan::with('timSalesD')
            ->where('leads_id', $quotation->leads_id)
            ->where('kebutuhan_id', $quotation->kebutuhan_id)
            ->first();

        $creatorName = $leadsKebutuhan->timSalesD->nama ?? Auth::user()->full_name;
        $msg = "Quotation dengan nomor: {$quotation->nomor} telah selesai dibuat oleh {$creatorName} dan membutuhkan persetujuan Direktur sales.";

        foreach ($dirSales as $userId) {
            LogNotification::create([
                'user_id' => $userId,
                'doc_id' => $quotation->id,
                'transaksi' => 'Quotation',
                'tabel' => 'sl_quotation',
                'pesan' => $msg,
                'is_read' => 0,
                'created_at' => $currentDateTime,
                'created_by' => $creatorName
            ]);
        }

        $approvalUrl = 'https://caisshelter.pages.dev/quotation/view/' . $quotation->id;
        $this->quotationNotificationService->sendApprovalNotification(
            quotation: $quotation,
            creatorName: $creatorName,
            approvalUrl: $approvalUrl,
            overrideRecipients: QuotationNotificationService::DIR_SALES  // eksplisit
        );
    }
    public function reset_Approval(Quotation $quotation, User $user)
    {
        // Cek role - tambahkan role lain yang boleh reset
        $allowedRoles = [2, 96, 97]; // Admin, OT1, OT2
        if (!in_array($user->cais_role_id, $allowedRoles)) {
            return ['success' => false, 'message' => 'Anda tidak memiliki akses untuk reset approval. Role: ' . $user->cais_role_id];
        }

        $quotation->update([
            'status_quotation_id' => 2,
            'is_aktif' => 0,
            'ot1' => null,
            'ot2' => null,
            'updated_at' => Carbon::now()->toDateTimeString(),
            'updated_by' => $user->full_name
        ]);

        \Log::info('Reset approval success', [
            'quotation_id' => $quotation->id,
            'reset_by' => $user->full_name
        ]);

        return ['success' => true, 'data' => $quotation->fresh()];
    }


    public function getFilteredQuotations(string $leadsId, string $tipeQuotation)
    {
        $query = Quotation::select([
            'id',
            'nomor',
            'nama_perusahaan',
            'tgl_quotation',
            'kebutuhan_id',
            'kebutuhan',       
            'company',        
            'mulai_kontrak',  
            'kontrak_selesai',
            'jumlah_site',
            'step',
            'is_aktif',
            'status_quotation_id',
            'tipe_quotation',
        ])
            ->where('leads_id', $leadsId) // ✅ cukup sekali di sini
            ->with([
                // ✅ Batasi kolom — kurangi data yang ditarik
                'statusQuotation:id,nama',
                'pks:id,quotation_id,nomor,tgl_pks,kontrak_awal,kontrak_akhir,is_aktif',
                'quotationSites:id,quotation_id,nama_site,provinsi,provinsi_id,kota,kota_id,penempatan,ump,umk',
            ])
            ->withoutTrashed();

        switch ($tipeQuotation) {
            case 'baru':
                $query
                    // Bukan revisi
                    ->where('leads_id', $leadsId)
                    ->whereIn('status_quotation_id', [1, 2, 4, 5, 8])
                    // Bukan rekontrak (tidak punya PKS aktif yang akan berakhir ≤ 3 bulan)
                    ->whereDoesntHave('pks', function ($q) {
                        $q->where('is_aktif', 1)
                            ->whereBetween('kontrak_akhir', [now(), now()->addMonths(3)]);
                    });
                break;

            case 'revisi':
                $query->where('leads_id', $leadsId)
                    ->whereIn('status_quotation_id', [1, 2, 3, 4, 5, 8]);

                break;
            case 'addendum':
                $query->where('leads_id', $leadsId)
                    ->whereIn('status_quotation_id', [1, 2, 3, 4, 5, 8]);
                // ->where(function ($q) {
                //     $q->whereHas('sites', function ($siteQuery) {
                //         $siteQuery->whereHas('pks', function ($pksQuery) {
                //             $pksQuery->where('is_aktif', 1)
                //                 ->whereBetween('kontrak_akhir', [now(), now()->addMonths(11)]);
                //         });
                //     })
                //     ->orWhereHas('sites', function ($siteQuery) {
                //         $siteQuery->whereNull('pks_id');
                //     });
                // });
                break;

            case 'rekontrak':
                $query->where('leads_id', $leadsId)
                    ->where(function ($q) {
                        $q->where('status_quotation_id', 3)
                            ->orWhereNotNull('ot1');
                    });
                // ->whereHas('sites', function ($siteQuery) {
                //     $siteQuery->whereHas('pks', function ($pksQuery) {
                //         $pksQuery->where('is_aktif', 1)
                //             ->whereBetween('kontrak_akhir', [now(), now()->addMonths(3)]);
                //     });
                // });
                break;
        }

        return $query->latest('created_at')
            ->get()
            ->map(fn(Quotation $quotation) => $this->formatQuotationData($quotation, $tipeQuotation));
    }

    /**
     * Format quotation data for response
     */
    public function formatQuotationData(Quotation $quotation, string $tipeQuotation): array
    {
        $data = [
            'id' => $quotation->id,
            'nomor' => $quotation->nomor,
            'nama_perusahaan' => $quotation->nama_perusahaan,
            'mulai_kontrak' => $quotation->mulai_kontrak,
            'kontrak_selesai' => $quotation->kontrak_selesai,
            'tgl_quotation' => $quotation->tgl_quotation,
            'kebutuhan_id' => $quotation->kebutuhan_id,
            'jumlah_site' => $quotation->jumlah_site,
            'step' => $quotation->step,
            'is_aktif' => $quotation->is_aktif,
            'status_quotation_id' => $quotation->status_quotation_id,
            'status_quotation' => $quotation->statusQuotation->nama ?? 'Unknown',
            'tipe_quotation' => $quotation->tipe_quotation,
            'kebutuhan' => $quotation->kebutuhan,
            'company' => $quotation->company,
            'source' => 'quotation',
            // Menampilkan semua site dalam bentuk array
            'sites' => $quotation->quotationSites->map(function ($site) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,
                    'provinsi_id' => $site->provinsi_id,
                    'kota_id' => $site->kota_id,
                    'ump' => $site->ump,
                    'umk' => $site->umk
                ];
            })->toArray()
        ];

        // Untuk kompatibilitas dengan kode yang sudah ada, tetap sertakan site pertama
        // (opsional, bisa dihapus jika tidak diperlukan)
        $data['site'] = $quotation->quotationSites->first()->nama_site ?? null;

        // Add PKS data for recontract
        if ($tipeQuotation === 'rekontrak' && $quotation->pks) {
            $data['pks_data'] = [
                'id' => $quotation->pks->id,
                'nomor' => $quotation->pks->nomor,
                'tgl_pks' => $quotation->pks->tgl_pks,
                'kontrak_awal' => $quotation->pks->kontrak_awal,
                'kontrak_akhir' => $quotation->pks->kontrak_akhir,
                'is_aktif' => $quotation->pks->is_aktif
            ];
        }

        return $data;
    }
}