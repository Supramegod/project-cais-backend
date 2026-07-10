<?php

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportRole30Service
{
    use ReportHelperTrait;

    /**
     * Laporan aktivitas bulanan khusus cais_role_id = 30 (telesales).
     *
     * @return array{periode: string, data: array, count: int}
     */
    public function monthlyRole30(int $month, int $year, ?int $branchId): array
    {
        $startMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        $salesData = $this->getSalesNamesRole30($branchId);

        if ($salesData->isEmpty()) {
            return [
                'periode' => strtoupper(Carbon::createFromDate($year, $month, 1)->locale('id')->monthName) . ' - ' . $year,
                'data' => [],
                'count' => 0,
            ];
        }

        $userIds = $salesData->pluck('user_id')->toArray();
        $aggData = $this->getRole30MonthlyAggregation($startMonth, $endMonth, $userIds);

        $data = [];
        $no = 1;
        foreach ($salesData as $sales) {
            $nama = $sales->nama_sales;
            $agg = $aggData->firstWhere('user_id', $sales->user_id);

            $jumlahLeads = $agg ? (int) $agg->jumlah_leads : 0;
            $jumlahAssignment = $agg ? (int) $agg->jumlah_assignment : 0;
            $jumlahAppointment = $agg ? (int) $agg->jumlah_appointment : 0;

            $data[] = [
                'no' => $no++,
                'user_id' => $sales->user_id ?? null,
                'nama_sales' => $nama,
                'cabang' => $sales->cabang,
                'aggregat' => [
                    'jumlah_leads' => $jumlahLeads,
                    'jumlah_appointment' => $jumlahAppointment,
                    'jumlah_assignment' => $jumlahAssignment,
                    'pct_leads_to_assignment' => $this->calcPercentage($jumlahAssignment, $jumlahLeads),
                    'pct_assignment_to_appointment' => $this->calcPercentage($jumlahAppointment, $jumlahAssignment),
                    'pct_leads_to_appointment' => $this->calcPercentage($jumlahAppointment, $jumlahLeads),
                ],
            ];
        }

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ) . ' - ' . $year;

        return [
            'periode' => $periode,
            'data' => $data,
            'count' => count($data),
        ];
    }

    /**
     * Laporan aktivitas mingguan khusus cais_role_id = 30 (telesales).
     *
     * @return array{periode: string, data: array, count: int}
     */
    public function weeklyRole30(int $month, int $year, ?int $branchId): array
    {
        $startMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endMonth = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $salesData = $this->getSalesNamesRole30($branchId);

        if ($salesData->isEmpty()) {
            return [
                'periode' => strtoupper(Carbon::create()->month($month)->locale('id')->monthName) . ' - ' . $year,
                'data' => [],
                'count' => 0,
            ];
        }

        $userIds = $salesData->pluck('user_id')->filter()->values()->toArray();

        $weeklyActivity = DB::table('sl_customer_activity as sa')
            ->select(
                DB::raw('ANY_VALUE(sa.created_by) as created_by'),
                'sa.user_id',
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Leads' AND DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN sa.leads_id END) as w1_leads"),
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Assignment' AND DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN sa.leads_id END) as w1_assignment"),
                DB::raw("SUM(CASE WHEN sa.tipe = 'Appointment' AND DAY(sa.tgl_activity) BETWEEN 1 AND 7 AND EXISTS (SELECT 1 FROM sl_customer_activity sa2 WHERE sa2.leads_id = sa.leads_id AND sa2.tipe = 'Assignment' AND sa2.tgl_activity <= sa.tgl_activity) THEN 1 ELSE 0 END) as w1_appt"),
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Leads' AND DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN sa.leads_id END) as w2_leads"),
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Assignment' AND DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN sa.leads_id END) as w2_assignment"),
                DB::raw("SUM(CASE WHEN sa.tipe = 'Appointment' AND DAY(sa.tgl_activity) BETWEEN 8 AND 14 AND EXISTS (SELECT 1 FROM sl_customer_activity sa2 WHERE sa2.leads_id = sa.leads_id AND sa2.tipe = 'Assignment' AND sa2.tgl_activity <= sa.tgl_activity) THEN 1 ELSE 0 END) as w2_appt"),
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Leads' AND DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN sa.leads_id END) as w3_leads"),
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Assignment' AND DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN sa.leads_id END) as w3_assignment"),
                DB::raw("SUM(CASE WHEN sa.tipe = 'Appointment' AND DAY(sa.tgl_activity) BETWEEN 15 AND 21 AND EXISTS (SELECT 1 FROM sl_customer_activity sa2 WHERE sa2.leads_id = sa.leads_id AND sa2.tipe = 'Assignment' AND sa2.tgl_activity <= sa.tgl_activity) THEN 1 ELSE 0 END) as w3_appt"),
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Leads' AND DAY(sa.tgl_activity) >= 22 THEN sa.leads_id END) as w4_leads"),
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Assignment' AND DAY(sa.tgl_activity) >= 22 THEN sa.leads_id END) as w4_assignment"),
                DB::raw("SUM(CASE WHEN sa.tipe = 'Appointment' AND DAY(sa.tgl_activity) >= 22 AND EXISTS (SELECT 1 FROM sl_customer_activity sa2 WHERE sa2.leads_id = sa.leads_id AND sa2.tipe = 'Assignment' AND sa2.tgl_activity <= sa.tgl_activity) THEN 1 ELSE 0 END) as w4_appt")
            )
            ->whereBetween('sa.tgl_activity', [$startMonth, $endMonth])
            ->whereIn('sa.user_id', $userIds)
            ->whereIn('sa.tipe', ['Leads', 'Assignment', 'Appointment'])
            ->groupBy('sa.user_id')
            ->get();

        $apptWeekly = DB::table('sl_activity_sales as sa')
            ->select(
                'sa.created_by_user_id as user_id',
                DB::raw("SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_appt"),
                DB::raw("SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_appt"),
                DB::raw("SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_appt"),
                DB::raw("SUM(CASE WHEN DAY(sa.tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_appt")
            )
            ->whereBetween('sa.tgl_activity', [$startMonth, $endMonth])
            ->whereIn('sa.created_by_user_id', $userIds)
            ->where('sa.jenis_activity', 'Appointment')
            ->groupBy('sa.created_by_user_id')
            ->get()
            ->keyBy('user_id');

        $data = [];
        $no = 1;
        foreach ($salesData as $sales) {
            $nama = $sales->nama_sales;
            $act = $weeklyActivity->firstWhere('user_id', $sales->user_id);
            $appt = $apptWeekly->get($sales->user_id);

            $w1 = [
                'leads' => (int) ($act->w1_leads ?? 0),
                'appt' => (int) ($appt->w1_appt ?? 0),
                'assignment' => (int) ($act->w1_assignment ?? 0),
            ];
            $w2 = [
                'leads' => (int) ($act->w2_leads ?? 0),
                'appt' => (int) ($appt->w2_appt ?? 0),
                'assignment' => (int) ($act->w2_assignment ?? 0),
            ];
            $w3 = [
                'leads' => (int) ($act->w3_leads ?? 0),
                'appt' => (int) ($appt->w3_appt ?? 0),
                'assignment' => (int) ($act->w3_assignment ?? 0),
            ];
            $w4 = [
                'leads' => (int) ($act->w4_leads ?? 0),
                'appt' => (int) ($appt->w4_appt ?? 0),
                'assignment' => (int) ($act->w4_assignment ?? 0),
            ];

            $data[] = [
                'no' => $no++,
                'user_id' => $sales->user_id ?? null,
                'nama_sales' => $nama,
                'cabang' => $sales->cabang,
                'w1' => $w1, 'w2' => $w2, 'w3' => $w3, 'w4' => $w4,
            ];
        }

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ) . ' - ' . $year;

        return [
            'periode' => $periode,
            'data' => $data,
            'count' => count($data),
        ];
    }

    /**
     * mentah
     */
    private function getRole30MonthlyAggregation($start, $end, array $userIds)
    {
        $base = DB::table('sl_customer_activity as sa')
            ->select(
                'sa.user_id',
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Leads' THEN sa.leads_id END) as jumlah_leads"),
                DB::raw("COUNT(DISTINCT CASE WHEN sa.tipe = 'Assignment' THEN sa.leads_id END) as jumlah_assignment")
            )
            ->whereBetween('sa.tgl_activity', [$start, $end])
            ->whereIn('sa.user_id', $userIds)
            ->whereIn('sa.tipe', ['Leads', 'Assignment'])
            ->groupBy('sa.user_id')
            ->get()
            ->keyBy('user_id');

        $appointment = DB::table('sl_activity_sales as sa')
            ->select(
                'sa.created_by_user_id as user_id',
                DB::raw("COUNT(*) as jumlah_appointment")
            )
            ->whereBetween('sa.tgl_activity', [$start, $end])
            ->whereIn('sa.created_by_user_id', $userIds)
            ->where('sa.jenis_activity', 'Appointment')
            ->groupBy('sa.created_by_user_id')
            ->get()
            ->keyBy('user_id');

        return collect($userIds)->unique()->map(function ($uid) use ($base, $appointment) {
            $b = $base->get($uid);
            $a = $appointment->get($uid);

            return (object) [
                'user_id' => $uid,
                'jumlah_leads' => (int) ($b->jumlah_leads ?? 0),
                'jumlah_assignment' => (int) ($b->jumlah_assignment ?? 0),
                'jumlah_appointment' => (int) ($a->jumlah_appointment ?? 0),
            ];
        })->values();
    }
}
