<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Sales Report",
 *     description="API Endpoints untuk Dashboard Sales Report"
 * )
 */

class ReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/sales-report/monthly",
     *     summary="Laporan Aktivitas Sales Bulanan",
     *     description="Menampilkan laporan bulanan aktivitas sales per orang, membandingkan bulan ini vs bulan lalu...",
     *     tags={"Sales Report"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="month", in="query", required=true,
     *         description="Bulan laporan (1-12)",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="year", in="query", required=true,
     *         description="Tahun laporan (4 digit)",
     *         @OA\Schema(type="integer", example=2026)
     *     ),
     *     @OA\Parameter(
     *         name="branch_id", in="query", required=false,
     *         description="Filter berdasarkan ID cabang (opsional)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data laporan bulanan berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="periode", type="string", example="05-2026"),
     *             @OA\Property(
     *                 property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="no", type="integer", example=1),
     *                     @OA\Property(property="nama_sales", type="string", example="Nuryono Hariyadi"),
     *                     @OA\Property(property="cabang", type="string", example="Central 2"),
     *                     @OA\Property(
     *                         property="bulan_ini", type="object",
     *                         @OA\Property(property="jumlah_kirim_proposal", type="integer", example=12),
     *                         @OA\Property(property="jumlah_appointment", type="integer", example=6),
     *                         @OA\Property(property="jumlah_visit", type="integer", example=3),
     *                         @OA\Property(property="jumlah_quotation", type="integer", example=2),
     *                         @OA\Property(property="jumlah_spk", type="integer", example=1),
     *                         @OA\Property(property="jumlah_pks", type="integer", example=0),
     *                         @OA\Property(property="jumlah_follow_up", type="integer", example=25),
     *                         @OA\Property(property="jumlah_aktual_penempatan", type="integer", example=0),
     *                         @OA\Property(property="pct_proposal_to_appt", type="string", example="50.0%"),
     *                         @OA\Property(property="pct_appt_to_visit", type="string", example="50.0%"),
     *                         @OA\Property(property="pct_visit_to_quot", type="string", example="66.7%"),
     *                         @OA\Property(property="pct_quot_to_spk", type="string", example="50.0%"),
     *                         @OA\Property(property="pct_spk_to_pks", type="string", example="0%")
     *                     ),
     *                     @OA\Property(
     *                         property="bulan_lalu", type="object",
     *                         @OA\Property(property="jumlah_kirim_proposal", type="integer", example=8),
     *                         @OA\Property(property="jumlah_appointment", type="integer", example=4),
     *                         @OA\Property(property="jumlah_visit", type="integer", example=2),
     *                         @OA\Property(property="jumlah_quotation", type="integer", example=1),
     *                         @OA\Property(property="jumlah_spk", type="integer", example=0),
     *                         @OA\Property(property="jumlah_pks", type="integer", example=0),
     *                         @OA\Property(property="jumlah_follow_up", type="integer", example=20),
     *                         @OA\Property(property="jumlah_aktual_penempatan", type="integer", example=0),
     *                         @OA\Property(property="pct_proposal_to_appt", type="string", example="50.0%"),
     *                         @OA\Property(property="pct_appt_to_visit", type="string", example="50.0%"),
     *                         @OA\Property(property="pct_visit_to_quot", type="string", example="50.0%"),
     *                         @OA\Property(property="pct_quot_to_spk", type="string", example="0%"),
     *                         @OA\Property(property="pct_spk_to_pks", type="string", example="0%")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The month field is required.")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function monthly(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|digits:4',
            'branch_id' => 'nullable|integer|exists:mysqlhris.m_branch,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $month = (int) $request->month;
        $year = (int) $request->year;
        $branchId = $request->branch_id;

        $startThisMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endThisMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        // $prevMonth = $month == 1 ? 12 : $month - 1;
        // $prevYear = $month == 1 ? $year - 1 : $year;
        // $startPrevMonth = Carbon::createFromDate($prevYear, $prevMonth, 1)->startOfDay();
        // $endPrevMonth = Carbon::createFromDate($prevYear, $prevMonth, 1)->endOfMonth()->endOfDay();

        // Ambil daftar sales (nama & cabang) dari database HR, 
        // beserta daftar nama untuk filter aktivitas
        $salesData = $this->getSalesNames($branchId);

        if ($salesData->isEmpty()) {
            return response()->json([
                'periode' => sprintf('%02d-%d', $month, $year),
                'data' => [],
            ]);
        }

        // Ambil hanya daftar nama (string) untuk whereIn
        $salesNames = $salesData->pluck('nama_sales')->toArray();

        // Agregasi aktivitas dengan filter nama
        $aggThisMonth = $this->getMonthlyAggregation($startThisMonth, $endThisMonth, $salesNames);
        // $aggPrevMonth = $this->getMonthlyAggregation($startPrevMonth, $endPrevMonth, $salesNames);

        // Susun response
        $data = [];
        $no = 1;
        foreach ($salesData as $sales) {
            $nama = $sales->nama_sales;
            $actThis = $aggThisMonth->firstWhere('created_by', $nama);
            // $actPrev = $aggPrevMonth->firstWhere('created_by', $nama);

            $thisMonthData = $this->formatMonthlyCounts($actThis);
            // $prevMonthData = $this->formatMonthlyCounts($actPrev);

            $thisMonthData = $this->attachPercentages($thisMonthData);
            // $prevMonthData = $this->attachPercentages($prevMonthData);

            $data[] = [
                'no' => $no++,
                'nama_sales' => $nama,
                'cabang' => $sales->cabang,
                'aggregat' => $thisMonthData,
                // 'bulan_lalu' => $prevMonthData,
            ];
        }
        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)
                ->locale('id')
                ->monthName
        ) . ' - ' . $year;

        return response()->json([
            'periode' => $periode,
            'data' => $data,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/sales-report/weekly",
     *     summary="Laporan Aktivitas Sales Mingguan",
     *     description="Menampilkan laporan mingguan aktivitas sales per orang dalam satu bulan...",
     *     tags={"Sales Report"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="month", in="query", required=true,
     *         description="Bulan laporan (1-12)",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="year", in="query", required=true,
     *         description="Tahun laporan (4 digit)",
     *         @OA\Schema(type="integer", example=2026)
     *     ),
     *     @OA\Parameter(
     *         name="branch_id", in="query", required=false,
     *         description="Filter berdasarkan ID cabang (opsional)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data laporan mingguan berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="periode", type="string", example="MEI - 2026"),
     *             @OA\Property(
     *                 property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="no", type="integer", example=1),
     *                     @OA\Property(property="nama_sales", type="string", example="Nuryono Hariyadi"),
     *                     @OA\Property(property="cabang", type="string", example="Central 2"),
     *                     @OA\Property(
     *                         property="w1", type="object",
     *                         @OA\Property(property="appt", type="integer", example=2),
     *                         @OA\Property(property="visit", type="integer", example=1),
     *                         @OA\Property(property="quot", type="integer", example=1),
     *                         @OA\Property(property="spk", type="integer", example=0),
     *                         @OA\Property(property="pks", type="integer", example=0)
     *                     ),
     *                     @OA\Property(
     *                         property="w2", type="object",
     *                         @OA\Property(property="appt", type="integer", example=1),
     *                         @OA\Property(property="visit", type="integer", example=1),
     *                         @OA\Property(property="quot", type="integer", example=0),
     *                         @OA\Property(property="spk", type="integer", example=1),
     *                         @OA\Property(property="pks", type="integer", example=0)
     *                     ),
     *                     @OA\Property(
     *                         property="w3", type="object",
     *                         @OA\Property(property="appt", type="integer", example=2),
     *                         @OA\Property(property="visit", type="integer", example=1),
     *                         @OA\Property(property="quot", type="integer", example=1),
     *                         @OA\Property(property="spk", type="integer", example=0),
     *                         @OA\Property(property="pks", type="integer", example=0)
     *                     ),
     *                     @OA\Property(
     *                         property="w4", type="object",
     *                         @OA\Property(property="appt", type="integer", example=0),
     *                         @OA\Property(property="visit", type="integer", example=0),
     *                         @OA\Property(property="quot", type="integer", example=0),
     *                         @OA\Property(property="spk", type="integer", example=0),
     *                         @OA\Property(property="pks", type="integer", example=0)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The month field is required.")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function weekly(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|digits:4',
            'branch_id' => 'nullable|integer|exists:mysqlhris.m_branch,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $month = (int) $request->month;   // pastikan integer
        $year = (int) $request->year;
        $branchId = $request->branch_id;
        $startMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endMonth = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $salesData = $this->getSalesNames($branchId);

        if ($salesData->isEmpty()) {
            return response()->json([
                'periode' => strtoupper(Carbon::create()->month($month)->locale('id')->monthName) . ' - ' . $year,
                'data' => [],
            ]);
        }

        $salesNames = $salesData->pluck('nama_sales')->toArray();

        $weeklyActivity = DB::table('sl_activity_sales')
            ->select(
                'created_by',
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
            ->whereIn('created_by', $salesNames)
            ->groupBy('created_by')
            ->get();

        $data = [];
        $no = 1;
        foreach ($salesData as $sales) {
            $nama = $sales->nama_sales;
            $act = $weeklyActivity->firstWhere('created_by', $nama);

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
                'cabang' => $sales->cabang,
                'w1' => $w1,
                'w2' => $w2,
                'w3' => $w3,
                'w4' => $w4,
            ];

        }
        // Untuk output periode
        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)
                ->locale('id')
                ->monthName
        ) . ' - ' . $year;


        return response()->json([
            'periode' => $periode,
            'data' => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helper Methods (diperbarui)
    // ──────────────────────────────────────────────


    private function getSalesNames($branchId = null)
    {
        $userIds = DB::table('m_tim_sales_d')
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        // 2. Ambil full_name dan branch dari mysqlhris
        $query = DB::connection('mysqlhris')
            ->table('m_user as u')
            ->leftJoin('m_branch as b', 'b.id', '=', 'u.branch_id')
            ->whereIn('u.id', $userIds)
            // ->whereNull('u.deleted_at')
            ->select('u.full_name as nama_sales', 'b.name as cabang')
            ->groupBy('u.full_name', 'b.name');

        if ($branchId) {
            $query->where('u.branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * Agregasi bulanan - sekarang menerima array nama sales
     */
    private function getMonthlyAggregation($start, $end, array $salesNames)
    {
        return DB::table('sl_activity_sales')
            ->select(
                'created_by',
                DB::raw("COUNT(CASE WHEN jenis_activity IN ('Kirim Berkas', 'Email') THEN 1 END) as jumlah_kirim_proposal"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'Appointment' THEN 1 END) as jumlah_appointment"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'Visit' THEN 1 END) as jumlah_visit"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'Quotation' THEN 1 END) as jumlah_quotation"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'SPK' THEN 1 END) as jumlah_spk"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'PKS' THEN 1 END) as jumlah_pks"),
                DB::raw("COUNT(CASE WHEN jenis_activity = 'Follow Up' THEN 1 END) as jumlah_follow_up")
            )
            ->whereBetween('tgl_activity', [$start, $end])
            ->whereIn('created_by', $salesNames)
            ->groupBy('created_by')
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

    private function calcPercentage(int $numerator, int $denominator): string
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) . '%' : '0%';
    }
}
