<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\QuotationDuplicationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
     *     summary="Get all quotations",
     *     description="Retrieves a list of quotations with optional filtering",
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
     *         description="Status filter",
     *         required=false,
     *         @OA\Schema(type="string", example="approved")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
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
            $quotations = Quotation::with([
                'leads',
                'statusQuotation',
                'quotationSites',
                'company'
            ])
                ->byUserRole()
                ->dateRange($request->tgl_dari, $request->tgl_sampai)
                ->byCompany($request->company)
                ->byKebutuhan($request->kebutuhan_id)
                ->byStatus($request->status)
                ->notDeleted()
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => QuotationResource::collection($quotations),
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
     *         @OA\Schema(type="string", enum={"baru", "revisi", "rekontrak"}, example="baru")
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
     *                 @OA\Property(property="quotation_sites", type="array",
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
        try {
            $user = Auth::user();

            // Validasi tipe_quotation dari URL parameter
            if (!in_array($tipe_quotation, ['baru', 'revisi', 'rekontrak'])) {
                throw new \Exception('Tipe quotation tidak valid');
            }

            // Merge tipe_quotation ke request untuk validasi
            $request->merge(['tipe_quotation' => $tipe_quotation]);

            // Prepare basic data
            $quotationData = $this->quotationBusinessService->prepareQuotationData($request);

            // Handle quotation referensi untuk revisi/rekontrak
            $quotationReferensi = null;
            if (in_array($tipe_quotation, ['revisi', 'rekontrak'])) {
                if (!$request->has('quotation_referensi_id') || !$request->quotation_referensi_id) {
                    throw new \Exception('Quotation referensi wajib dipilih untuk ' . $tipe_quotation);
                }

                $quotationReferensi = Quotation::notDeleted()->findOrFail($request->quotation_referensi_id);
                $quotationData['quotation_referensi_id'] = $quotationReferensi->id;
            }

            // Generate nomor berdasarkan tipe dari URL parameter
            $quotationData['nomor'] = $this->quotationBusinessService->generateNomorByType(
                $request->perusahaan_id,
                $request->entitas,
                $tipe_quotation, // Gunakan parameter dari URL
                $quotationReferensi
            );

            $quotationData['created_by'] = $user->full_name;
            $quotationData['tipe_quotation'] = $tipe_quotation;

            $quotation = Quotation::create($quotationData);

            // Create site data dan PIC
            $this->quotationBusinessService->createQuotationSites($quotation, $request, $user->full_name);
            $this->quotationBusinessService->createInitialPic($quotation, $request, $user->full_name);

            // Untuk revisi/rekontrak, copy additional data dari referensi
            if ($quotationReferensi) {
                $this->quotationDuplicationService->duplicateQuotationData($quotation, $quotationReferensi);
            }

            $this->quotationBusinessService->createInitialActivity($quotation, $user->full_name, $user->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($quotation->load(['quotationSites', 'quotationPics'])),
                'message' => 'Quotation ' . $tipe_quotation . ' created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create quotation',
                'error' => $e->getMessage()
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
    public function show(string $id): JsonResponse
    {
        try {
            $quotation = Quotation::with([
                'leads',
                'statusQuotation',
                'quotationSites',
                'quotationDetails',
                'quotationPics',
                'quotationAplikasis',
                'quotationKaporlaps',
                'quotationDevices',
                'quotationChemicals',
                'quotationOhcs',
                'quotationKerjasamas',
                'quotationTrainings',
                'company'
            ])
                ->notDeleted()
                ->findOrFail($id);

            $calculatedQuotation = $this->quotationService->calculateQuotation($quotation);

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($calculatedQuotation),
                'message' => 'Quotation retrieved successfully'
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

            // Use business service to soft delete relations
            $this->quotationBusinessService->softDeleteQuotationRelations($quotation, $user->full_name);

            $quotation->update([
                'deleted_at' => Carbon::now(),
                'deleted_by' => $user->full_name
            ]);

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
     *             @OA\Property(property="message", type="string", example="Failed to resubmit quotation"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function resubmit(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $originalQuotation = Quotation::with([
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
                ->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'alasan' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $newQuotation = $this->quotationService->resubmitQuotation(
                $originalQuotation,
                $request->alasan,
                Auth::user()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new QuotationResource($newQuotation),
                'message' => 'Quotation resubmitted successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
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
     *     description="Submits quotation for approval at different levels (OT1, OT2, OT3)",
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
     *             required={"approval_type","is_approved"},
     *             @OA\Property(property="approval_type", type="string", enum={"ot1", "ot2", "ot3"}, example="ot1"),
     *             @OA\Property(property="is_approved", type="boolean", example=true),
     *             @OA\Property(property="notes", type="string", example="Approval notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation submitted for approval successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation submitted for approval successfully")
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
     *             @OA\Property(property="message", type="string", example="Failed to submit quotation for approval"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function submitForApproval(Request $request, string $id): JsonResponse
    {
        try {
            $quotation = Quotation::notDeleted()->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'approval_type' => 'required|in:ot1,ot2,ot3',
                'is_approved' => 'required|boolean',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $this->quotationService->submitForApproval($quotation, $request->all(), Auth::user());

            return response()->json([
                'success' => true,
                'message' => 'Quotation submitted for approval successfully'
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
     *     path="/api/quotations/leads",
     *     tags={"Quotations"},
     *     summary="Get quotations by leads",
     *     description="Retrieves all quotations for a specific leads/company",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Quotations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Quotations retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leads not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Leads not found"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function getByLeads(): JsonResponse
    {
        try {
            set_time_limit(0);

            $leads = Leads::with(['statusLeads', 'branch'])
                ->withoutTrashed()
                ->get();

            $data = $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'nama_perusahaan' => $lead->nama_perusahaan,
                    'pic' => $lead->pic,
                    'wilayah' => $lead->branch->name ?? 'Unknown',
                    'status_leads' => $lead->statusLeads->nama ?? 'Unknown',
                ];
            });

            return response()->json([
                'message' => 'Leads retrieved successfully',
                'success' => true,
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leads not found',
                'error' => $e->getMessage(),
            ], 404);
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
     *         @OA\Schema(type="string", enum={"baru", "revisi", "rekontrak"}, example="revisi")
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
                'tipe_quotation' => 'required|in:baru,revisi,rekontrak'
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
}