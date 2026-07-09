<?php

namespace App\Services\SalesRevenue;

use App\Models\Pks;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesRevenueQueryService
{
    public function fetchSalesUsers(array $filters): Collection
    {
        return User::whereIn('cais_role_id', [29, 30, 31, 32, 33])
            ->where('id', '!=', 96986)
            ->when(isset($filters['user_id']), fn($q) => $q->where('id', $filters['user_id']))
            ->when(isset($filters['branch_id']), fn($q) => $q->where('branch_id', $filters['branch_id']))
            ->get(['id', 'full_name', 'role_id', 'branch_id']);
    }

    public function getAllPksBySalesUsers(array $userIds, array $filters = []): Collection
    {
        $linkedPksIds = DB::table('sl_pks as p')
            ->select('p.id as pks_id', 'tsd.user_id as sales_user_id')
            ->join('sl_leads as l', 'p.leads_id', '=', 'l.id')
            ->join('m_tim_sales_d as tsd', 'l.tim_sales_d_id', '=', 'tsd.id')
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
            ->when(
                isset($filters['start_date']),
                fn($q) => $q->where('p.kontrak_akhir', '>=', $filters['start_date'])
            )
            ->when(
                isset($filters['end_date']),
                fn($q) => $q->where('p.kontrak_awal', '<=', $filters['end_date'])
            )
            ->get()
            ->pluck('sales_user_id', 'pks_id');

        if ($linkedPksIds->isEmpty()) {
            return collect();
        }

        $pksIds = $linkedPksIds->keys()->all();

        $pksList = Pks::whereIn('id', $pksIds)
            ->select(['id', 'leads_id', 'quotation_id', 'kontrak_awal', 'kontrak_akhir'])
            ->get();

        return $pksList->each(function ($pks) use ($linkedPksIds) {
            $pks->_sales_user_id = $linkedPksIds->get($pks->id);
        });
    }

    public function getBulkInvoiceTotals(Collection $allPks): array
    {
        $directQuotationIds = $allPks->pluck('quotation_id')->filter()->unique()->values()->all();
        $leadsIds = $allPks->pluck('leads_id')->filter()->unique()->values()->all();

        $quotationsByLeads = DB::table('sl_quotation')
            ->whereIn('leads_id', $leadsIds)
            ->whereNull('deleted_at')
            ->select('id', 'leads_id')
            ->get()
            ->groupBy('leads_id')
            ->map(fn($q) => $q->pluck('id'));

        $allQuotationIds = collect($directQuotationIds)
            ->merge($quotationsByLeads->flatten())
            ->unique()
            ->values()
            ->all();

        if (empty($allQuotationIds)) {
            return [];
        }

        $invoiceByQuotation = DB::table('sl_quotation_detail_coss')
            ->whereIn('quotation_id', $allQuotationIds)
            ->whereNull('deleted_at')
            ->groupBy('quotation_id')
            ->pluck(DB::raw('SUM(total_invoice)'), 'quotation_id')
            ->toArray();

        $invoiceByPks = [];

        foreach ($allPks as $pks) {
            $total = 0.0;

            if ($pks->quotation_id && isset($invoiceByQuotation[$pks->quotation_id])) {
                $total += (float) $invoiceByQuotation[$pks->quotation_id];
            }

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

    public function fetchAllTargets(
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
}
