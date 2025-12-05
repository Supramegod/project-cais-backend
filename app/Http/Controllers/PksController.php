<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationResource;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Loyalty;
use App\Models\Pks;
use App\Models\Leads;
use App\Models\KategoriSesuaiHc;
use App\Models\Quotation;
use App\Models\RuleThr;
use App\Models\SalaryRule;
use App\Models\Site;
use App\Models\PksPerjanjian;
use App\Models\CustomerActivity;
use App\Models\Kebutuhan;
use App\Models\SpkSite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
/**
 * @OA\Tag(
 *     name="PKS",
 *     description="API untuk manajemen PKS (Perjanjian Kerja Sama)"
 * )
 */
class PksController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pks/list",
     *     summary="Get list of PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tgl_dari",
     *         in="query",
     *         description="Start date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-10-01")
     *     ),
     *     @OA\Parameter(
     *         name="tgl_sampai",
     *         in="query",
     *         description="End date",
     *         required=false,
     *         @OA\Schema(type="string", format="date",example="2025-11-01")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Status filter",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nomor", type="string"),
     *                 @OA\Property(property="nama_perusahaan", type="string"),
     *                 @OA\Property(property="tgl_pks", type="string"),
     *                 @OA\Property(property="formatted_tgl_pks", type="string"),
     *                 @OA\Property(property="kontrak_awal", type="string"),
     *                 @OA\Property(property="kontrak_akhir", type="string"),
     *                 @OA\Property(property="formatted_kontrak_awal", type="string"),
     *                 @OA\Property(property="formatted_kontrak_akhir", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="berakhir_dalam", type="string"),
     *                 @OA\Property(property="status_berlaku", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="created_by", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'tgl_dari' => 'nullable|date',
                'tgl_sampai' => 'nullable|date|after_or_equal:tgl_dari',
                'status' => 'nullable|integer|exists:m_status_pks,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Pks::with(['statusPks'])
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc');

            if (!empty($request->status)) {
                $query->where('status_pks_id', $request->status);
            }

            if (!empty($request->tgl_dari) && !empty($request->tgl_sampai)) {
                $query->whereBetween('tgl_pks', [
                    $request->tgl_dari,
                    $request->tgl_sampai
                ]);
            }

            $pksList = $query->get()->map(function ($pks) {
                return [
                    'id' => $pks->id,
                    'nomor' => $pks->nomor,
                    'nama_perusahaan' => $pks->nama_perusahaan,
                    'tgl_pks' => $pks->tgl_pks,
                    'kontrak_awal' => $pks->kontrak_awal,
                    'kontrak_akhir' => $pks->kontrak_akhir,
                    'formatted_kontrak_awal' => Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y'),
                    'formatted_kontrak_akhir' => Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y'),
                    'status' => $pks->statusPks->nama ?? null,
                    'berakhir_dalam' => $this->hitungBerakhirKontrak($pks->kontrak_akhir),
                    'status_berlaku' => $this->getStatusBerlaku($pks->kontrak_akhir),
                    'created_at' => $pks->created_at,
                    'created_by' => $pks->created_by
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $pksList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve PKS list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks/view/{id}",
     *     summary="Get PKS details",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="quotation_data", type="object", description="Quotation details and calculation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        try {
            $pks = Pks::with([
                'leads',
                'statusPks',
                'sites',
                'perjanjian',
                'activities',
                'sites.quotation' // Tambahkan relasi quotation dari site
            ])->find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            // Format dates for frontend
            $pks->formatted_kontrak_awal = Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y');
            $pks->formatted_kontrak_akhir = Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y');
            $pks->berakhir_dalam = $this->hitungBerakhirKontrak($pks->kontrak_akhir);

            // GET QUOTATION DATA FROM FIRST SITE
            $quotationData = null;
            if ($pks->sites->isNotEmpty()) {
                $firstSite = $pks->sites->first();

                if ($firstSite && $firstSite->quotation) {
                    // Load full quotation with all necessary relations
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
                        'quotationKerjasamas'
                    ])->find($firstSite->quotation_id);

                    if ($quotation) {
                        // Use QuotationResource to format data
                        $quotationData = new QuotationResource($quotation);
                        $quotationData = $quotationData->toArray(request());
                    }
                }
            }

            $response = [
                'success' => true,
                'data' => $pks
            ];

            // Add quotation data if available
            if ($quotationData) {
                $response['quotation_data'] = $quotationData;
            }

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve PKS details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve PKS details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/pks/add",
     *     summary="Create new PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leads_id", "site_ids", "tanggal_pks", "tanggal_awal_kontrak", "tanggal_akhir_kontrak", "kategoriHC", "loyalty", "salary_rule", "rule_thr", "entitas"},
     *             @OA\Property(property="leads_id", type="integer", example=38),
     *             @OA\Property(
     *                 property="site_ids", 
     *                 type="array", 
     *                 @OA\Items(type="integer"),
     *                 example={130}
     *             ),
     *             @OA\Property(property="tanggal_pks", type="string", format="date", example="2025-10-14"),
     *             @OA\Property(property="tanggal_awal_kontrak", type="string", format="date", example="2025-11-01"),
     *             @OA\Property(property="tanggal_akhir_kontrak", type="string", format="date", example="2026-10-31"),
     *             @OA\Property(property="kategoriHC", type="integer", example=1, description="Kategori HC ID"),
     *             @OA\Property(property="loyalty", type="integer", example=1, description="Loyalty ID"),
     *             @OA\Property(property="salary_rule", type="integer", example=1, description="Salary Rule ID"),
     *             @OA\Property(property="rule_thr", type="integer", example=1, description="Rule THR ID"),
     *             @OA\Property(property="entitas", type="integer", example=1, description="Entitas ID"),
     *             @OA\Property(
     *                 property="perjanjian_data", 
     *                 type="object", 
     *                 description="Template data for frontend to generate agreement",
     *                 example={
     *                     "nomor_perjanjian": "PKS/2025/001",
     *                     "pihak_pertama": "PT ABC",
     *                     "pihak_kedua": "PT Client XYZ",
     *                     "lokasi_penandatanganan": "Jakarta"
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="PKS created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PKS berhasil dibuat"),
     *             @OA\Property(
     *                 property="data", 
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="PKS/2025/001"),
     *                 @OA\Property(property="leads_id", type="integer", example=1),
     *                 @OA\Property(property="tanggal_pks", type="string", example="14-10-2025"),
     *                 @OA\Property(property="tanggal_awal_kontrak", type="string", example="01-11-2025"),
     *                 @OA\Property(property="tanggal_akhir_kontrak", type="string", example="31-10-2026")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 example={
     *                     "leads_id": {"The leads id field is required."},
     *                     "site_ids": {"The site ids field is required."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'leads_id' => 'required|integer|exists:sl_leads,id',
                'site_ids' => 'required|array',
                'site_ids.*' => 'integer|exists:sl_spk_site,id',
                'tanggal_pks' => 'required|date',
                'tanggal_awal_kontrak' => 'required|date',
                'tanggal_akhir_kontrak' => 'required|date|after:tanggal_awal_kontrak',
                'kategoriHC' => 'required|integer|exists:m_kategori_sesuai_hc,id',
                'loyalty' => 'required|integer|exists:m_loyalty,id',
                'salary_rule' => 'required|integer|exists:m_salary_rule,id',
                'rule_thr' => 'required|integer|exists:m_rule_thr,id',
                'entitas' => 'required|integer|exists:m_company,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $pksData = $this->createPks($request);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PKS created successfully',
                'data' => $pksData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create PKS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/pks/update/{id}",
     *     summary="Update PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="PKS ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tanggal_pks", type="string", format="date", example="2025-10-14"),
     *             @OA\Property(property="tanggal_awal_kontrak", type="string", format="date", example="2025-11-01"),
     *             @OA\Property(property="tanggal_akhir_kontrak", type="string", format="date", example="2026-10-31"),
     *             @OA\Property(property="status_pks_id", type="integer", example=2, description="Status PKS ID (1=Draft, 2=Active, 3=Expired, etc.)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PKS berhasil diupdate"),
     *             @OA\Property(
     *                 property="data", 
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nomor", type="string", example="PKS/2025/001"),
     *                 @OA\Property(property="tanggal_pks", type="string", example="14-10-2025"),
     *                 @OA\Property(property="tanggal_awal_kontrak", type="string", example="01-11-2025"),
     *                 @OA\Property(property="tanggal_akhir_kontrak", type="string", example="31-10-2026"),
     *                 @OA\Property(property="status_pks_id", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="PKS tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 example={
     *                     "tanggal_pks": {"The tanggal pks must be a valid date."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $pks = Pks::find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'tanggal_pks' => 'sometimes|date',
                'tanggal_awal_kontrak' => 'sometimes|date',
                'tanggal_akhir_kontrak' => 'sometimes|date|after:tanggal_awal_kontrak',
                'status_pks_id' => 'sometimes|integer|exists:m_status_pks,id',
                'is_aktif' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Simpan data lama untuk pengecekan
            $oldIsAktif = $pks->is_aktif;
            $oldKontrakAkhir = $pks->kontrak_akhir;

            $pks->update($request->all());

            // Jika ada perubahan pada is_aktif atau kontrak_akhir, sync customer_active
            if ($oldIsAktif != $pks->is_aktif || $oldKontrakAkhir != $pks->kontrak_akhir) {
                $this->autoSyncCustomerActiveStatus();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PKS updated successfully',
                'data' => $pks
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update PKS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/pks/delete/{id}",
     *     summary="Delete PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            $pks = Pks::find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $pks->delete();

            return response()->json([
                'success' => true,
                'message' => 'PKS deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete PKS',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pks/{id}/approve",
     *     summary="Approve PKS",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ot"},
     *             @OA\Property(property="ot", type="integer", description="Approval level (1-4)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $pks = Pks::find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $this->approvePks($pks, $request->ot);

            return response()->json([
                'success' => true,
                'message' => 'PKS approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve PKS',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/pks/{id}/activate",
     *     summary="Activate PKS sites",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS sites activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function activate($id): JsonResponse
    {
        try {
            $pks = Pks::with(['leads', 'sites'])->find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $this->activatePksSites($pks);

            return response()->json([
                'success' => true,
                'message' => 'PKS sites activated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate PKS sites',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/pks/{id}/perjanjian",
     *     summary="Get PKS perjanjian data for template",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Template data for frontend")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function getPerjanjianTemplateData($id): JsonResponse
    {
        try {
            $pks = Pks::with(['leads', 'sites'])->find($id);

            if (!$pks) {
                return response()->json([
                    'success' => false,
                    'message' => 'PKS not found'
                ], 404);
            }

            $templateData = $this->getTemplateData($pks);

            return response()->json([
                'success' => true,
                'data' => $templateData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/pks/available-leads",
     *     summary="Get available leads for PKS creation",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nomor", type="string"),
     *                 @OA\Property(property="nama_perusahaan", type="string"),
     *                 @OA\Property(property="provinsi", type="string"),
     *                 @OA\Property(property="kota", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getAvailableLeads(): JsonResponse
    {
        try {
            $leads = $this->getAvailableLeadsData();

            return response()->json([
                'success' => true,
                'data' => $leads
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available leads',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks/available-sites/{leadsId}",
     *     summary="Get available sites for leads",
     *     tags={"PKS"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="leadsId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nomor", type="string"),
     *                 @OA\Property(property="nama_site", type="string"),
     *                 @OA\Property(property="provinsi", type="string"),
     *                 @OA\Property(property="kota", type="string"),
     *                 @OA\Property(property="penempatan", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leads not found"
     *     )
     * )
     */

    public function getAvailableSites($leadsId): JsonResponse
    {
        try {
            $sites = $this->getAvailableSitesData($leadsId);

            return response()->json([
                'success' => true,
                'data' => $sites
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available sites',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ======================================================================
    // PRIVATE METHODS - Business Logic
    // ======================================================================

    private function createPks($request)
    {
        $leads = Leads::findOrFail($request->leads_id);
        $kebutuhan = Kebutuhan::find($leads->kebutuhan_id);
        $kategoriHC = KategoriSesuaiHc::find($request->kategoriHC);
        $loyalty = Loyalty::find($request->loyalty);
        $ruleThr = RuleThr::find($request->rule_thr);
        $salaryRule = SalaryRule::find($request->salary_rule);
        $company = Company::find($request->entitas);

        $pksNomor = $this->generateNomor($leads->id, $request->entitas);

        // Create PKS
        $pks = Pks::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'nomor' => $pksNomor,
            'tgl_pks' => $request->tanggal_pks,
            'kode_perusahaan' => $leads->nomor,
            'nama_perusahaan' => $leads->nama_perusahaan,
            'alamat_perusahaan' => $leads->alamat,
            'layanan_id' => $leads->kebutuhan_id,
            'layanan' => $kebutuhan->nama ?? null,
            'bidang_usaha_id' => $leads->bidang_perusahaan_id,
            'bidang_usaha' => $leads->bidang_perusahaan,
            'jenis_perusahaan_id' => $leads->jenis_perusahaan_id,
            'jenis_perusahaan' => $leads->jenis_perusahaan,
            'kontrak_awal' => $request->tanggal_awal_kontrak,
            'kontrak_akhir' => $request->tanggal_akhir_kontrak,
            'status_pks_id' => 5,
            'sales_id' => Auth::id(),
            'company_id' => $request->entitas,
            'salary_rule_id' => $request->salary_rule,
            'rule_thr_id' => $request->rule_thr,
            'kategori_sesuai_hc_id' => $request->kategoriHC,
            'kategori_sesuai_hc' => $kategoriHC->nama ?? null,
            'loyalty_id' => $request->loyalty,
            'loyalty' => $loyalty->nama ?? null,
            'provinsi_id' => $leads->provinsi_id,
            'provinsi' => $leads->provinsi,
            'kota_id' => $leads->kota_id,
            'kota' => $leads->kota,
            'pma' => $leads->pma,
            'created_by' => Auth::user()->full_name
        ]);

        // Create Sites
        $this->createSites($pks, $request->site_ids, $pksNomor, $kebutuhan, $leads);

        // Create Initial Activity
        $this->createInitialActivity($pks, $leads, $pksNomor);

        return $pks;
    }

    private function createSites($pks, $siteIds, $pksNomor, $kebutuhan, $leads)
    {
        foreach ($siteIds as $key => $siteId) {
            $spkSite = SpkSite::where('id', $siteId)->first();

            if ($spkSite) {
                $nomorSite = $pksNomor . '-' . sprintf("%04d", ($key + 1));
                $namaProyek = sprintf(
                    '%s-%s.%s.%s',
                    Carbon::parse($pks->kontrak_awal)->format('my'),
                    Carbon::parse($pks->kontrak_akhir)->format('my'),
                    strtoupper(substr($kebutuhan->nama, 0, 2)),
                    strtoupper($leads->nama_perusahaan)
                );

                Site::create([
                    'quotation_id' => $spkSite->quotation_id,
                    'spk_id' => $spkSite->spk_id,
                    'pks_id' => $pks->id,
                    'quotation_site_id' => $spkSite->quotation_site_id,
                    'spk_site_id' => $spkSite->id,
                    'leads_id' => $spkSite->leads_id,
                    'nomor' => $nomorSite,
                    'nomor_proyek' => $nomorSite,
                    'nama_proyek' => $namaProyek,
                    'nama_site' => $spkSite->nama_site,
                    'provinsi_id' => $spkSite->provinsi_id,
                    'provinsi' => $spkSite->provinsi,
                    'kota_id' => $spkSite->kota_id,
                    'kota' => $spkSite->kota,
                    'ump' => $spkSite->ump,
                    'umk' => $spkSite->umk,
                    'nominal_upah' => $spkSite->nominal_upah,
                    'penempatan' => $spkSite->penempatan,
                    'kebutuhan_id' => $spkSite->kebutuhan_id,
                    'kebutuhan' => $spkSite->kebutuhan,
                    'nomor_quotation' => $spkSite->nomor_quotation,
                    'created_by' => Auth::user()->full_name
                ]);
            }
        }
    }

    private function createInitialActivity($pks, $leads, $pksNomor)
    {
        $nomorActivity = $this->generateNomorActivity($leads->id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'pks_id' => $pks->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'PKS',
            'notes' => 'PKS dengan nomor :' . $pksNomor . ' terbentuk',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name
        ]);
    }

    private function approvePks($pks, $otLevel)
    {
        $statusMap = [
            1 => 2,
            2 => 3,
            3 => 4,
            4 => 5
        ];

        $approveField = "ot{$otLevel}";

        $pks->update([
            $approveField => Auth::user()->full_name,
            'status_pks_id' => $statusMap[$otLevel] ?? $pks->status_pks_id,
            'updated_by' => Auth::user()->full_name
        ]);
    }

    private function activatePksSites($pks)
    {
        DB::beginTransaction();

        try {
            // Update PKS menjadi aktif
            $pks->update([
                'ot5' => Auth::user()->full_name,
                'status_pks_id' => 7,
                'is_aktif' => 1,
                'updated_by' => Auth::user()->full_name
            ]);

            $leads = $pks->leads;

            // Cek apakah customer sudah ada
            if (!$leads->customer_id) {
                // Generate nomor customer
                $customerNomor = $this->generateCustomerNumber($leads->id, $pks->company_id);

                // Buat record customer
                $customer = Customer::create([
                    'leads_id' => $leads->id,
                    'nomor' => $customerNomor,
                    'tgl_customer' => now(),
                    'tim_sales_id' => $leads->tim_sales_id,
                    'tim_sales_d_id' => $leads->tim_sales_d_id,
                    'created_by' => Auth::user()->full_name
                ]);

                // Update leads dengan customer_id, status_leads_id, dan customer_active
                $leads->update([
                    'customer_id' => $customer->id,
                    'status_leads_id' => 102,
                    'customer_active' => 1, // Set ke 1 karena PKS aktif
                    'updated_by' => Auth::user()->full_name
                ]);

                // Buat activity log untuk customer
                $this->createCustomerActivity($leads, $customerNomor);

            } else {
                // Jika customer sudah ada, update status dan customer_active
                $leads->update([
                    'status_leads_id' => 102,
                    'customer_active' => 1, // Set ke 1 karena PKS aktif
                    'updated_by' => Auth::user()->full_name
                ]);
            }

            // Update customer_active untuk semua leads yang terkait (otomatis)
            $this->autoSyncCustomerActiveStatus();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    /**
     * SYNC OTOMATIS - Update customer_active berdasarkan status PKS
     * Dipanggil otomatis ketika ada perubahan PKS
     */
    private function autoSyncCustomerActiveStatus()
    {
        try {
            // Ambil semua leads yang sudah menjadi customer
            $leadsWithCustomers = Leads::whereNotNull('customer_id')
                ->whereNull('deleted_at')
                ->get();

            foreach ($leadsWithCustomers as $lead) {
                $this->updateCustomerActiveFromPks($lead);
            }

            \Log::info('Auto sync customer_active status completed', [
                'count' => $leadsWithCustomers->count(),
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            \Log::error('Auto sync customer_active status failed: ' . $e->getMessage());
        }
    }
    /**
     * Update customer_active untuk satu leads berdasarkan status PKS
     */
    private function updateCustomerActiveFromPks($lead)
    {
        // Cari semua PKS yang terkait dengan leads ini
        $pksList = Pks::where('leads_id', $lead->id)
            ->whereNull('deleted_at')
            ->get();

        $hasActivePks = false;

        foreach ($pksList as $pks) {
            // Cek apakah PKS aktif dan kontrak masih berlaku
            if ($pks->is_aktif == 1 && $this->isKontrakBerlaku($pks->kontrak_akhir)) {
                $hasActivePks = true;
                break;
            }
        }

        // Update customer_active berdasarkan status PKS
        $newStatus = $hasActivePks ? 1 : 0;

        // Only update if changed to avoid unnecessary database operations
        if ($lead->customer_active != $newStatus) {
            $lead->update([
                'customer_active' => $newStatus
            ]);

            \Log::info('Customer active status updated', [
                'leads_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'customer_active' => $newStatus,
                'timestamp' => now()
            ]);
        }
    }

    /**
     * Cek apakah kontrak masih berlaku
     */
    private function isKontrakBerlaku($kontrakAkhir)
    {
        if (!$kontrakAkhir) {
            return false;
        }

        $tanggalSekarang = Carbon::now();
        $tanggalKontrakAkhir = Carbon::parse($kontrakAkhir);

        return $tanggalSekarang->lessThanOrEqualTo($tanggalKontrakAkhir);
    }
    /**
     * Generate nomor customer dengan format seperti PKS
     */
    private function generateCustomerNumber($leadsId, $companyId = null)
    {
        $now = Carbon::now();
        $leads = Leads::find($leadsId);

        if (!$companyId && $leads && $leads->company_id) {
            $companyId = $leads->company_id;
        }

        $company = Company::where('id', $companyId)->first();
        $dataLeads = Leads::find($leadsId);

        $nomor = "CUST/"; // Prefix CUST untuk Customer

        if ($company && $dataLeads) {
            $nomor .= $company->code . "/";
            $nomor .= $dataLeads->nomor . "-";
        } else {
            $nomor .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);

        // Hitung jumlah data customer dengan pattern yang sama
        $pattern = $nomor . $month . $now->year . "-%";
        $jumlahData = Customer::where('nomor', 'like', $pattern)->count();
        $urutan = sprintf("%05d", $jumlahData + 1);

        return $nomor . $month . $now->year . "-" . $urutan;
    }

    /**
     * Create customer activity log
     */
    private function createCustomerActivity($leads, $customerNomor)
    {
        $nomorActivity = $this->generateNomorActivity($leads->id);

        CustomerActivity::create([
            'leads_id' => $leads->id,
            'branch_id' => $leads->branch_id,
            'tgl_activity' => now(),
            'nomor' => $nomorActivity,
            'tipe' => 'CUSTOMER',
            'notes' => 'Customer dengan nomor :' . $customerNomor . ' terbentuk dari PKS',
            'is_activity' => 0,
            'user_id' => Auth::id(),
            'created_by' => Auth::user()->full_name
        ]);
    }

    private function getTemplateData($pks)
    {
        $leads = $pks->leads;
        $company = Company::find($pks->company_id);
        $kebutuhan = Kebutuhan::find($pks->layanan_id);
        $ruleThr = RuleThr::find($pks->rule_thr_id);
        $salaryRule = SalaryRule::find($pks->salary_rule_id);

        return [
            'pks' => [
                'nomor' => $pks->nomor,
                'tanggal_pks' => Carbon::parse($pks->tgl_pks)->isoFormat('D MMMM Y'),
                'kontrak_awal' => Carbon::parse($pks->kontrak_awal)->isoFormat('D MMMM Y'),
                'kontrak_akhir' => Carbon::parse($pks->kontrak_akhir)->isoFormat('D MMMM Y'),
            ],
            'perusahaan' => [
                'nama' => $leads->nama_perusahaan,
                'alamat' => $leads->alamat,
                'pic' => $leads->pic,
                'nomor' => $leads->nomor,
            ],
            'penyedia' => [
                'nama' => $company->name ?? '',
                'direktur' => $company->nama_direktur ?? '',
                'alamat' => $company->address ?? '',
                'bank' => [
                    'nama' => 'MANDIRI',
                    'cabang' => 'KCP SURABAYA RUNGKUT MEGAH RAYA',
                    'rekening' => '1420001290823',
                    'nama_rekening' => $company->name ?? ''
                ]
            ],
            'layanan' => [
                'nama' => $kebutuhan->nama ?? '',
                'kebutuhan_id' => $pks->layanan_id
            ],
            'rule_thr' => [
                'hari_penagihan_invoice' => $ruleThr->hari_penagihan_invoice ?? 0,
                'hari_pembayaran_invoice' => $ruleThr->hari_pembayaran_invoice ?? 0,
                'hari_rilis_thr' => $ruleThr->hari_rilis_thr ?? 0
            ],
            'salary_rule' => [
                'cutoff' => $salaryRule->cutoff ?? '',
                'crosscheck_absen' => $salaryRule->crosscheck_absen ?? '',
                'pengiriman_invoice' => $salaryRule->pengiriman_invoice ?? '',
                'perkiraan_invoice_diterima' => $salaryRule->perkiraan_invoice_diterima ?? '',
                'pembayaran_invoice' => $salaryRule->pembayaran_invoice ?? '',
                'rilis_payroll' => $salaryRule->rilis_payroll ?? ''
            ],
            'sites' => $pks->sites->map(function ($site) {
                return [
                    'nama_site' => $site->nama_site,
                    'alamat' => $site->penempatan,
                    'kota' => $site->kota
                ];
            })
        ];
    }

    // ======================================================================
    // UTILITY METHODS
    // ======================================================================

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

    private function getStatusBerlaku($tanggalBerakhir)
    {
        $selisih = $this->selisihKontrakBerakhir($tanggalBerakhir);

        if ($selisih <= 0)
            return 'Kontrak Habis';
        if ($selisih <= 60)
            return 'Berakhir dalam 2 bulan';
        if ($selisih <= 90)
            return 'Berakhir dalam 3 bulan';
        return 'Lebih dari 3 Bulan';
    }

    private function selisihKontrakBerakhir($tanggalBerakhir)
    {
        if (is_null($tanggalBerakhir))
            return 0;

        $tanggalSekarang = Carbon::now();
        $tanggalBerakhir = Carbon::createFromFormat('Y-m-d', $tanggalBerakhir);

        if ($tanggalSekarang->greaterThanOrEqualTo($tanggalBerakhir)) {
            return 0;
        }

        return $tanggalSekarang->diffInDays($tanggalBerakhir);
    }

    private function generateNomor($leadsId, $companyId)
    {
        $now = Carbon::now();
        $dataLeads = Leads::find($leadsId);
        $company = Company::where('id', $companyId)->first();

        $nomor = "PKS/";
        if ($company) {
            $nomor .= $company->code . "/";
            $nomor .= $dataLeads->nomor . "-";
        } else {
            $nomor .= "NN/NNNNN-";
        }

        $month = str_pad($now->month, 2, '0', STR_PAD_LEFT);
        $jumlahData = Pks::where('nomor', 'like', $nomor . $month . $now->year . "-%")->count();
        $urutan = sprintf("%05d", $jumlahData + 1);

        return $nomor . $month . $now->year . "-" . $urutan;
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

    private function getAvailableLeadsData()
    {
        return Leads::with(['timSalesD.user'])
            ->whereHas('timSalesD', function ($query) {
                $query->where('user_id', Auth::id());
            })
            ->whereHas('spkSites', function ($query) {
                $query->whereNull('sl_spk_site.deleted_at')
                    ->whereHas('spk', function ($subQuery) {
                        $subQuery->whereNull('sl_spk.deleted_at');
                    })
                    ->whereDoesntHave('site');
            })
            ->select('id', 'nomor', 'nama_perusahaan', 'provinsi', 'kota')
            ->distinct()
            ->get();
    }
    private function getAvailableSitesData($leadsId)
    {
        // Ambil data PKS dengan relasi yang diperlukan
        $pks = Pks::with(['leads', 'sites', 'company', 'ruleThr', 'salaryRule'])
            ->where('leads_id', $leadsId)
            ->first();

        // Ambil data sites yang tersedia
        $sites = SpkSite::with(['spk'])
            ->where('leads_id', $leadsId)
            ->whereNull('deleted_at')
            ->whereDoesntHave('site')
            ->whereHas('spk', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->select('id', 'nama_site', 'provinsi', 'kota', 'penempatan', 'spk_id')
            ->orderBy(function ($query) {
                $query->select('nomor')
                    ->from('sl_spk')
                    ->whereColumn('sl_spk.id', 'sl_spk_site.spk_id')
                    ->limit(1);
            }, 'asc')
            ->get()
            ->map(function ($site) use ($pks) {
                return [
                    'id' => $site->id,
                    'nomor' => $site->spk->nomor ?? null,
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,
                    // Data tambahan dari PKS
                    'company' => $pks && $pks->company ? [
                        'id' => $pks->company->id,
                        'name' => $pks->company->name,
                        'code' => $pks->company->code
                    ] : null,
                    'rule_thr' => $pks && $pks->ruleThr ? [
                        'id' => $pks->ruleThr->id,
                        'nama' => $pks->ruleThr->nama,
                        'hari_penagihan_invoice' => $pks->ruleThr->hari_penagihan_invoice,
                        'hari_pembayaran_invoice' => $pks->ruleThr->hari_pembayaran_invoice,
                        'hari_rilis_thr' => $pks->ruleThr->hari_rilis_thr
                    ] : null,
                    'salary_rule' => $pks && $pks->salaryRule ? [
                        'id' => $pks->salaryRule->id,
                        'nama' => $pks->salaryRule->nama_salary_rule,
                        'cutoff' => $pks->salaryRule->cutoff,
                        'pembayaran_invoice' => $pks->salaryRule->pembayaran_invoice,
                        'rilis_payroll' => $pks->salaryRule->rilis_payroll
                    ] : null
                ];
            });

        return $sites;
    }
}