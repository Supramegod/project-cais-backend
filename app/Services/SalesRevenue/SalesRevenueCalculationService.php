<?php

namespace App\Services\SalesRevenue;

use App\Models\Pks;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesRevenueCalculationService
{
    public function __construct(
        protected SalesRevenueQueryService $queryService
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    public function calculateMonthlyRevenue(array $filters = []): array
    {
        $salesUsers = $this->queryService->fetchSalesUsers($filters);

        if ($salesUsers->isEmpty()) {
            return [];
        }

        $userIds = $salesUsers->pluck('id')->all();
        $allPks = $this->queryService->getAllPksBySalesUsers($userIds, $filters);

        if ($allPks->isEmpty()) {
            return [];
        }

        $invoiceByPks = $this->queryService->getBulkInvoiceTotals($allPks);
        $userBranchMap = $salesUsers->pluck('branch_id', 'id');

        $userMonthlyRevenue = [];

        foreach ($allPks as $pks) {
            $totalInvoice = $invoiceByPks[$pks->id] ?? 0;

            if ($totalInvoice <= 0) {
                continue;
            }

            $contractDuration = $this->calculateContractDuration($pks);

            if ($contractDuration <= 0) {
                continue;
            }

            $monthlyRevenue = $totalInvoice / $contractDuration;

            $breakdown = $this->generateMonthlyBreakdown(
                $pks->kontrak_awal,
                $pks->kontrak_akhir,
                $monthlyRevenue,
                $filters
            );

            if (empty($breakdown)) {
                continue;
            }

            $userId = $pks->_sales_user_id;

            foreach ($breakdown as $month => $revenue) {
                $userMonthlyRevenue[$userId][$month]
                    = ($userMonthlyRevenue[$userId][$month] ?? 0) + $revenue;
            }
        }

        return $this->formatResult($userMonthlyRevenue, $salesUsers, $userBranchMap);
    }

    /**
     * KPI Comparison: aktual vs target per user per bulan.
     */
    /**
     * Summary revenue per sales.
     */
    public function getSalesRevenueSummary(array $filters = []): array
    {
        $monthlyData = $this->calculateMonthlyRevenue($filters);
        $summary = [];

        foreach ($monthlyData as $data) {
            $uid = $data['user_id'];

            if (!isset($summary[$uid])) {
                $summary[$uid] = [
                    'user_id' => $uid,
                    'user_name' => $data['user_name'],
                    'total_revenue' => 0,
                    'month_count' => 0,
                    'months' => [],
                ];
            }

            $summary[$uid]['total_revenue'] += $data['revenue'];
            $summary[$uid]['month_count']++;
            $summary[$uid]['months'][$data['month']] = $data['revenue'];
        }

        foreach ($summary as &$s) {
            $s['total_revenue_formatted'] = $this->formatRupiah($s['total_revenue']);
            $s['average_monthly'] = $s['month_count'] > 0
                ? $s['total_revenue'] / $s['month_count'] : 0;
            $s['average_monthly_formatted'] = $this->formatRupiah($s['average_monthly']);
        }

        return array_values($summary);
    }

    /**
     * Versi query builder langsung (optimized).
     */
    public function calculateMonthlyRevenueOptimized(array $filters = []): array
    {
        $query = DB::table('sl_pks as p')
            ->select([
                'u.id as user_id',
                'u.full_name as user_name',
                'u.role_id',
                DB::raw('DATE_FORMAT(p.kontrak_awal, "%Y-%m") as month_start'),
                DB::raw('DATE_FORMAT(p.kontrak_akhir, "%Y-%m") as month_end'),
                DB::raw('TIMESTAMPDIFF(MONTH, p.kontrak_awal, p.kontrak_akhir) + 1 as contract_months'),
                DB::raw('COALESCE(SUM(qdc.total_invoice), 0) as total_invoice'),
            ])
            ->join('sl_leads as l', 'p.leads_id', '=', 'l.id')
            ->leftJoin('sl_tim_sales_details as tsd', 'l.tim_sales_d_id', '=', 'tsd.id')
            ->leftJoin('m_user as u', 'tsd.user_id', '=', 'u.id')
            ->leftJoin('sl_quotation as q', function ($join) {
                $join->on('p.quotation_id', '=', 'q.id')
                    ->orOn('q.leads_id', '=', 'p.leads_id');
            })
            ->leftJoin('sl_quotation_detail_coss as qdc', 'q.id', '=', 'qdc.quotation_id')
            ->where('p.is_aktif', 1)
            ->whereIn('u.role_id', [29, 30, 31, 32, 33])
            ->whereNotNull('p.kontrak_awal')
            ->whereNotNull('p.kontrak_akhir')
            ->where('qdc.total_invoice', '>', 0)
            ->groupBy('u.id', 'u.full_name', 'u.role_id', 'p.id', 'p.kontrak_awal', 'p.kontrak_akhir');

        if (isset($filters['user_id'])) {
            $query->where('u.id', $filters['user_id']);
        }

        if (isset($filters['year'])) {
            $query->whereYear('p.kontrak_awal', '<=', $filters['year'])
                ->whereYear('p.kontrak_akhir', '>=', $filters['year']);
        }

        if (isset($filters['month'])) {
            $year = $filters['year'] ?? date('Y');
            $date = Carbon::create($year, $filters['month'], 1);
            $query->where('p.kontrak_awal', '<=', $date->endOfMonth())
                ->where('p.kontrak_akhir', '>=', $date->startOfMonth());
        }

        $rows = $query->get();
        $result = [];

        foreach ($rows as $row) {
            $months = max(1, (int) $row->contract_months);
            $monthly = $row->total_invoice / $months;
            $start = Carbon::parse($row->month_start . '-01');
            $end = Carbon::parse($row->month_end . '-01');

            if (!isset($result[$row->user_id])) {
                $result[$row->user_id] = [
                    'meta' => [
                        'user_name' => $row->user_name,
                        'role_id' => $row->role_id,
                    ],
                    'months' => [],
                ];
            }

            foreach (CarbonPeriod::create($start, '1 month', $end) as $date) {
                $key = $date->format('Y-m');
                $result[$row->user_id]['months'][$key]
                    = ($result[$row->user_id]['months'][$key] ?? 0) + $monthly;
            }
        }

        $formatted = [];

        foreach ($result as $userId => $data) {
            foreach ($data['months'] as $month => $revenue) {
                $formatted[] = [
                    'user_id' => $userId,
                    'user_name' => $data['meta']['user_name'] ?? 'Unknown',
                    'user_role' => $data['meta']['role_id'] ?? 0,
                    'month' => $month,
                    'month_name' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                    'revenue' => round($revenue, 2),
                    'revenue_formatted' => $this->formatRupiah($revenue),
                ];
            }
        }

        return $formatted;
    }

    // ─── Private: Revenue Helpers ─────────────────────────────────────────────

    private function calculateContractDuration(Pks $pks): int
    {
        if (!$pks->kontrak_awal || !$pks->kontrak_akhir) {
            return 0;
        }

        try {
            $start = Carbon::parse($pks->kontrak_awal)->startOfMonth();
            $end = Carbon::parse($pks->kontrak_akhir)->startOfMonth();

            return $end->lt($start) ? 0 : max(1, $start->diffInMonths($end) + 1);
        } catch (\Exception $e) {
            Log::error('calculateContractDuration error', [
                'pks_id' => $pks->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    private function generateMonthlyBreakdown(
        mixed $contractStart,
        mixed $contractEnd,
        float $monthlyRevenue,
        array $filters = []
    ): array {
        if (!$contractStart || !$contractEnd) {
            return [];
        }

        try {
            $start = Carbon::parse($contractStart)->startOfMonth();
            $end = Carbon::parse($contractEnd)->endOfMonth();
            $period = CarbonPeriod::create($start, '1 month', $end);

            $breakdown = [];

            foreach ($period as $date) {
                if (isset($filters['year']) && $date->year != $filters['year'])
                    continue;
                if (isset($filters['month']) && $date->month != $filters['month'])
                    continue;
                if (isset($filters['start_date']) && $date->format('Y-m-d') < $filters['start_date'])
                    continue;
                if (isset($filters['end_date']) && $date->format('Y-m-d') > $filters['end_date'])
                    continue;

                $breakdown[$date->format('Y-m')] = $monthlyRevenue;
            }

            return $breakdown;
        } catch (\Exception $e) {
            Log::error('generateMonthlyBreakdown error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function formatResult(
        array $userMonthlyRevenue,
        Collection $salesUsers,
        Collection $userBranchMap
    ): array {
        $userMap = $salesUsers->keyBy('id');
        $formatted = [];

        foreach ($userMonthlyRevenue as $userId => $months) {
            $user = $userMap->get($userId);

            foreach ($months as $month => $revenue) {
                $formatted[] = [
                    'user_id' => $userId,
                    'user_name' => $user?->full_name ?? 'Unknown',
                    'user_role' => $user?->role_id ?? 0,
                    'branch_id' => $userBranchMap->get($userId),
                    'month' => $month,
                    'month_name' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                    'revenue' => round($revenue, 2),
                    'revenue_formatted' => $this->formatRupiah($revenue),
                ];
            }
        }

        usort(
            $formatted,
            fn($a, $b) =>
            $a['user_id'] <=> $b['user_id'] ?: $a['month'] <=> $b['month']
        );

        return $formatted;
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
