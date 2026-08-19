<?php

namespace App\Services\Report;

use Illuminate\Support\Facades\DB;

trait ReportHelperTrait
{
    /**
     * Ambil daftar sales KECUALI cais_role_id = 30 (existing behaviour).
     */
    private function getSalesNames(?int $branchId = null)
    {
        $userIds = DB::table('m_tim_sales_d')
            ->where('user_id', '!=', 96986)
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        $query = DB::connection('mysqlhris')
            ->table('m_user as u')
            ->where('u.cais_role_id', '!=', 30)
            ->where('u.is_active', 1)
            ->leftJoin('m_branch as b', 'b.id', '=', 'u.branch_id')
            ->whereIn('u.id', $userIds)
            ->select('u.full_name as nama_sales', 'b.name as cabang', 'u.id as user_id')
            ->groupBy('u.full_name', 'b.name', 'u.id');

        if ($branchId) {
            $query->where('u.branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * Ambil daftar user dengan cais_role_id = 30 (untuk endpoint baru).
     */
    private function getSalesNamesRole30(?int $branchId = null)
    {
        $query = DB::connection('mysqlhris')
            ->table('m_user as u')
            ->where('u.cais_role_id', '=', 30)
            ->where('u.is_active', 1)
            ->whereNotIn('u.id', [101182, 123994])
            ->leftJoin('m_branch as b', 'b.id', '=', 'u.branch_id')
            ->select('u.full_name as nama_sales', 'b.name as cabang', 'u.id as user_id')
            ->groupBy('u.full_name', 'b.name', 'u.id');

        if ($branchId) {
            $query->where('u.branch_id', $branchId);
        }

        return $query->get();
    }

    private function calcPercentage(int $numerator, int $denominator): string
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1).'%' : '0%';
    }
}
