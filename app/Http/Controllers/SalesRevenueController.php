<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SalesRevenueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Sales Revenue",
 *     description="API untuk menghitung akumulasi total nilai invoice sales berdasarkan PKS"
 * )
 */
class SalesRevenueController extends Controller
{
    public function __construct(
        protected SalesRevenueService $revenueService
    ) {
    }

    // ─── Monthly Revenue ──────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/sales-revenue/list",
     *     summary="Get monthly revenue data",
     *     tags={"Sales Revenue"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="year",       in="query", @OA\Schema(type="integer", example=2024)),
     *     @OA\Parameter(name="month",      in="query", @OA\Schema(type="integer", minimum=1, maximum=12)),
     *     @OA\Parameter(name="user_id",    in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="start_date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date",   in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getMonthlyRevenue(Request $request): JsonResponse
    {
        set_time_limit(0);

        $validator = Validator::make($request->all(), [
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|between:1,12',
            'user_id' => 'nullable|integer',
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $filters = $this->prepareFilters($request);
            $revenueData = $this->revenueService->calculateMonthlyRevenue($filters);
            $summary = $this->calculateSummary($revenueData);

            return response()->json($this->formatResponse($revenueData, $summary, $filters));
        } catch (\Exception $e) {
            return $this->serverError($e, $request);
        }
    }

    // ─── Revenue Summary ──────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/sales-revenue/summary",
     *     summary="Get revenue summary per user",
     *     tags={"Sales Revenue"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="year",  in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function getRevenueSummary(Request $request): JsonResponse
    {
        try {
            $filters = $this->prepareFilters($request);
            $summary = $this->revenueService->getSalesRevenueSummary($filters);

            return response()->json([
                'success' => true,
                'data' => $summary,
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e, $request);
        }
    }

    // ─── Revenue by Month ─────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/sales-revenue/by-month",
     *     summary="Get revenue grouped by month for a year",
     *     tags={"Sales Revenue"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="year", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function getRevenueByMonth(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $filters = ['year' => (int) $request->get('year')];
            $revenueData = $this->revenueService->calculateMonthlyRevenueOptimized($filters);

            $groupedByMonth = [];

            foreach ($revenueData as $item) {
                $month = $item['month'];

                if (!isset($groupedByMonth[$month])) {
                    $groupedByMonth[$month] = [
                        'month' => $month,
                        'month_name' => $item['month_name'],
                        'total_revenue' => 0,
                        'user_breakdown' => [],
                    ];
                }

                $groupedByMonth[$month]['total_revenue'] += $item['revenue'];
                $groupedByMonth[$month]['user_breakdown'][] = [
                    'user_id' => $item['user_id'],
                    'user_name' => $item['user_name'],
                    'user_role' => $item['user_role'],
                    'revenue' => $item['revenue'],
                    'revenue_formatted' => $item['revenue_formatted'],
                ];
            }

            ksort($groupedByMonth);

            foreach ($groupedByMonth as &$m) {
                $m['total_revenue_formatted'] = 'Rp ' . number_format($m['total_revenue'], 0, ',', '.');
                $m['user_count'] = count($m['user_breakdown']);
            }

            $grandTotal = array_sum(array_column($groupedByMonth, 'total_revenue'));

            return response()->json([
                'success' => true,
                'data' => array_values($groupedByMonth),
                'total_months' => count($groupedByMonth),
                'grand_total' => $grandTotal,
                'grand_total_formatted' => 'Rp ' . number_format($grandTotal, 0, ',', '.'),
                'year' => $request->get('year'),
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e, $request);
        }
    }

    // ─── KPI Comparison ──────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/sales-revenue/kpi",
     *     summary="KPI: actual revenue vs sales target per user per month",
     *     description="Menghitung pencapaian target (personal/branch/company) untuk setiap sales berdasarkan revenue aktual PKS.",
     *     tags={"Sales Revenue"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="year",        in="query", required=true,  @OA\Schema(type="integer", example=2025)),
     *     @OA\Parameter(name="month",       in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=12)),
     *     @OA\Parameter(name="user_id",     in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="branch_id",   in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period_type", in="query", required=false,
     *         @OA\Schema(type="string", enum={"monthly","yearly"}, default="monthly")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="user_id",              type="string"),
     *                     @OA\Property(property="user_name",            type="string"),
     *                     @OA\Property(property="month",                type="string", example="2025-06"),
     *                     @OA\Property(property="month_name",           type="string", example="June 2025"),
     *                     @OA\Property(property="actual_revenue",       type="number"),
     *                     @OA\Property(property="actual_formatted",     type="string"),
     *                     @OA\Property(property="target_personal",      type="number", nullable=true),
     *                     @OA\Property(property="target_branch",        type="number", nullable=true),
     *                     @OA\Property(property="target_company",       type="number", nullable=true),
     *                     @OA\Property(property="achievement_personal", type="number", nullable=true),
     *                     @OA\Property(property="achievement_branch",   type="number", nullable=true),
     *                     @OA\Property(property="achievement_company",  type="number", nullable=true),
     *                     @OA\Property(property="status",               type="string",
     *                         enum={"achieved","on_track","under_target","no_target"}),
     *                     @OA\Property(property="rank",                 type="integer")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="summary",
     *                 type="object",
     *                 @OA\Property(property="total_actual",             type="number"),
     *                 @OA\Property(property="total_actual_formatted",   type="string"),
     *                 @OA\Property(property="total_target_personal",    type="number"),
     *                 @OA\Property(property="total_target_formatted",   type="string"),
     *                 @OA\Property(property="avg_achievement_personal", type="number", nullable=true),
     *                 @OA\Property(property="status_breakdown",         type="object",
     *                     @OA\Property(property="achieved",     type="integer"),
     *                     @OA\Property(property="on_track",     type="integer"),
     *                     @OA\Property(property="under_target", type="integer"),
     *                     @OA\Property(property="no_target",    type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getKpi(Request $request): JsonResponse
    {
        set_time_limit(0);

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|between:1,12',
            'user_id' => 'nullable|string',
            'branch_id' => 'nullable|integer',
            'period_type' => 'nullable|in:monthly,yearly',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $filters = $this->prepareFilters($request);

            // period_type dibutuhkan service untuk query target yang tepat
            $filters['period_type'] = $request->get('period_type', 'monthly');

            $kpiResult = $this->revenueService->getKpiComparison($filters);

            return response()->json([
                'success' => true,
                'message' => 'KPI data retrieved successfully.',
                'data' => $kpiResult['data'],
                'summary' => $kpiResult['summary'],
                'metadata' => [
                    'total_records' => count($kpiResult['data']),
                    'generated_at' => now()->toDateTimeString(),
                    'filters_applied' => $filters,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e, $request);
        }
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function prepareFilters(Request $request): array
    {
        return array_filter([
            'year' => $request->filled('year') ? (int) $request->get('year') : null,
            'month' => $request->filled('month') ? (int) $request->get('month') : null,
            'user_id' => $request->filled('user_id') ? $request->get('user_id') : null,
            'branch_id' => $request->filled('branch_id') ? (int) $request->get('branch_id') : null,
            'start_date' => $request->filled('start_date') ? $request->get('start_date') : null,
            'end_date' => $request->filled('end_date') ? $request->get('end_date') : null,
        ], fn($v) => $v !== null);
    }

    private function calculateSummary(array $revenueData): array
    {
        if (empty($revenueData)) {
            return [
                'total_revenue' => 0,
                'total_revenue_formatted' => 'Rp 0',
                'user_count' => 0,
                'month_count' => 0,
                'average_per_user' => 0,
                'average_per_month' => 0,
            ];
        }

        $totalRevenue = array_sum(array_column($revenueData, 'revenue'));
        $userIds = array_unique(array_column($revenueData, 'user_id'));
        $months = array_unique(array_column($revenueData, 'month'));

        return [
            'total_revenue' => $totalRevenue,
            'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
            'user_count' => count($userIds),
            'month_count' => count($months),
            'average_per_user' => count($userIds) > 0
                ? round($totalRevenue / count($userIds), 2)
                : 0,
            'average_per_month' => count($months) > 0
                ? round($totalRevenue / count($months), 2)
                : 0,
        ];
    }

    private function formatResponse(array $revenueData, array $summary, array $filters): array
    {
        return [
            'success' => true,
            'message' => 'Monthly revenue data retrieved successfully.',
            'timestamp' => now()->toDateTimeString(),
            'data' => $revenueData,
            'summary' => $summary,
            'metadata' => [
                'total_records' => count($revenueData),
                'generated_at' => now()->toDateTimeString(),
                'filters_applied' => $filters,
                'request_time' => round(microtime(true) - LARAVEL_START, 3) . 's',
            ],
        ];
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $errors,
        ], 400);
    }

    private function serverError(\Exception $e, Request $request): JsonResponse
    {
        Log::error('SalesRevenueController error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request' => $request->all(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Internal server error.',
            'error' => config('app.debug') ? $e->getMessage() : 'Please contact administrator.',
        ], 500);
    }
}