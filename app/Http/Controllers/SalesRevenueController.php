<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SalesRevenueService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Sales Revenue",
 *     description="API untuk menghitung akumulasi total nilai invoice sales berdasarkan PKS"
 * )
 */

class SalesRevenueController extends Controller
{
    protected $revenueService;

    public function __construct(SalesRevenueService $revenueService)
    {
        $this->revenueService = $revenueService;
    }

    /**
     * @OA\Get(
     *     path="/api/sales-revenue/list",
     *     summary="Get monthly revenue data without pagination",
     *     description="Calculate accumulated invoice values for sales (Role ID: 29-33) grouped by user and month. Returns array without pagination.",
     *     operationId="getMonthlyRevenue",
     *     tags={"Sales Revenue"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Year filter (e.g., 2024)",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=2024,
     *             minimum=2000,
     *             maximum=2100
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         description="Month filter (1-12)",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=6,
     *             minimum=1,
     *             maximum=12
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by specific user ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2024-12-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Monthly revenue data retrieved successfully"),
     *             @OA\Property(property="timestamp", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer", example=123),
     *                     @OA\Property(property="user_name", type="string", example="John Doe"),
     *                     @OA\Property(property="user_role", type="integer", example=29),
     *                     @OA\Property(property="month", type="string", example="2024-06"),
     *                     @OA\Property(property="month_name", type="string", example="June 2024"),
     *                     @OA\Property(property="revenue", type="number", format="float", example=10000000),
     *                     @OA\Property(property="revenue_formatted", type="string", example="Rp 10.000.000")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="summary",
     *                 type="object",
     *                 @OA\Property(property="total_revenue", type="number", example=120000000),
     *                 @OA\Property(property="total_revenue_formatted", type="string", example="Rp 120.000.000"),
     *                 @OA\Property(property="user_count", type="integer", example=5),
     *                 @OA\Property(property="month_count", type="integer", example=12),
     *                 @OA\Property(property="average_per_user", type="number", example=24000000),
     *                 @OA\Property(property="average_per_month", type="number", example=10000000)
     *             ),
     *             @OA\Property(
     *                 property="metadata",
     *                 type="object",
     *                 @OA\Property(property="total_records", type="integer", example=50),
     *                 @OA\Property(property="generated_at", type="string", format="date-time"),
     *                 @OA\Property(property="filters_applied", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Server error occurred")
     *         )
     *     )
     * )
     */
    public function getMonthlyRevenue(Request $request): JsonResponse
    {set_time_limit(0);
        try {
            // Validasi parameter
            $validator = Validator::make($request->all(), [
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|between:1,12',
                'user_id' => 'nullable|integer|exists:users,id',
                'start_date' => 'nullable|date|date_format:Y-m-d',
                'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
                'optimized' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ], 400);
            }

            // Siapkan filter
            $filters = $this->prepareFilters($request);

            // Hitung revenue data
            // $revenueData = $this->calculateRevenueData($filters, $request->get('optimized', true));
            $revenueData = $this->revenueService->calculateMonthlyRevenue($filters);
            \Log::info('Revenue data calculated successfully', ['data' => $revenueData]);

            // Hitung summary
            $summary = $this->calculateSummary($revenueData);

            // Format response
            $response = $this->formatResponse($revenueData, $summary, $filters);

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Sales Revenue API Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Please contact administrator',
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sales-revenue/summary",
     *     summary="Get revenue summary by user",
     *     description="Get aggregated revenue summary grouped by sales user",
     *     operationId="getRevenueSummary",
     *     tags={"Sales Revenue"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Year filter",
     *         required=false,
     *         @OA\Schema(type="integer", example=2024)
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         description="Month filter",
     *         required=false,
     *         @OA\Schema(type="integer", example=6)
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Revenue summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer"),
     *                     @OA\Property(property="user_name", type="string"),
     *                     @OA\Property(property="user_role", type="integer"),
     *                     @OA\Property(property="total_revenue", type="number"),
     *                     @OA\Property(property="total_revenue_formatted", type="string"),
     *                     @OA\Property(property="month_count", type="integer"),
     *                     @OA\Property(property="average_monthly", type="number"),
     *                     @OA\Property(property="average_monthly_formatted", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="grand_total", type="number"),
     *             @OA\Property(property="grand_total_formatted", type="string"),
     *             @OA\Property(property="total_users", type="integer")
     *         )
     *     )
     * )
     */
    public function getRevenueSummary(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|between:1,12',
                'user_id' => 'nullable|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ], 400);
            }

            $filters = $this->prepareFilters($request);
            $summary = $this->revenueService->getSalesRevenueSummary($filters);

            return response()->json([
                'success' => true,
                'message' => 'Revenue summary retrieved successfully',
                'data' => $summary,
                'grand_total' => array_sum(array_column($summary, 'total_revenue')),
                'grand_total_formatted' => 'Rp ' . number_format(array_sum(array_column($summary, 'total_revenue')), 0, ',', '.'),
                'total_users' => count($summary),
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Sales Revenue Summary API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving revenue summary: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sales-revenue/by-user",
     *     summary="Get revenue grouped by user",
     *     description="Get revenue data grouped by user with monthly breakdown",
     *     operationId="getRevenueByUser",
     *     tags={"Sales Revenue"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Year filter",
     *         required=true,
     *         @OA\Schema(type="integer", example=2024)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="user_id", type="integer"),
     *                     @OA\Property(property="user_name", type="string"),
     *                     @OA\Property(property="user_role", type="integer"),
     *                     @OA\Property(property="total_revenue", type="number"),
     *                     @OA\Property(property="total_revenue_formatted", type="string"),
     *                     @OA\Property(
     *                         property="monthly_breakdown",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="month", type="string"),
     *                             @OA\Property(property="month_name", type="string"),
     *                             @OA\Property(property="revenue", type="number"),
     *                             @OA\Property(property="revenue_formatted", type="string")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getRevenueByUser(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'year' => 'required|integer|min:2000|max:2100',
                'optimized' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ], 400);
            }

            $filters = ['year' => $request->get('year')];
            $optimized = $request->get('optimized', true);

            // if ($optimized) {
            //     $revenueData = $this->revenueService->calculateMonthlyRevenueOptimized($filters);
            // } else {
                $revenueData = $this->revenueService->calculateMonthlyRevenue($filters);
            // }

            // Group by user
            $groupedByUser = [];

            foreach ($revenueData as $item) {
                $userId = $item['user_id'];

                if (!isset($groupedByUser[$userId])) {
                    $groupedByUser[$userId] = [
                        'user_id' => $userId,
                        'user_name' => $item['user_name'],
                        'user_role' => $item['user_role'],
                        'total_revenue' => 0,
                        'monthly_breakdown' => [],
                    ];
                }

                $groupedByUser[$userId]['total_revenue'] += $item['revenue'];
                $groupedByUser[$userId]['monthly_breakdown'][] = [
                    'month' => $item['month'],
                    'month_name' => $item['month_name'],
                    'revenue' => $item['revenue'],
                    'revenue_formatted' => $item['revenue_formatted'],
                ];
            }

            // Format total revenue
            foreach ($groupedByUser as &$user) {
                $user['total_revenue_formatted'] = 'Rp ' . number_format($user['total_revenue'], 0, ',', '.');
                $user['average_monthly'] = count($user['monthly_breakdown']) > 0
                    ? $user['total_revenue'] / count($user['monthly_breakdown'])
                    : 0;
                $user['average_monthly_formatted'] = 'Rp ' . number_format($user['average_monthly'], 0, ',', '.');
            }

            return response()->json([
                'success' => true,
                'data' => array_values($groupedByUser),
                'total_users' => count($groupedByUser),
                'grand_total' => array_sum(array_column($groupedByUser, 'total_revenue')),
                'grand_total_formatted' => 'Rp ' . number_format(array_sum(array_column($groupedByUser, 'total_revenue')), 0, ',', '.'),
                'year' => $request->get('year'),
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Revenue By User API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sales-revenue/by-month",
     *     summary="Get revenue grouped by month",
     *     description="Get revenue data grouped by month with user breakdown",
     *     operationId="getRevenueByMonth",
     *     tags={"Sales Revenue"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Year filter",
     *         required=true,
     *         @OA\Schema(type="integer", example=2024)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="month", type="string"),
     *                     @OA\Property(property="month_name", type="string"),
     *                     @OA\Property(property="total_revenue", type="number"),
     *                     @OA\Property(property="total_revenue_formatted", type="string"),
     *                     @OA\Property(
     *                         property="user_breakdown",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="user_id", type="integer"),
     *                             @OA\Property(property="user_name", type="string"),
     *                             @OA\Property(property="revenue", type="number"),
     *                             @OA\Property(property="revenue_formatted", type="string")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getRevenueByMonth(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'year' => 'required|integer|min:2000|max:2100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ], 400);
            }

            $filters = ['year' => $request->get('year')];
            $revenueData = $this->revenueService->calculateMonthlyRevenueOptimized($filters);

            // Group by month
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

            // Sort by month
            ksort($groupedByMonth);

            // Format total revenue
            foreach ($groupedByMonth as &$month) {
                $month['total_revenue_formatted'] = 'Rp ' . number_format($month['total_revenue'], 0, ',', '.');
                $month['user_count'] = count($month['user_breakdown']);
            }

            return response()->json([
                'success' => true,
                'data' => array_values($groupedByMonth),
                'total_months' => count($groupedByMonth),
                'grand_total' => array_sum(array_column($groupedByMonth, 'total_revenue')),
                'grand_total_formatted' => 'Rp ' . number_format(array_sum(array_column($groupedByMonth, 'total_revenue')), 0, ',', '.'),
                'year' => $request->get('year'),
                'timestamp' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Revenue By Month API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Helper Methods
     */

    /**
     * Prepare filters from request
     */
    private function prepareFilters(Request $request): array
    {
        $filters = [];

        if ($request->has('year')) {
            $filters['year'] = $request->get('year');
        }

        if ($request->has('month')) {
            $filters['month'] = $request->get('month');
        }

        if ($request->has('user_id')) {
            $filters['user_id'] = $request->get('user_id');
        }

        if ($request->has('start_date')) {
            $filters['start_date'] = $request->get('start_date');
        }

        if ($request->has('end_date')) {
            $filters['end_date'] = $request->get('end_date');
        }

        return $filters;
    }

    /**
     * Calculate revenue data based on optimization flag
     */
    private function calculateRevenueData(array $filters, bool $optimized = true): array
    {
        if ($optimized) {
            return $this->revenueService->calculateMonthlyRevenueOptimized($filters);
        }

        return $this->revenueService->calculateMonthlyRevenue($filters);
    }

    /**
     * Calculate summary from revenue data
     */
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
            'average_per_user' => count($userIds) > 0 ? $totalRevenue / count($userIds) : 0,
            'average_per_month' => count($months) > 0 ? $totalRevenue / count($months) : 0,
        ];
    }

    /**
     * Format response
     */
    private function formatResponse(array $revenueData, array $summary, array $filters): array
    {
        return [
            'success' => true,
            'message' => 'Monthly revenue data retrieved successfully',
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
}