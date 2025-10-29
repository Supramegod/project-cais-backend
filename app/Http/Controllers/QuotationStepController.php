<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\QuotationStepService;
use App\Http\Requests\QuotationStepRequest;
use App\Http\Resources\QuotationResource;
use App\Http\Resources\QuotationStepResource;

/**
 * @OA\Tag(
 *     name="Quotations",
 *     description="API Endpoints for Quotation Management"
 * )
 */
class QuotationStepController extends Controller
{
    protected $quotationStepService;

    public function __construct(QuotationStepService $quotationStepService)
    {
        $this->quotationStepService = $quotationStepService;
    }

    /**
     * Get quotation data for specific step
     * 
     * @OA\Get(
     *     path="/api/quotations-step/{id}/step/{step}",
     *     summary="Get quotation data for specific step",
     *     tags={"Quotations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Quotation ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="step",
     *         in="path",
     *         required=true,
     *         description="Step number (1-12)",
     *         @OA\Schema(type="integer", minimum=1, maximum=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Step data retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to get step data"),
     *             @OA\Property(property="error", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function getStep(string $id, int $step): JsonResponse
    {
        try {
            $quotation = Quotation::with($this->quotationStepService->getStepRelations($step))
                ->notDeleted()
                ->findOrFail($id);

            $stepData = $this->quotationStepService->prepareStepData($quotation, $step);

            return response()->json([
                'success' => true,
                'data' => new QuotationStepResource($quotation, $step),
                'message' => 'Step data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get step data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update specific step
     * 
     * @OA\Post(
     *     path="/api/quotations-step/{id}/step/{step}",
     *     summary="Update specific quotation step",
     *     tags={"Quotations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Quotation ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="step",
     *         in="path",
     *         required=true,
     *         description="Step number (1-12)",
     *         @OA\Schema(type="integer", minimum=1, maximum=12)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Step data (varies by step)",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="Step 1 - Contract Type",
     *                     @OA\Property(property="jenis_kontrak", type="string", example="Reguler")
     *                 ),
     *                 @OA\Schema(
     *                     description="Step 2 - Contract Details",
     *                     @OA\Property(property="mulai_kontrak", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="kontrak_selesai", type="string", format="date", example="2024-12-31"),
     *                     @OA\Property(property="tgl_penempatan", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="salary_rule", type="integer", example=1),
     *                     @OA\Property(property="top", type="string", example="7 Hari"),
     *                     @OA\Property(property="ada_cuti", type="string", example="Ada"),
     *                     @OA\Property(property="cuti", type="array", @OA\Items(type="string", example="Cuti Tahunan")),
     *                     @OA\Property(property="edit", type="boolean", example=false)
     *                 ),
     *                 @OA\Schema(
     *                     description="Step 4 - Wage Calculation",
     *                     @OA\Property(property="upah", type="string", example="UMP"),
     *                     @OA\Property(property="manajemen_fee", type="integer", example=1),
     *                     @OA\Property(property="persentase", type="number", format="float", example=10.5),
     *                     @OA\Property(property="thr", type="string", example="Diprovisikan"),
     *                     @OA\Property(property="kompensasi", type="string", example="Diprovisikan"),
     *                     @OA\Property(property="lembur", type="string", example="Flat"),
     *                     @OA\Property(property="is_ppn", type="boolean", example=true),
     *                     @OA\Property(property="ppn_pph_dipotong", type="string", example="Management Fee")
     *                 ),
     *                 @OA\Schema(
     *                     description="Step 5 - BPJS & Company Data",
     *                     @OA\Property(property="penjamin", type="object", example={"1": "BPJS", "2": "Takaful"}),
     *                     @OA\Property(property="jkk", type="object", example={"1": true, "2": false}),
     *                     @OA\Property(property="jkm", type="object", example={"1": true, "2": true}),
     *                     @OA\Property(property="jht", type="object", example={"1": true, "2": true}),
     *                     @OA\Property(property="jp", type="object", example={"1": true, "2": false}),
     *                     @OA\Property(property="nominal_takaful", type="object", example={"1": 50000, "2": 75000}),
     *                     @OA\Property(property="jenis_perusahaan", type="integer", example=1),
     *                     @OA\Property(property="bidang_perusahaan", type="integer", example=2),
     *                     @OA\Property(property="resiko", type="string", example="Rendah")
     *                 ),
     *                 @OA\Schema(
     *                     description="Step 6 - Supporting Applications",
     *                     @OA\Property(property="aplikasi_pendukung", type="array", @OA\Items(type="integer", example=1))
     *                 ),
     *                 @OA\Schema(
     *                     description="Step 11 - Billing & Agreement",
     *                     @OA\Property(property="penagihan", type="string", example="Bulanan"),
     *                     @OA\Property(property="training_id", type="string", example="1,2,3")
     *                 )
     *             },
     *             @OA\Property(property="edit", type="boolean", example=false, description="Set to true if only updating without progressing step")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string", example="Step 1 updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Step method not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update step 1"),
     *             @OA\Property(property="error", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function updateStep(QuotationStepRequest $request, $id, $step): JsonResponse
    {
        DB::beginTransaction();
        try {
            $quotation = Quotation::notDeleted()->findOrFail($id);

            $updateMethod = 'updateStep' . $step;
            if (!method_exists($this->quotationStepService, $updateMethod)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Step method not found'
                ], 404);
            }

            $this->quotationStepService->$updateMethod($quotation, $request);

            // Update step progress
            if (!$request->has('edit') || !$request->edit) {
                $quotation->update([
                    'step' => max($quotation->step, $step + 1),
                    'updated_by' => Auth::user()->full_name
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new QuotationStepResource($quotation, $step),
                'message' => "Step {$step} updated successfully"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => "Failed to update step {$step}",
                'error' => $e->getMessage()
            ], 500);
        }
    }
}