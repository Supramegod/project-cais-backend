<?php

namespace App\Services\SalesRevenue;

use App\Models\SalesTarget;

class SalesRevenueKpiService
{
    public function __construct(
        protected SalesRevenueCalculationService $calculationService,
        protected SalesRevenueQueryService $queryService
    ) {}

    /**
     * KPI Comparison: aktual vs target per user per bulan.
     */
    public function getKpiComparison(array $filters = []): array
    {
        $revenueRows = $this->calculationService->calculateMonthlyRevenue($filters);

        if (empty($revenueRows)) {
            return $this->buildEmptyKpiResponse();
        }

        $userIds = array_unique(array_column($revenueRows, 'user_id'));
        $branchIds = array_filter(array_unique(array_column($revenueRows, 'branch_id')));
        $months = array_unique(array_column($revenueRows, 'month'));
        $years = array_unique(array_map(fn($m) => (int) substr($m, 0, 4), $months));

        $allTargets = $this->queryService->fetchAllTargets($userIds, $branchIds, $years, $filters);

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
