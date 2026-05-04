<?php

namespace App\Services;

use App\Models\Pks;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesRevenueService
{
    // ─── Public API ───────────────────────────────────────────────────────────


    public function calculateMonthlyRevenue(array $filters = []): array
    {

        $salesUsers = $this->fetchSalesUsers($filters);

        if ($salesUsers->isEmpty()) {
            return [];
        }

        $userIds = $salesUsers->pluck('id')->all();

        // Step 2: Semua PKS untuk semua user → 2 query (linked + model)
        $allPks = $this->getAllPksBySalesUsers($userIds, $filters);

        if ($allPks->isEmpty()) {
            return [];
        }

        // Step 3: Semua total_invoice untuk semua PKS → 3 query
        $invoiceByPks = $this->getBulkInvoiceTotals($allPks);

        // Step 4: Lookup map (di memory, 0 query)
        $userBranchMap = $salesUsers->pluck('branch_id', 'id');

        // Step 5: Semua kalkulasi di memory
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

            // _sales_user_id di-inject oleh getAllPksBySalesUsers
            $userId = $pks->_sales_user_id;

            foreach ($breakdown as $month => $revenue) {
                $userMonthlyRevenue[$userId][$month]
                    = ($userMonthlyRevenue[$userId][$month] ?? 0) + $revenue;
            }
        }

        // Step 6: Format flat array — lookup dari Collection, 0 query
        return $this->formatResult($userMonthlyRevenue, $salesUsers, $userBranchMap);
    }

    /**
     * KPI Comparison: aktual vs target per user per bulan.
     *
     * Tidak ada query di dalam loop — semua data diambil sekaligus
     * lalu di-map di memory dengan Collection::keyBy.
     */
    public function getKpiComparison(array $filters = []): array
    {
        // Reuse calculateMonthlyRevenue — tidak ada query ulang
        $revenueRows = $this->calculateMonthlyRevenue($filters);

        if (empty($revenueRows)) {
            return $this->buildEmptyKpiResponse();
        }

        $userIds = array_unique(array_column($revenueRows, 'user_id'));
        $branchIds = array_filter(array_unique(array_column($revenueRows, 'branch_id')));
        $months = array_unique(array_column($revenueRows, 'month'));
        $years = array_unique(array_map(fn($m) => (int) substr($m, 0, 4), $months));

        // Ambil semua target dalam 1 query
        $allTargets = $this->fetchAllTargets($userIds, $branchIds, $years, $filters);

        // Build lookup maps — O(1) access, tidak ada query di loop
        $personalMap = $allTargets
            ->where('type', 'personal')
            ->keyBy(fn($t) => SalesTarget::buildKey('personal', $t->user_id, null, $t->year, $t->month));

        $branchMap = $allTargets
            ->where('type', 'branch')
            ->keyBy(fn($t) => SalesTarget::buildKey('branch', null, $t->branch_id, $t->year, $t->month));

        $companyMap = $allTargets
            ->where('type', 'company')
            ->keyBy(fn($t) => SalesTarget::buildKey('company', null, null, $t->year, $t->month));

        $kpiRows = [];

        foreach ($revenueRows as $row) {
            $year = (int) substr($row['month'], 0, 4);
            $month = (int) substr($row['month'], 5, 2);

            $targetPersonal = $personalMap->get(
                SalesTarget::buildKey('personal', $row['user_id'], null, $year, $month)
            )?->target_amount;

            $targetBranch = $branchMap->get(
                SalesTarget::buildKey('branch', null, $row['branch_id'] ?? null, $year, $month)
            )?->target_amount;

            $targetCompany = $companyMap->get(
                SalesTarget::buildKey('company', null, null, $year, $month)
            )?->target_amount;

            $actual = $row['revenue'];

            $kpiRows[] = [
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
                'month' => $row['month'],
                'month_name' => $row['month_name'],
                'actual_revenue' => $actual,
                'actual_formatted' => $this->formatRupiah($actual),
                'target_personal' => $targetPersonal,
                'target_branch' => $targetBranch,
                'target_company' => $targetCompany,
                'achievement_personal' => $this->calcAchievement($actual, $targetPersonal),
                'achievement_branch' => $this->calcAchievement($actual, $targetBranch),
                'achievement_company' => $this->calcAchievement($actual, $targetCompany),
                'status' => $this->resolveStatus(
                    $actual,
                    $targetPersonal ?? $targetBranch ?? $targetCompany
                ),
            ];
        }

        usort(
            $kpiRows,
            fn($a, $b) =>
            $a['user_id'] <=> $b['user_id'] ?: $a['month'] <=> $b['month']
        );

        $kpiRows = $this->appendRanking($kpiRows);

        return [
            'data' => $kpiRows,
            'summary' => $this->buildKpiSummary($kpiRows),
        ];
    }

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
     *
     * PERBAIKAN: User::find() dalam loop dihapus.
     * user_name & role_id langsung dari JOIN, disimpan bersama revenue data.
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

            // ✅ Simpan meta bersama data — tidak perlu User::find() nanti
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

        // ✅ Tidak ada User::find() di sini
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

    private function fetchSalesUsers(array $filters): Collection
    {
        return User::whereIn('cais_role_id', [29, 30, 31, 32, 33])
            ->when(isset($filters['user_id']), fn($q) => $q->where('id', $filters['user_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->get(['id', 'full_name', 'role_id', 'branch_id']);
    }

    private function getAllPksBySalesUsers(array $userIds, array $filters = []): Collection
    {
        $linkedPksIds = DB::table('sl_pks as p')
            ->select('p.id as pks_id', 'tsd.user_id as sales_user_id')
            ->join('sl_leads as l', 'p.leads_id', '=', 'l.id')
            ->join('sl_tim_sales_details as tsd', 'l.tim_sales_d_id', '=', 'tsd.id')
            ->whereIn('tsd.user_id', $userIds)
            ->where('p.is_aktif', 1)
            ->whereNull('p.deleted_at')
            ->when(isset($filters['year']), function ($q) use ($filters) {
                $q->whereYear('p.kontrak_awal', '<=', $filters['year'])
                    ->whereYear('p.kontrak_akhir', '>=', $filters['year']);
            })
            ->when(isset($filters['month']), function ($q) use ($filters) {
                $year = $filters['year'] ?? date('Y');
                $date = Carbon::create($year, $filters['month'], 1);
                $q->where('p.kontrak_awal', '<=', $date->endOfMonth())
                    ->where('p.kontrak_akhir', '>=', $date->startOfMonth());
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sl_spk_site as ss')
                    ->join('sl_site as s', 'ss.site_id', '=', 's.id')
                    ->whereColumn('ss.spk_id', 'p.spk_id')
                    ->where('s.is_active', 1);
            })
            ->when(
                isset($filters['start_date']),
                fn($q) =>
                $q->where('p.kontrak_akhir', '>=', $filters['start_date'])
            )
            ->when(
                isset($filters['end_date']),
                fn($q) =>
                $q->where('p.kontrak_awal', '<=', $filters['end_date'])
            )
            ->get()
            ->pluck('sales_user_id', 'pks_id'); // [ pks_id => sales_user_id ]

        if ($linkedPksIds->isEmpty()) {
            return collect();
        }

        $pksIds = $linkedPksIds->keys()->all();

        // Ambil model Pks — hanya kolom yang diperlukan
        $pksList = Pks::whereIn('id', $pksIds)
            ->select(['id', 'leads_id', 'quotation_id', 'kontrak_awal', 'kontrak_akhir'])
            ->get();

        // Inject sales_user_id di memory — 0 query tambahan
        return $pksList->each(function ($pks) use ($linkedPksIds) {
            $pks->_sales_user_id = $linkedPksIds->get($pks->id);
        });
    }

    /**
     * Ambil total invoice untuk SEMUA PKS sekaligus — 3 query total, bukan N*M query.
     *
     * Query 1: semua quotation via leads_id
     * Query 2: sum(total_invoice) per quotation_id
     * (Query 3 adalah direct quotation_id yang sudah ada di model PKS — tidak perlu query)
     *
     * Return: [ pks_id => total_invoice ]
     */
    private function getBulkInvoiceTotals(Collection $allPks): array
    {
        $directQuotationIds = $allPks->pluck('quotation_id')->filter()->unique()->values()->all();
        $leadsIds = $allPks->pluck('leads_id')->filter()->unique()->values()->all();

        // Query 1: cari quotation tambahan via leads_id
        $quotationsByLeads = DB::table('sl_quotation')
            ->whereIn('leads_id', $leadsIds)
            ->whereNull('deleted_at')
            ->select('id', 'leads_id')
            ->get()
            ->groupBy('leads_id')
            ->map(fn($q) => $q->pluck('id')); // [ leads_id => [quotation_ids] ]

        // Gabungkan semua quotation_id yang relevan
        $allQuotationIds = collect($directQuotationIds)
            ->merge($quotationsByLeads->flatten())
            ->unique()
            ->values()
            ->all();

        if (empty($allQuotationIds)) {
            return [];
        }

        // Query 2: sum per quotation_id
        $invoiceByQuotation = DB::table('sl_quotation_detail_coss')
            ->whereIn('quotation_id', $allQuotationIds)
            ->whereNull('deleted_at')
            ->groupBy('quotation_id')
            ->pluck(DB::raw('SUM(total_invoice)'), 'quotation_id')
            ->toArray();

        // Map ke pks_id di memory — 0 query
        $invoiceByPks = [];

        foreach ($allPks as $pks) {
            $total = 0.0;

            // Direct quotation_id
            if ($pks->quotation_id && isset($invoiceByQuotation[$pks->quotation_id])) {
                $total += (float) $invoiceByQuotation[$pks->quotation_id];
            }

            // Quotation lain via leads_id (hindari double-count)
            if ($pks->leads_id && isset($quotationsByLeads[$pks->leads_id])) {
                foreach ($quotationsByLeads[$pks->leads_id] as $qid) {
                    if ($qid != $pks->quotation_id && isset($invoiceByQuotation[$qid])) {
                        $total += (float) $invoiceByQuotation[$qid];
                    }
                }
            }

            $invoiceByPks[$pks->id] = $total;
        }

        return $invoiceByPks;
    }

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

    /**
     * Format dari [ user_id => [ month => revenue ] ] ke flat array.
     * Lookup dari Collection — 0 query.
     */
    private function formatResult(
        array $userMonthlyRevenue,
        Collection $salesUsers,
        Collection $userBranchMap
    ): array {
        $userMap = $salesUsers->keyBy('id'); // O(1) lookup
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

    // ─── Private: KPI Helpers ─────────────────────────────────────────────────

    private function fetchAllTargets(
        array $userIds,
        array $branchIds,
        array $years,
        array $filters
    ): Collection {
        $periodType = $filters['period_type'] ?? 'monthly';

        return SalesTarget::where('period_type', $periodType)
            ->whereIn('year', $years)
            ->when(isset($filters['month']), fn($q) => $q->where('month', $filters['month']))
            ->where(function ($q) use ($userIds, $branchIds) {
                $q->where(function ($q2) use ($userIds) {
                    $q2->where('type', 'personal')->whereIn('user_id', $userIds);
                })->orWhere(function ($q2) use ($branchIds) {
                    $q2->where('type', 'branch')
                        ->when(!empty($branchIds), fn($q3) => $q3->whereIn('branch_id', $branchIds));
                })->orWhere('type', 'company');
            })
            ->get(['id', 'type', 'user_id', 'branch_id', 'year', 'month', 'target_amount']);
    }

    private function calcAchievement(float $actual, ?float $target): ?float
    {
        if ($target === null || $target === 0.0) {
            return null;
        }
        return round(($actual / $target) * 100, 2);
    }

    private function resolveStatus(float $actual, ?float $target): string
    {
        $pct = $this->calcAchievement($actual, $target);

        if ($pct === null)
            return 'no_target';
        if ($pct >= 100)
            return 'achieved';
        if ($pct >= 80)
            return 'on_track';
        return 'under_target';
    }

    private function appendRanking(array $kpiRows): array
    {
        $byMonth = [];
        foreach ($kpiRows as $i => $_) {
            $byMonth[$kpiRows[$i]['month']][] = &$kpiRows[$i];
        }

        foreach ($byMonth as &$rows) {
            usort($rows, function ($a, $b) {
                $achA = $a['achievement_personal'] ?? $a['achievement_branch'] ?? $a['achievement_company'] ?? -1;
                $achB = $b['achievement_personal'] ?? $b['achievement_branch'] ?? $b['achievement_company'] ?? -1;
                return $achB <=> $achA;
            });
            foreach ($rows as $rank => &$row) {
                $row['rank'] = $rank + 1;
            }
        }

        usort(
            $kpiRows,
            fn($a, $b) =>
            $a['user_id'] <=> $b['user_id'] ?: $a['month'] <=> $b['month']
        );

        return $kpiRows;
    }

    private function buildKpiSummary(array $kpiRows): array
    {
        if (empty($kpiRows)) {
            return $this->buildEmptyKpiResponse()['summary'];
        }

        $totalActual = array_sum(array_column($kpiRows, 'actual_revenue'));
        $totalTargetP = array_sum(array_filter(array_column($kpiRows, 'target_personal')));
        $achievementsP = array_filter(array_column($kpiRows, 'achievement_personal'));

        $statusCounts = array_count_values(array_column($kpiRows, 'status'));

        return [
            'total_actual' => $totalActual,
            'total_actual_formatted' => $this->formatRupiah($totalActual),
            'total_target_personal' => $totalTargetP,
            'total_target_formatted' => $this->formatRupiah($totalTargetP),
            'avg_achievement_personal' => count($achievementsP) > 0
                ? round(array_sum($achievementsP) / count($achievementsP), 2)
                : null,
            'status_breakdown' => [
                'achieved' => $statusCounts['achieved'] ?? 0,
                'on_track' => $statusCounts['on_track'] ?? 0,
                'under_target' => $statusCounts['under_target'] ?? 0,
                'no_target' => $statusCounts['no_target'] ?? 0,
            ],
        ];
    }

    private function buildEmptyKpiResponse(): array
    {
        return [
            'data' => [],
            'summary' => [
                'total_actual' => 0,
                'total_actual_formatted' => 'Rp 0',
                'total_target_personal' => 0,
                'total_target_formatted' => 'Rp 0',
                'avg_achievement_personal' => null,
                'status_breakdown' => [
                    'achieved' => 0,
                    'on_track' => 0,
                    'under_target' => 0,
                    'no_target' => 0,
                ],
            ],
        ];
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}