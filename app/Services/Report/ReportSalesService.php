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
        ).' - '.$year;

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
                'periode' => strtoupper(Carbon::create()->month($month)->locale('id')->monthName).' - '.$year,
                'data' => [],
            ];
        }

        $userIds = $salesData->pluck('user_id')->filter()->values()->toArray();

        $weeklyActivity = DB::table('sl_activity_sales as sa')
            ->leftJoin('sl_quotation as q', 'q.id', '=', 'sa.quotation_id')
            ->select(
                DB::raw('ANY_VALUE(sa.created_by) as created_by'),
                'sa.created_by_user_id',
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'Visit' AND DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_visit"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'Quotation' AND q.tipe_quotation = 'baru' AND DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_quot"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'SPK' AND DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_spk"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'PKS' AND DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_pks"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'Visit' AND DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_visit"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'Quotation' AND q.tipe_quotation = 'baru' AND DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_quot"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'SPK' AND DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_spk"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'PKS' AND DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_pks"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'Visit' AND DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_visit"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'Quotation' AND q.tipe_quotation = 'baru' AND DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_quot"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'SPK' AND DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_spk"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'PKS' AND DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_pks"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'Visit' AND DAY(sa.tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_visit"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'Quotation' AND q.tipe_quotation = 'baru' AND DAY(sa.tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_quot"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'SPK' AND DAY(sa.tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_spk"),
                DB::raw("SUM(CASE WHEN sa.jenis_activity = 'PKS' AND DAY(sa.tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_pks")
            )
            ->whereBetween('sa.tgl_activity', [$startMonth, $endMonth])
            ->whereIn('sa.created_by_user_id', $userIds)
            ->groupBy('sa.created_by_user_id')
            ->get();

        $weeklyApptDirect = DB::table('sl_activity_sales as sa')
            ->select(
                'sa.created_by_user_id as user_id',
                DB::raw('SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_appt'),
                DB::raw('SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_appt'),
                DB::raw('SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_appt'),
                DB::raw('SUM(CASE WHEN DAY(sa.tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_appt')
            )
            ->whereBetween('sa.tgl_activity', [$startMonth, $endMonth])
            ->whereIn('sa.created_by_user_id', $userIds)
            ->where('sa.jenis_activity', 'Appointment')
            ->groupBy('sa.created_by_user_id')
            ->get()
            ->keyBy('user_id');

        $weeklyApptDelegated = DB::table('sl_activity_sales as sa')
            ->join('sl_leads_kebutuhan as lk', function ($join) {
                $join->on('lk.leads_id', '=', 'sa.leads_id')
                    ->whereNull('lk.deleted_at');
            })
            ->join('m_tim_sales_d as tsd', function ($join) {
                $join->on('tsd.id', '=', 'lk.tim_sales_d_id')
                    ->whereNull('tsd.deleted_at');
            })
            ->select(
                'tsd.user_id',
                DB::raw('SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as w1_appt'),
                DB::raw('SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as w2_appt'),
                DB::raw('SUM(CASE WHEN DAY(sa.tgl_activity) BETWEEN 15 AND 21 THEN 1 ELSE 0 END) as w3_appt'),
                DB::raw('SUM(CASE WHEN DAY(sa.tgl_activity) >= 22 THEN 1 ELSE 0 END) as w4_appt')
            )
            ->whereBetween('sa.tgl_activity', [$startMonth, $endMonth])
            ->where('sa.jenis_activity', 'Appointment')
            ->whereIn('tsd.user_id', $userIds)
            ->whereNotIn('sa.created_by_user_id', $userIds)
            ->groupBy('tsd.user_id')
            ->get()
            ->keyBy('user_id');

        $data = [];
        $no = 1;
        foreach ($salesData as $sales) {
            $nama = $sales->nama_sales;
            $uid = $sales->user_id;
            $act = $weeklyActivity->firstWhere('created_by_user_id', $uid);
            $apptDirect = $weeklyApptDirect->get($uid);
            $apptDelegated = $weeklyApptDelegated->get($uid);

            $w1 = ['appt' => 0, 'visit' => 0, 'quot' => 0, 'spk' => 0, 'pks' => 0];
            $w2 = ['appt' => 0, 'visit' => 0, 'quot' => 0, 'spk' => 0, 'pks' => 0];
            $w3 = ['appt' => 0, 'visit' => 0, 'quot' => 0, 'spk' => 0, 'pks' => 0];
            $w4 = ['appt' => 0, 'visit' => 0, 'quot' => 0, 'spk' => 0, 'pks' => 0];

            if ($act) {
                $w1['visit'] = (int) $act->w1_visit;
                $w1['quot'] = (int) $act->w1_quot;
                $w1['spk'] = (int) $act->w1_spk;
                $w1['pks'] = (int) $act->w1_pks;
                $w2['visit'] = (int) $act->w2_visit;
                $w2['quot'] = (int) $act->w2_quot;
                $w2['spk'] = (int) $act->w2_spk;
                $w2['pks'] = (int) $act->w2_pks;
                $w3['visit'] = (int) $act->w3_visit;
                $w3['quot'] = (int) $act->w3_quot;
                $w3['spk'] = (int) $act->w3_spk;
                $w3['pks'] = (int) $act->w3_pks;
                $w4['visit'] = (int) $act->w4_visit;
                $w4['quot'] = (int) $act->w4_quot;
                $w4['spk'] = (int) $act->w4_spk;
                $w4['pks'] = (int) $act->w4_pks;
            }

            if ($apptDirect) {
                $w1['appt'] += (int) $apptDirect->w1_appt;
                $w2['appt'] += (int) $apptDirect->w2_appt;
                $w3['appt'] += (int) $apptDirect->w3_appt;
                $w4['appt'] += (int) $apptDirect->w4_appt;
            }
            if ($apptDelegated) {
                $w1['appt'] += (int) $apptDelegated->w1_appt;
                $w2['appt'] += (int) $apptDelegated->w2_appt;
                $w3['appt'] += (int) $apptDelegated->w3_appt;
                $w4['appt'] += (int) $apptDelegated->w4_appt;
            }

            $data[] = [
                'no' => $no++,
                'nama_sales' => $nama,
                'user_id' => $uid ?? null,
                'cabang' => $sales->cabang,
                'w1' => $w1, 'w2' => $w2, 'w3' => $w3, 'w4' => $w4,
            ];
        }

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ).' - '.$year;

        return [
            'periode' => $periode,
            'data' => $data,
            'count' => count($data),
        ];
    }

    private function getMonthlyAggregation($start, $end, array $userIds)
    {
        $base = DB::table('sl_activity_sales as sa')
            ->leftJoin('sl_quotation as q', 'q.id', '=', 'sa.quotation_id')
            ->select(
                DB::raw('ANY_VALUE(sa.created_by) as created_by'),
                'sa.created_by_user_id',
                DB::raw("COUNT(CASE WHEN sa.jenis_activity IN ('Kirim Berkas', 'Email') THEN 1 END) as jumlah_kirim_proposal"),
                DB::raw("COUNT(CASE WHEN sa.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon') THEN 1 END) as jumlah_visit"),
                DB::raw("COUNT(CASE WHEN sa.jenis_activity = 'Quotation' AND q.tipe_quotation = 'baru' THEN 1 END) as jumlah_quotation"),
                DB::raw("COUNT(CASE WHEN sa.jenis_activity = 'SPK' THEN 1 END) as jumlah_spk"),
                DB::raw("COUNT(CASE WHEN sa.jenis_activity = 'PKS' THEN 1 END) as jumlah_pks"),
                DB::raw("COUNT(CASE WHEN sa.jenis_activity = 'Follow Up' THEN 1 END) as jumlah_follow_up")
            )
            ->whereBetween('sa.tgl_activity', [$start, $end])
            ->whereIn('sa.created_by_user_id', $userIds)
            ->groupBy('sa.created_by_user_id')
            ->get()
            ->keyBy('created_by_user_id');

        $appointments = $this->getAppointmentCounts($start, $end, $userIds);

        return collect($userIds)->unique()->map(function ($uid) use ($base, $appointments) {
            $b = $base->get($uid);

            return (object) [
                'created_by' => $b->created_by ?? null,
                'created_by_user_id' => $uid,
                'jumlah_kirim_proposal' => (int) ($b->jumlah_kirim_proposal ?? 0),
                'jumlah_appointment' => (int) ($appointments->get($uid, 0)),
                'jumlah_visit' => (int) ($b->jumlah_visit ?? 0),
                'jumlah_quotation' => (int) ($b->jumlah_quotation ?? 0),
                'jumlah_spk' => (int) ($b->jumlah_spk ?? 0),
                'jumlah_pks' => (int) ($b->jumlah_pks ?? 0),
                'jumlah_follow_up' => (int) ($b->jumlah_follow_up ?? 0),
            ];
        })->values();
    }

    private function getAppointmentCounts($start, $end, array $userIds): \Illuminate\Support\Collection
    {
        $direct = DB::table('sl_activity_sales as sa')
            ->select('sa.created_by_user_id as user_id', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('sa.tgl_activity', [$start, $end])
            ->where('sa.jenis_activity', 'Appointment')
            ->whereIn('sa.created_by_user_id', $userIds)
            ->groupBy('sa.created_by_user_id')
            ->get();

        $delegated = DB::table('sl_activity_sales as sa')
            ->join('sl_leads_kebutuhan as lk', function ($join) {
                $join->on('lk.leads_id', '=', 'sa.leads_id')
                    ->whereNull('lk.deleted_at');
            })
            ->join('m_tim_sales_d as tsd', function ($join) {
                $join->on('tsd.id', '=', 'lk.tim_sales_d_id')
                    ->whereNull('tsd.deleted_at');
            })
            ->select('tsd.user_id', DB::raw('COUNT(*) as cnt'))
            ->whereBetween('sa.tgl_activity', [$start, $end])
            ->where('sa.jenis_activity', 'Appointment')
            ->whereIn('tsd.user_id', $userIds)
            ->whereNotIn('sa.created_by_user_id', $userIds)
            ->groupBy('tsd.user_id')
            ->get();

        $result = collect();
        foreach ($direct as $row) {
            $result->put($row->user_id, (int) $row->cnt);
        }
        foreach ($delegated as $row) {
            $existing = $result->get($row->user_id, 0);
            $result->put($row->user_id, $existing + (int) $row->cnt);
        }

        return $result;
    }

    private function formatMonthlyCounts($record): array
    {
        if (! $record) {
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
