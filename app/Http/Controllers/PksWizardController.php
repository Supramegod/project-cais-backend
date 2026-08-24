<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pks\PksWizardInitializeRequest;
use App\Http\Requests\Pks\PksWizardFinalizeRequest;
use App\Http\Requests\Pks\PksWizardPasalPreviewRequest;
use App\Http\Requests\Pks\PksWizardPasalPreviewUpdateRequest;
use App\Http\Requests\Pks\PksWizardStepRequest;
use App\Models\Pks;
use App\Services\Pks\PksPasalPreviewService;
use App\Services\Pks\Wizard\PksWizardFinalizeService;
use App\Services\Pks\Wizard\PksWizardService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="PKS Wizard",
 *     description="API untuk inisialisasi, step, dan preview pasal PKS Wizard"
 * )
 */
class PksWizardController extends Controller
{
    public function __construct(
        private readonly PksWizardService $pksWizardService,
        private readonly PksPasalPreviewService $pksPasalPreviewService,
        private readonly PksWizardFinalizeService $pksWizardFinalizeService
    )
    {
    }

    /**
     * @OA\Post(
     *     path="/api/pks-wizard/initialize/{tipe}",
     *     summary="Initialize PKS wizard",
     *     description="Membuat draft PKS wizard awal dengan nomor draft dan menyimpan snapshot source awal ke sl_pks.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tipe",
     *         in="path",
     *         required=true,
     *         description="Tipe PKS wizard",
     *         @OA\Schema(type="string", enum={"baru", "rekontrak", "addendum"}, example="baru")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     required={"leads_id", "company_id", "candidate_spk_ids"},
     *                     @OA\Property(property="leads_id", type="integer", example=123),
     *                     @OA\Property(property="company_id", type="integer", example=13),
     *                     @OA\Property(property="candidate_spk_ids", type="array", @OA\Items(type="integer"), example={789,790}),
     *                     @OA\Property(property="candidate_quotation_ids", type="array", @OA\Items(type="integer"), example={456,457})
     *                 ),
     *                 @OA\Schema(
     *                     required={"leads_id", "company_id", "candidate_quotation_ids"},
     *                     @OA\Property(property="leads_id", type="integer", example=123),
     *                     @OA\Property(property="company_id", type="integer", example=13),
     *                     @OA\Property(property="candidate_quotation_ids", type="array", @OA\Items(type="integer"), example={456,457}),
     *                     @OA\Property(property="candidate_spk_ids", type="array", @OA\Items(type="integer"), example={789})
     *                 ),
     *                 @OA\Schema(
     *                     required={"leads_id", "pks_induk_id"},
     *                     @OA\Property(property="leads_id", type="integer", example=123),
     *                     @OA\Property(property="pks_induk_id", type="integer", example=22),
     *                     @OA\Property(property="candidate_quotation_ids", type="array", @OA\Items(type="integer"), example={456,457}),
     *                     @OA\Property(property="company_id", type="integer", nullable=true, example=13)
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="PKS wizard initialized successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="pks_id", type="integer", example=99),
     *                 @OA\Property(property="nomor", type="string", example="draft/PKS/SIG/LDS001-062026-00001"),
     *                 @OA\Property(property="wizard_status_id", type="integer", example=1),
     *                 @OA\Property(property="wizard_current_step", type="integer", example=1)
     *             ),
     *             @OA\Property(property="message", type="string", example="PKS wizard initialized successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="object", example={"leads_id": {"The leads id field is required."}})
     *         )
     *     )
     * )
     */
    public function initialize(PksWizardInitializeRequest $request, string $tipe): JsonResponse
    {
        $pks = $this->pksWizardService->initialize($tipe, $request->validated(), Auth::user());

        return $this->createdResponse([
            'pks_id' => $pks->id,
            'nomor' => $pks->nomor,
            'wizard_status_id' => $pks->wizard_status_id,
            'wizard_current_step' => $pks->wizard_current_step,
        ], 'PKS wizard initialized successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/pks-wizard/{pksId}/step/{step}",
     *     summary="Get PKS wizard step data",
     *     description="Mengambil payload step PKS wizard yang tersimpan di sl_pks.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="pksId", in="path", required=true, @OA\Schema(type="integer", example=99)),
     *     @OA\Parameter(name="step", in="path", required=true, @OA\Schema(type="integer", minimum=1, maximum=7, example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Step data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="pks_id", type="integer", example=99),
     *                 @OA\Property(property="step", type="integer", example=1),
     *                 @OA\Property(property="wizard_status_id", type="integer", example=1),
     *                 @OA\Property(property="wizard_status", type="string", example="Initialized"),
     *                 @OA\Property(property="wizard_current_step", type="integer", example=1),
     *                 @OA\Property(property="wizard_completed_steps", type="array", @OA\Items(type="integer", example=1)),
     *                 @OA\Property(property="nomor", type="string", example="draft/PKS/SIG/LDS001-062026-00001"),
     *                 @OA\Property(property="tipe_pks", type="string", example="baru"),
     *                 @OA\Property(property="step_data", type="object")
     *             ),
     *             @OA\Property(property="message", type="string", example="Step data retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="PKS not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="PKS not found")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid step",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Step wizard tidak valid")
     *         )
     *     )
     * )
     */
    public function getStep(int $pksId, int $step): JsonResponse
    {
        try {
            $pks = $this->resolveAccessiblePks($pksId);

            return $this->successResponse(
                $this->pksWizardService->getStep($pks, $step),
                'Step data retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('PKS not found');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pks-wizard/{pksId}/step/{step}",
     *     summary="Update PKS wizard step data",
     *     description="Menyimpan payload per step ke wizard_payload pada sl_pks dan mengupdate progress wizard.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="pksId", in="path", required=true, @OA\Schema(type="integer", example=99)),
     *     @OA\Parameter(name="step", in="path", required=true, @OA\Schema(type="integer", minimum=1, maximum=7, example=2)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="mark_as_complete", type="boolean", example=true),
     *             @OA\Property(
     *                 property="step_data",
     *                 type="object",
     *                 example={
     *                     "tanggal_pks": "2026-07-01",
     *                     "tanggal_awal_kontrak": "2026-07-01",
     *                     "tanggal_akhir_kontrak": "2027-06-30",
     *                     "company_id": 13,
     *                     "salary_rule_id": 1,
     *                     "rule_thr_id": 2,
     *                     "kategori_sesuai_hc_id": 1,
     *                     "loyalty_id": 1
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Step updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Step 2 updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="PKS not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="PKS not found")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="object", example={"step_data": {"The step data must be an array."}})
     *         )
     *     )
     * )
     */
    public function updateStep(PksWizardStepRequest $request, int $pksId, int $step): JsonResponse
    {
        try {
            $pks = $this->resolveAccessiblePks($pksId);

            $updated = $this->pksWizardService->updateStep(
                $pks,
                $step,
                $request->input('step_data', []),
                (bool) $request->boolean('mark_as_complete', true),
                Auth::user()
            );

            return $this->successResponse(
                $this->pksWizardService->getStep($updated, $step),
                "Step {$step} updated successfully"
            );
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('PKS not found');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pks-wizard/{pksId}/preview-pasal",
     *     summary="Generate PKS wizard pasal preview",
     *     description="Generate atau regenerate preview pasal wizard dari template PKS existing. Untuk addendum dapat menerima pasal tambahan manual.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="pksId", in="path", required=true, @OA\Schema(type="integer", example=99)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="regenerate", type="boolean", example=true),
     *             @OA\Property(
     *                 property="additional_articles",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="pasal", type="string", example="Addendum 1"),
     *                     @OA\Property(property="judul", type="string", example="PASAL TAMBAHAN"),
     *                     @OA\Property(property="raw_text", type="string", example="<p>Isi pasal tambahan</p>")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pasal preview generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="message", type="string", example="Pasal preview generated successfully")
     *         )
     *     )
     * )
     */
    public function generatePasalPreview(PksWizardPasalPreviewRequest $request, int $pksId): JsonResponse
    {
        try {
            $pks = $this->resolveAccessiblePks($pksId)->loadMissing(['leads', 'pksInduk']);

            $preview = $this->pksPasalPreviewService->generatePreview($pks, $request->validated());

            return $this->successResponse($preview, 'Pasal preview generated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('PKS not found');
        }
    }

    /**
     * @OA\Put(
     *     path="/api/pks-wizard/{pksId}/preview-pasal/{pasalKey}",
     *     summary="Update PKS wizard pasal preview",
     *     description="Update raw text pasal preview sebelum finalize.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="pksId", in="path", required=true, @OA\Schema(type="integer", example=99)),
     *     @OA\Parameter(name="pasalKey", in="path", required=true, @OA\Schema(type="string", example="section_0")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"raw_text"},
     *             @OA\Property(property="pasal", type="string", example="Pasal 1"),
     *             @OA\Property(property="judul", type="string", example="RUANG LINGKUP PERJANJIAN"),
     *             @OA\Property(property="raw_text", type="string", example="<p>Raw text hasil edit</p>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pasal preview updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="message", type="string", example="Pasal preview updated successfully")
     *         )
     *     )
     * )
     */
    public function updatePasalPreview(PksWizardPasalPreviewUpdateRequest $request, int $pksId, string $pasalKey): JsonResponse
    {
        try {
            $pks = $this->resolveAccessiblePks($pksId);

            $preview = $this->pksPasalPreviewService->updatePreview($pks, $pasalKey, $request->validated());

            return $this->successResponse($preview, 'Pasal preview updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('PKS not found');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pks-wizard/{pksId}/finalize",
     *     summary="Finalize PKS wizard",
     *     description="Mengubah draft PKS wizard menjadi finalized, menyimpan site final, insert pasal final, dan menjalankan side effect status/activity.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="pksId", in="path", required=true, @OA\Schema(type="integer", example=99)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"confirm_finalize"},
     *             @OA\Property(property="confirm_finalize", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PKS wizard finalized successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="PKS wizard finalized successfully")
     *         )
     *     )
     * )
     */
    public function finalize(PksWizardFinalizeRequest $request, int $pksId): JsonResponse
    {
        try {
            $pks = $this->resolveAccessiblePks($pksId);

            $finalized = $this->pksWizardFinalizeService->finalize($pks, Auth::user());

            return $this->successResponse([
                'id' => $finalized->id,
                'nomor' => $finalized->nomor,
                'wizard_status_id' => $finalized->wizard_status_id,
                'finalized_at' => $finalized->finalized_at,
                'sites_count' => $finalized->sites->count(),
                'perjanjian_count' => $finalized->perjanjian->count(),
            ], 'PKS wizard finalized successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('PKS not found');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/pks-wizard/{pksId}",
     *     summary="Cancel PKS wizard",
     *     description="Membatalkan PKS wizard draft dengan mengubah wizard status menjadi cancelled. Tidak berlaku untuk PKS yang sudah finalized.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="pksId", in="path", required=true, @OA\Schema(type="integer", example=99)),
     *     @OA\Response(
     *         response=200,
     *         description="PKS wizard cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="PKS wizard cancelled successfully")
     *         )
     *     )
     * )
     */
    public function cancel(int $pksId): JsonResponse
    {
        try {
            $pks = $this->resolveAccessiblePks($pksId);

            $cancelled = $this->pksWizardService->cancel($pks, Auth::user());

            return $this->successResponse([
                'id' => $cancelled->id,
                'wizard_status_id' => $cancelled->wizard_status_id,
                'wizard_status' => $cancelled->wizardStatus?->nama,
            ], 'PKS wizard cancelled successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('PKS not found');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pks-wizard/source/quotations/{leadsId}",
     *     summary="Get available quotations for PKS wizard source selection",
     *     description="Mengambil daftar quotation berdasarkan leads yang bisa dipakai frontend untuk memilih candidate quotation saat initialize wizard. Jika request body spk_id dikirim, hasil akan difilter hanya quotation yang terhubung dengan SPK tersebut.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="leadsId", in="path", required=true, @OA\Schema(type="integer", example=123)),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="spk_ids", type="array", @OA\Items(type="integer"), example={789,790}),
     *             @OA\Property(property="tipe_pks", type="string", enum={"baru", "rekontrak", "addendum"}, nullable=true, example="rekontrak", description="Jika rekontrak, hanya quotation tipe rekontrak yang dikembalikan"),
     *             @OA\Property(property="search", type="string", example="SIG"),
     *             @OA\Property(property="search_by", type="string", enum={"nomor", "tipe_quotation", "company_name", "company_code", "salary_rule", "rule_thr"}, example="company_name"),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="page", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Available quotations retrieved successfully")
     * )
     */
    public function getAvailableQuotations(Request $request, int $leadsId): JsonResponse
    {
        try {
            $quotations = $this->pksWizardService->getAvailableQuotationsByLeads(
                $leadsId,
                collect($request->input('spk_ids', []))->map(fn($id) => (int) $id)->filter()->values()->all(),
                $request->input('tipe_pks'),
                $request->input('search'),
                $request->input('search_by', 'nomor'),
                max(1, (int) $request->input('per_page', 10))
            );

            return response()->json([
                'success' => true,
                'data' => $quotations->items(),
                'pagination' => [
                    'current_page' => $quotations->currentPage(),
                    'last_page' => $quotations->lastPage(),
                    'total' => $quotations->total(),
                    'per_page' => $quotations->perPage(),
                ],
                'message' => 'Available quotations retrieved successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Leads not found');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks-wizard/source/spk/{leadsId}",
     *     summary="Get available SPK for PKS wizard source selection",
     *     description="Mengambil daftar SPK berdasarkan leads. Quotation dilampirkan sebagai array quotations, bukan object quotation tunggal, karena satu SPK bisa memiliki banyak quotation melalui spk_site.",
     *     tags={"PKS Wizard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="leadsId", in="path", required=true, @OA\Schema(type="integer", example=123)),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", example="SPK-001")),
     *     @OA\Parameter(name="search_by", in="query", required=false, @OA\Schema(type="string", enum={"nomor", "quotation_nomor", "company_name", "company_code"}, example="quotation_nomor")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Available SPK retrieved successfully")
     * )
     */
    public function getAvailableSpk(Request $request, int $leadsId): JsonResponse
    {
        try {
            $spk = $this->pksWizardService->getAvailableSpkByLeads(
                $leadsId,
                $request->query('search'),
                $request->query('search_by', 'nomor'),
                max(1, $request->integer('per_page', 10))
            );

            return response()->json([
                'success' => true,
                'data' => $spk->items(),
                'pagination' => [
                    'current_page' => $spk->currentPage(),
                    'last_page' => $spk->lastPage(),
                    'total' => $spk->total(),
                    'per_page' => $spk->perPage(),
                ],
                'message' => 'Available SPK retrieved successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Leads not found');
        }
    }

    private function resolveAccessiblePks(int $pksId): Pks
    {
        return Pks::with(['wizardStatus', 'leads'])
            ->whereHas('leads', function ($query) {
                $query->filterByUserRole(Auth::user());
            })
            ->findOrFail($pksId);
    }
}
