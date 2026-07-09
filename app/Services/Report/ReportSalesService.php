<?php

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportSalesService
{
    use ReportHelperTrait;

    /**
     * Laporan aktivitas bulanan (regular sales, cais_role_id != 30).
     *
     * @return array{periode: string, data: array, count: int}
     */
    public function monthly(int $month, int $year, ?int $branchId): array
    {
        $startThisMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endThisMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        $salesData = $this->getSalesNames($branchId);

        if ($salesData->isEmpty()) {
            return [
                'periode' => sprintf('%02d-%d', $month, $year),
                'data' => [],
            ];
        }

        $userIds = $salesData->pluck('user_id')->filter()->values()->toArray();
        $aggThisMonth = $this->getMonthlyAggregation($startThisMonth, $endThisMonth, $userIds);

        $data = [];
        $no = 1;
        foreach ($salesData as $sales) {
            $actThis = $aggThisMonth->firstWhere('created_by_user_id', $sales->user_id);

            $thisMonthData = $this->formatMonthlyCounts($actThis);
            $thisMonthData = $this->attachPercentages($thisMonthData);

            $data[] = [
                'no' => $no++,
                'user_id' => $sales->user_id ?? null,
                'nama_sales' => $sales->nama_sales,
                'cabang' => $sales->cabang,
                'aggregat' => $thisMonthData,
            ];
        }

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)
                ->locale('id')
                ->monthName
        ) . ' - ' . $year;

        return [
            'periode' => $periode,
            'data' => $data,
            'count' => count($data),
        ];
    }

    /**
     * Laporan aktivitas mingguan (regular sales, cais_role_id != 30).
     *
     * @return array{periode: string, data: array, count: int}
     */
    public function weekly(int $month, int $year, ?int $branchId): array
    {
        $startMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endMonth = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $salesData = $this->getSalesNames($branchId);

        if ($salesData->isEmpty()) {
            return [
                'periode' => strtoupper(Carbon::create()->month($month)->locale('id')->monthName) . ' - ' . $year,
                'data' => [],
            ];
        }

        $userIds = $salesData->pluck('user_id')->filter()->values()->toArray();

        $weeklyActivity = DB::table('sl_activity_sales')
            ->select(
                DB::raw('ANY_VALUE(created_by) as created_by'),
                'created_by_user_id',
                DB::raw("SUM(CASE WHEN jenis_activity = 'Appointment' AND DAY(tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_appt"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Visit' AND DAY(tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_visit"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Quotation' AND DAY(tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_quot"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'SPK' AND DAY(tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_spk"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'PKS' AND DAY(tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_pks"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Appointment' AND DAY(tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_appt"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Visit' AND DAY(tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_visit"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Quotation' AND DAY(tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_quot"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'SPK' AND DAY(tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_spk"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'PKS' AND DAY(tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_pks"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Appointment' AND DAY(tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_appt"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Visit' AND DAY(tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_visit"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Quotation' AND DAY(tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_quot"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'SPK' AND DAY(tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_spk"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'PKS' AND DAY(tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_pks"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Appointment' AND DAY(tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_appt"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Visit' AND DAY(tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_visit"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'Quotation' AND DAY(tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_quot"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'SPK' AND DAY(tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_spk"),
                DB::raw("SUM(CASE WHEN jenis_activity = 'PKS' AND DAY(tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_pks")
            )
            ->whereBetween('tgl_activity', [$startMonth, $endMonth])
            ->whereIn('created_by_user_id', $userIds)
            ->groupBy('created_by_user_id')
            ->get();

        $data = [];
        $no = 1;
        foreach ($salesData as $sales) {
            $nama = $sales->nama_sales;
            $act = $weeklyActivity->firstWhere('created_by_user_id', $sales->user_id);

            $w1 = ['appt' => 0, 'visit' => 0, 'quot' => 0, 'spk' => 0, 'pks' => 0];
            $w2 = ['appt' => 0, 'visit' => 0, 'quot' => 0, 'spk' => 0, 'pks' => 0];
            $w3 = ['appt' => 0, 'visit' => 0, 'quot' => 0, 'spk' => 0, 'pks' => 0];
            $w4 = ['appt' => 0, 'visit' => 0, 'quot' => 0, 'spk' => 0, 'pks' => 0];

            if ($act) {
                $w1 = ['appt' => (int) $act->w1_appt, 'visit' => (int) $act->w1_visit, 'quot' => (int) $act->w1_quot, 'spk' => (int) $act->w1_spk, 'pks' => (int) $act->w1_pks];
                $w2 = ['appt' => (int) $act->w2_appt, 'visit' => (int) $act->w2_visit, 'quot' => (int) $act->w2_quot, 'spk' => (int) $act->w2_spk, 'pks' => (int) $act->w2_pks];
                $w3 = ['appt' => (int) $act->w3_appt, 'visit' => (int) $act->w3_visit, 'quot' => (int) $act->w3_quot, 'spk' => (int) $act->w3_spk, 'pks' => (int) $act->w3_pks];
                $w4 = ['appt' => (int) $act->w4_appt, 'visit' => (int) $act->w4_visit, 'quot' => (int) $act->w4_quot, 'spk' => (int) $act->w4_spk, 'pks' => (int) $act->w4_pks];
            }

            $data[] = [
                'no' => $no++,
                'nama_sales' => $nama,
                'user_id' => $sales->user_id ?? null,
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

    private function getMonthlyAggregation($start, $end, array $userIds)
    {
        return DB::table('sl_activity_sales')
            ->select(
                DB::raw('ANY_VALUE(created_by) as created_by'),
                'created_by_user_id',
                DB::raw("COUNT(CASE WHEN jenis_activity IN ('Kirim Berkas', 'Email') THEN 1 END) as jumlah_kirim_proposal"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'Appointment' THEN 1 END) as jumlah_appointment"),
                DB::raw("COUNT(CASE WHEN jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon') THEN 1 END) as jumlah_visit"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'Quotation' THEN 1 END) as jumlah_quotation"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'SPK' THEN 1 END) as jumlah_spk"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'PKS' THEN 1 END) as jumlah_pks"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'Follow Up' THEN 1 END) as jumlah_follow_up")
            )
            ->whereBetween('tgl_activity', [$start, $end])
            ->whereIn('created_by_user_id', $userIds)
            ->groupBy('created_by_user_id')
            ->get();
    }

    private function formatMonthlyCounts($record): array
    {
        if (!$record) {
            return [
                'jumlah_kirim_proposal' => 0,
                'jumlah_appointment' => 0,
                'jumlah_visit' => 0,
                'jumlah_quotation' => 0,
                'jumlah_spk' => 0,
                'jumlah_pks' => 0,
                'jumlah_follow_up' => 0,
                'jumlah_aktual_penempatan' => 0,
            ];
        }

        return [
            'jumlah_kirim_proposal' => (int) $record->jumlah_kirim_proposal,
            'jumlah_appointment' => (int) $record->jumlah_appointment,
            'jumlah_visit' => (int) $record->jumlah_visit,
            'jumlah_quotation' => (int) $record->jumlah_quotation,
            'jumlah_spk' => (int) $record->jumlah_spk,
            'jumlah_pks' => (int) $record->jumlah_pks,
            'jumlah_follow_up' => (int) $record->jumlah_follow_up,
            'jumlah_aktual_penempatan' => (int) $record->jumlah_pks,
        ];
    }

    private function attachPercentages(array $counts): array
    {
        $counts['pct_proposal_to_appt'] = $this->calcPercentage($counts['jumlah_appointment'], $counts['jumlah_kirim_proposal']);
        $counts['pct_appt_to_visit'] = $this->calcPercentage($counts['jumlah_visit'], $counts['jumlah_appointment']);
        $counts['pct_visit_to_quot'] = $this->calcPercentage($counts['jumlah_quotation'], $counts['jumlah_visit']);
        $counts['pct_quot_to_spk'] = $this->calcPercentage($counts['jumlah_spk'], $counts['jumlah_quotation']);
        $counts['pct_spk_to_pks'] = $this->calcPercentage($counts['jumlah_pks'], $counts['jumlah_spk']);
        return $counts;
    }
}
