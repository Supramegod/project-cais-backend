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

        $salesData = $this->getSalesNames($branchId);

        if ($salesData->isEmpty()) {
            return response()->json([
                'periode' => sprintf('%02d-%d', $month, $year),
                'data' => [],
            ]);
        }

        $salesNames = $salesData->pluck('nama_sales')->toArray();
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
        $count = count($data);

        return response()->json([
            'periode' => $periode,
            'data' => $data,
            'count' => $count,
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

        $month = (int) $request->month;
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
        $userIds = $salesData->pluck('user_id')->filter()->values()->toArray();

        //mentah
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

        // Alur: Kirim Proposal → Appointment → Visit → Quotation → SPK → PKS
        // Tiap step dicek EXISTS step sebelumnya pada leads_id yang sama

        // $weeklyActivity = DB::table('sl_activity_sales as sa')
        //     ->leftJoin('sl_leads as l', 'sa.leads_id', '=', 'l.id')
        //     ->select(
        //         'sa.created_by',

        //         // ── WEEK 1 (1-7) ──────────────────────────────────────────
        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Appointment'
        //          AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity IN ('Kirim Berkas','Email')
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w1_appt"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
        //          AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Appointment'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w1_visit"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Quotation'
        //          AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
        //          AND (
        //              EXISTS (
        //                  SELECT 1 FROM sl_activity_sales sa2
        //                  WHERE sa2.leads_id = sa.leads_id
        //                    AND sa2.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
        //                    AND sa2.tgl_activity <= sa.tgl_activity
        //              )
        //              OR l.status_leads_id = 102
        //          )
        //         THEN 1 ELSE 0 END) as w1_quot"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'SPK'
        //          AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Quotation'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w1_spk"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'PKS'
        //          AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'SPK'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w1_pks"),

        //         // ── WEEK 2 (8-14) ──────────────────────────────────────────
        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Appointment'
        //          AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity IN ('Kirim Berkas','Email')
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w2_appt"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
        //          AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Appointment'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w2_visit"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Quotation'
        //          AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
        //          AND (
        //              EXISTS (
        //                  SELECT 1 FROM sl_activity_sales sa2
        //                  WHERE sa2.leads_id = sa.leads_id
        //                    AND sa2.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
        //                    AND sa2.tgl_activity <= sa.tgl_activity
        //              )
        //              OR l.status_leads_id = 102
        //          )
        //         THEN 1 ELSE 0 END) as w2_quot"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'SPK'
        //          AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Quotation'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w2_spk"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'PKS'
        //          AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'SPK'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w2_pks"),

        //         // ── WEEK 3 (15-21) ─────────────────────────────────────────
        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Appointment'
        //          AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity IN ('Kirim Berkas','Email')
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w3_appt"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
        //          AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Appointment'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w3_visit"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Quotation'
        //          AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
        //          AND (
        //              EXISTS (
        //                  SELECT 1 FROM sl_activity_sales sa2
        //                  WHERE sa2.leads_id = sa.leads_id
        //                    AND sa2.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
        //                    AND sa2.tgl_activity <= sa.tgl_activity
        //              )
        //              OR l.status_leads_id = 102
        //          )
        //         THEN 1 ELSE 0 END) as w3_quot"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'SPK'
        //          AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Quotation'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w3_spk"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'PKS'
        //          AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'SPK'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w3_pks"),

        //         // ── WEEK 4 (22-akhir bulan) ─────────────────────────────────
        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Appointment'
        //          AND DAY(sa.tgl_activity) >= 22
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity IN ('Kirim Berkas','Email')
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w4_appt"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
        //          AND DAY(sa.tgl_activity) >= 22
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Appointment'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w4_visit"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Quotation'
        //          AND DAY(sa.tgl_activity) >= 22
        //          AND (
        //              EXISTS (
        //                  SELECT 1 FROM sl_activity_sales sa2
        //                  WHERE sa2.leads_id = sa.leads_id
        //                    AND sa2.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
        //                    AND sa2.tgl_activity <= sa.tgl_activity
        //              )
        //              OR l.status_leads_id = 102
        //          )
        //         THEN 1 ELSE 0 END) as w4_quot"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'SPK'
        //          AND DAY(sa.tgl_activity) >= 22
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Quotation'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w4_spk"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'PKS'
        //          AND DAY(sa.tgl_activity) >= 22
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'SPK'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w4_pks")
        //     )
        //     ->whereBetween('sa.tgl_activity', [$startMonth, $endMonth])
        //     ->whereIn('sa.created_by', $salesNames)
        //     ->whereIn('sa.jenis_activity', ['Kirim Berkas', 'Email', 'Appointment', 'Visit', 'Quotation', 'SPK', 'PKS'])
        //     ->groupBy('sa.created_by')
        //     ->get();

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
                'w1' => $w1,
                'w2' => $w2,
                'w3' => $w3,
                'w4' => $w4,
            ];
        }

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)
                ->locale('id')
                ->monthName
        ) . ' - ' . $year;
        $count = count($data);

        return response()->json([
            'periode' => $periode,
            'data' => $data,
            'count' => $count,
        ]);
    }

    // ============================================================
    //  ENDPOINT BARU: Monthly Report untuk cais_role_id = 30
    // ============================================================

    /**
     * @OA\Get(
     *     path="/api/sales-report/monthly/tele",
     *     summary="Laporan Aktivitas Bulanan - Role 30 (Leads → Assignment → Appointment)",
     *     description="Menampilkan laporan bulanan khusus user dengan cais_role_id=30.
     *     Hanya menghitung alur: Leads → Assignment → Appointment.
     *     Follow Up dan aktivitas lain TIDAK dihitung.",
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
     *         description="Data laporan bulanan role-30 berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="periode", type="string", example="MEI - 2026"),
     *             @OA\Property(property="count", type="integer", example=5),
     *             @OA\Property(
     *                 property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="no", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=101),
     *                     @OA\Property(property="nama_sales", type="string", example="Budi Santoso"),
     *                     @OA\Property(property="cabang", type="string", example="Central 1"),
     *                     @OA\Property(
     *                         property="aggregat", type="object",
     *                         @OA\Property(property="jumlah_leads", type="integer", example=10,
     *                             description="Jumlah leads yang sudah melalui proses assignment"),
     *                         @OA\Property(property="jumlah_assignment", type="integer", example=10,
     *                             description="Jumlah assignment (1 per leads yang di-assign)"),
     *                         @OA\Property(property="jumlah_appointment", type="integer", example=6,
     *                             description="Jumlah appointment yang terjadi setelah assignment"),
     *                         @OA\Property(property="pct_leads_to_assignment", type="string", example="100.0%",
     *                             description="Persentase leads yang berhasil di-assign"),
     *                         @OA\Property(property="pct_assignment_to_appointment", type="string", example="60.0%",
     *                             description="Persentase assignment yang berhasil jadi appointment")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function monthlyRole30(Request $request)
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

        $startMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        // Ambil daftar user cais_role_id = 30
        $salesData = $this->getSalesNamesRole30($branchId);

        if ($salesData->isEmpty()) {
            return response()->json([
                'periode' => strtoupper(Carbon::createFromDate($year, $month, 1)->locale('id')->monthName) . ' - ' . $year,
                'data' => [],
                'count' => 0,
            ]);
        }

        // Kumpulkan user_id (untuk join ke sl_leads dan filter sl_customer_activity)
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
                    // Berapa % dari leads yang berhasil di-assign
                    'pct_leads_to_assignment' => $this->calcPercentage($jumlahAssignment, $jumlahLeads),
                    // Berapa % dari assignment yang berhasil jadi appointment
                    'pct_assignment_to_appointment' => $this->calcPercentage($jumlahAppointment, $jumlahAssignment),
                    'pct_leads_to_appointment' => $this->calcPercentage($jumlahAppointment, $jumlahLeads),
                ],
            ];
        }

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ) . ' - ' . $year;

        return response()->json([
            'periode' => $periode,
            'data' => $data,
            'count' => count($data),
        ]);
    }

    // ============================================================
    //  ENDPOINT BARU: Weekly Report untuk cais_role_id = 30
    // ============================================================

    /**
     * @OA\Get(
     *     path="/api/sales-report/weekly/tele",
     *     summary="Laporan Aktivitas Mingguan - Role 30 (Leads → Assignment → Appointment)",
     *     description="Menampilkan laporan mingguan khusus user dengan cais_role_id=30.
     *     Breakdown per minggu untuk: jumlah_leads, jumlah_assignment, jumlah_appointment.
     *     Follow Up dan aktivitas lain TIDAK dihitung.",
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
     *         description="Data laporan mingguan role-30 berhasil diambil",
     *         @OA\JsonContent(
     *             @OA\Property(property="periode", type="string", example="MEI - 2026"),
     *             @OA\Property(property="count", type="integer", example=5),
     *             @OA\Property(
     *                 property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="no", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=101),
     *                     @OA\Property(property="nama_sales", type="string", example="Budi Santoso"),
     *                     @OA\Property(property="cabang", type="string", example="Central 1"),
     *                     @OA\Property(
     *                         property="w1", type="object",
     *                         @OA\Property(property="leads", type="integer", example=3),
     *                         @OA\Property(property="assignment", type="integer", example=3),
     *                         @OA\Property(property="appt", type="integer", example=2)
     *                     ),
     *                     @OA\Property(
     *                         property="w2", type="object",
     *                         @OA\Property(property="leads", type="integer", example=2),
     *                         @OA\Property(property="assignment", type="integer", example=2),
     *                         @OA\Property(property="appt", type="integer", example=1)
     *                     ),
     *                     @OA\Property(
     *                         property="w3", type="object",
     *                         @OA\Property(property="leads", type="integer", example=4),
     *                         @OA\Property(property="assignment", type="integer", example=4),
     *                         @OA\Property(property="appt", type="integer", example=3)
     *                     ),
     *                     @OA\Property(
     *                         property="w4", type="object",
     *                         @OA\Property(property="leads", type="integer", example=1),
     *                         @OA\Property(property="assignment", type="integer", example=1),
     *                         @OA\Property(property="appt", type="integer", example=0)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Error"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function weeklyRole30(Request $request)
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

        $startMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endMonth = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

        $salesData = $this->getSalesNamesRole30($branchId);

        if ($salesData->isEmpty()) {
            return response()->json([
                'periode' => strtoupper(Carbon::create()->month($month)->locale('id')->monthName) . ' - ' . $year,
                'data' => [],
                'count' => 0,
            ]);
        }

        $salesNames = $salesData->pluck('nama_sales')->toArray();
        $userIds = $salesData->pluck('user_id')->filter()->values()->toArray();

        $weeklyActivity = DB::table('sl_customer_activity as sa')
            ->select(
                DB::raw('ANY_VALUE(sa.created_by) as created_by'),
                'sa.user_id',

                // ── WEEK 1 (tgl 1-7) ──────────────────────────────────────
                DB::raw("COUNT(DISTINCT CASE
                    WHEN sa.tipe = 'Leads'
                     AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
                    THEN sa.leads_id END) as w1_leads"),

                DB::raw("COUNT(DISTINCT CASE
                    WHEN sa.tipe = 'Assignment'
                     AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
                    THEN sa.leads_id END) as w1_assignment"),

                DB::raw("SUM(CASE
                    WHEN sa.tipe = 'Appointment'
                     AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
                     AND EXISTS (
                         SELECT 1 FROM sl_customer_activity sa2
                         WHERE sa2.leads_id = sa.leads_id
                           AND sa2.tipe = 'Assignment'
                           AND sa2.tgl_activity <= sa.tgl_activity
                     )
                    THEN 1 ELSE 0 END) as w1_appt"),

                // ── WEEK 2 (tgl 8-14) ─────────────────────────────────────
                DB::raw("COUNT(DISTINCT CASE
                    WHEN sa.tipe = 'Leads'
                     AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
                    THEN sa.leads_id END) as w2_leads"),

                DB::raw("COUNT(DISTINCT CASE
                    WHEN sa.tipe = 'Assignment'
                     AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
                    THEN sa.leads_id END) as w2_assignment"),

                DB::raw("SUM(CASE
                    WHEN sa.tipe = 'Appointment'
                     AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
                     AND EXISTS (
                         SELECT 1 FROM sl_customer_activity sa2
                         WHERE sa2.leads_id = sa.leads_id
                           AND sa2.tipe = 'Assignment'
                           AND sa2.tgl_activity <= sa.tgl_activity
                     )
                    THEN 1 ELSE 0 END) as w2_appt"),

                // ── WEEK 3 (tgl 15-21) ────────────────────────────────────
                DB::raw("COUNT(DISTINCT CASE
                    WHEN sa.tipe = 'Leads'
                     AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
                    THEN sa.leads_id END) as w3_leads"),

                DB::raw("COUNT(DISTINCT CASE
                    WHEN sa.tipe = 'Assignment'
                     AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
                    THEN sa.leads_id END) as w3_assignment"),

                DB::raw("SUM(CASE
                    WHEN sa.tipe = 'Appointment'
                     AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
                     AND EXISTS (
                         SELECT 1 FROM sl_customer_activity sa2
                         WHERE sa2.leads_id = sa.leads_id
                           AND sa2.tipe = 'Assignment'
                           AND sa2.tgl_activity <= sa.tgl_activity
                     )
                    THEN 1 ELSE 0 END) as w3_appt"),

                // ── WEEK 4 (tgl 22-akhir bulan) ───────────────────────────
                DB::raw("COUNT(DISTINCT CASE
                    WHEN sa.tipe = 'Leads'
                     AND DAY(sa.tgl_activity) >= 22
                    THEN sa.leads_id END) as w4_leads"),

                DB::raw("COUNT(DISTINCT CASE
                    WHEN sa.tipe = 'Assignment'
                     AND DAY(sa.tgl_activity) >= 22
                    THEN sa.leads_id END) as w4_assignment"),

                DB::raw("SUM(CASE
                    WHEN sa.tipe = 'Appointment'
                     AND DAY(sa.tgl_activity) >= 22
                     AND EXISTS (
                         SELECT 1 FROM sl_customer_activity sa2
                         WHERE sa2.leads_id = sa.leads_id
                           AND sa2.tipe = 'Assignment'
                           AND sa2.tgl_activity <= sa.tgl_activity
                     )
                    THEN 1 ELSE 0 END) as w4_appt")
            )
            ->whereBetween('sa.tgl_activity', [$startMonth, $endMonth])
            ->whereIn('sa.user_id', $userIds)
            // Hanya ambil baris yang relevan dengan alur Leads→Assignment→Appointment
            ->whereIn('sa.tipe', ['Leads', 'Assignment', 'Appointment'])
            ->groupBy('sa.user_id')
            ->get();
        // ── Query agregasi mingguan untuk 3 metrik (Leads, Assignment, Appointment) ──
        //
        // Strategi per metrik:
        //   leads       = jenis_activity = 'Leads' (entry pertama saat leads dibuat)
        //   assignment  = jenis_activity = 'Assignment' (sales di-assign ke leads)
        //   appointment = jenis_activity = 'Appointment' DAN leads_id-nya sudah pernah
        //                 ada activity 'Assignment' sebelumnya (subquery EXISTS)
        //
        // Untuk performa: kita gunakan subquery EXISTS yang ringan, karena
        // assignment biasanya terjadi sebelum appointment pada leads yang sama.
        // ─────────────────────────────────────────────────────────────────────

        // $weeklyActivity = DB::table('sl_activity_sales as sa')
        //     ->select(
        //         'sa.created_by',

        //         // ── WEEK 1 (tgl 1-7) ──────────────────────────────────────
        //         DB::raw("COUNT(DISTINCT CASE
        //         WHEN sa.jenis_activity = 'Leads'
        //          AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
        //         THEN sa.leads_id END) as w1_leads"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Appointment'
        //          AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
        //         THEN 1 ELSE 0 END) as w1_appt"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Assignment'
        //          AND DAY(sa.tgl_activity) BETWEEN 1 AND 7
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Appointment'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w1_assignment"),

        //         // ── WEEK 2 (tgl 8-14) ─────────────────────────────────────
        //         DB::raw("COUNT(DISTINCT CASE
        //         WHEN sa.jenis_activity = 'Leads'
        //          AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
        //         THEN sa.leads_id END) as w2_leads"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Appointment'
        //          AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
        //         THEN 1 ELSE 0 END) as w2_appt"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Assignment'
        //          AND DAY(sa.tgl_activity) BETWEEN 8 AND 14
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Appointment'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w2_assignment"),

        //         // ── WEEK 3 (tgl 15-21) ────────────────────────────────────
        //         DB::raw("COUNT(DISTINCT CASE
        //         WHEN sa.jenis_activity = 'Leads'
        //          AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
        //         THEN sa.leads_id END) as w3_leads"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Appointment'
        //          AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
        //         THEN 1 ELSE 0 END) as w3_appt"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Assignment'
        //          AND DAY(sa.tgl_activity) BETWEEN 15 AND 21
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Appointment'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w3_assignment"),

        //         // ── WEEK 4 (tgl 22-akhir bulan) ───────────────────────────
        //         DB::raw("COUNT(DISTINCT CASE
        //         WHEN sa.jenis_activity = 'Leads'
        //          AND DAY(sa.tgl_activity) >= 22
        //         THEN sa.leads_id END) as w4_leads"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Appointment'
        //          AND DAY(sa.tgl_activity) >= 22
        //         THEN 1 ELSE 0 END) as w4_appt"),

        //         DB::raw("SUM(CASE
        //         WHEN sa.jenis_activity = 'Assignment'
        //          AND DAY(sa.tgl_activity) >= 22
        //          AND EXISTS (
        //              SELECT 1 FROM sl_activity_sales sa2
        //              WHERE sa2.leads_id = sa.leads_id
        //                AND sa2.jenis_activity = 'Appointment'
        //                AND sa2.tgl_activity <= sa.tgl_activity
        //          )
        //         THEN 1 ELSE 0 END) as w4_assignment")
        //     )
        //     ->whereBetween('sa.tgl_activity', [$startMonth, $endMonth])
        //     ->whereIn('sa.created_by', $salesNames)
        //     ->whereIn('sa.jenis_activity', ['Leads', 'Appointment', 'Assignment'])
        //     ->groupBy('sa.created_by')
        //     ->get();

        $emptyWeek = ['leads' => 0, 'appt' => 0, 'assignment' => 0];

        $data = [];
        $no = 1;
        foreach ($salesData as $sales) {
            $nama = $sales->nama_sales;
            $act = $weeklyActivity->firstWhere('user_id', $sales->user_id);

            $w1 = $emptyWeek;
            $w2 = $emptyWeek;
            $w3 = $emptyWeek;
            $w4 = $emptyWeek;

            if ($act) {
                $w1 = [
                    'leads' => (int) $act->w1_leads,
                    'appt' => (int) $act->w1_appt,
                    'assignment' => (int) $act->w1_assignment
                ];
                $w2 = [
                    'leads' => (int) $act->w2_leads,
                    'appt' => (int) $act->w2_appt,
                    'assignment' => (int) $act->w2_assignment
                ];
                $w3 = [
                    'leads' => (int) $act->w3_leads,
                    'appt' => (int) $act->w3_appt,
                    'assignment' => (int) $act->w3_assignment
                ];
                $w4 = [
                    'leads' => (int) $act->w4_leads,
                    'appt' => (int) $act->w4_appt,
                    'assignment' => (int) $act->w4_assignment
                ];
            }

            $data[] = [
                'no' => $no++,
                'user_id' => $sales->user_id ?? null,
                'nama_sales' => $nama,
                'cabang' => $sales->cabang,
                'w1' => $w1,
                'w2' => $w2,
                'w3' => $w3,
                'w4' => $w4,
            ];
        }

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ) . ' - ' . $year;

        return response()->json([
            'periode' => $periode,
            'data' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/sales-report/activity-detail/{user_id}",
     *     summary="Detail Aktivitas Sales",
     *     description="Menampilkan daftar aktivitas sales secara flat per bulan/tahun berdasarkan user_id sales. Field `aksi` berisi ID dokumen terkait jika jenis aktivitas adalah Leads/Quotation/SPK/PKS, null untuk jenis lainnya.",
     *     tags={"Sales Report"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         description="ID user sales. Harus terdaftar sebagai sales aktif.",
     *         @OA\Schema(type="integer", example=101)
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=false,
     *         description="Bulan laporan (1–12). Default: bulan berjalan.",
     *         @OA\Schema(type="integer", minimum=1, maximum=12, example=9)
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         required=false,
     *         description="Tahun laporan (4 digit). Default: tahun berjalan.",
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan ID cabang.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data detail aktivitas berhasil diambil.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user_id", type="integer", example=101),
     *             @OA\Property(property="sales_name", type="string", example="S. Wulandari Ayuningdiah"),
     *             @OA\Property(property="cabang", type="string", example="East"),
     *             @OA\Property(property="periode", type="string", example="SEPTEMBER - 2025"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="tgl_activity", type="string", example="2 September 2025"),
     *                     @OA\Property(property="nomor", type="string", example="CAT/CS/AAB8I-092025-00001"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="Universitas Katolik Widya Mandala Surabaya"),
     *                     @OA\Property(
     *                         property="tipe",
     *                         type="string",
     *                         enum={"Leads", "Appointment", "Visit", "Quotation", "SPK", "PKS", "Follow Up", "Kirim Berkas", "Email"},
     *                         example="Visit"
     *                     ),
     *                     @OA\Property(property="notes", type="string", example="Presentasi dan submit compro"),
     *                     @OA\Property(property="created_by", type="string", example="S. Wulandari Ayuningdiah"),
     *                     @OA\Property(property="created_at", type="string", example="02-09-2025 15:02:26"),
     *                     @OA\Property(
     *                         property="aksi",
     *                         type="integer",
     *                         nullable=true,
     *                         description="ID dokumen terkait: Leads→leads_id, Quotation→quotation_id, SPK→spk_id, PKS→pks_id. null untuk jenis lain.",
     *                         example=47
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="user_id tidak ditemukan dalam daftar sales aktif.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User ID tidak ditemukan dalam daftar sales aktif.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validasi input gagal.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The month must be between 1 and 12.")
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function activityDetail(Request $request, int $userId)
    {
        // ── 1. Validasi query params ──────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|digits:4',
            'branch_id' => 'nullable|integer|exists:mysqlhris.m_branch,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // ── 2. Resolusi parameter ─────────────────────────────────────────────
        $month = (int) ($request->month ?? now()->month);
        $year = (int) ($request->year ?? now()->year);
        $branchId = $request->branch_id;

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ) . ' - ' . $year;

        // ── 3. Validasi user_id ada di daftar sales aktif ─────────────────────
        $salesCollection = $this->getSalesNames($branchId);
        $matched = $salesCollection->firstWhere('user_id', $userId);

        if (!$matched) {
            return response()->json([
                'success' => false,
                'message' => 'User ID tidak ditemukan dalam daftar sales aktif.',
            ], 404);
        }

        $salesName = $matched->nama_sales;
        $cabang = $matched->cabang;

        // ── 4. Query aktivitas ────────────────────────────────────────────────
        $activities = DB::table('sl_activity_sales as sa')
            ->join('sl_leads as l', 'sa.leads_id', '=', 'l.id')
            ->select(
                'sa.id',
                'sa.leads_id',
                'sa.tgl_activity',
                'sa.jenis_activity',
                DB::raw("COALESCE(sa.notulen, '') AS notulen"),
                'sa.created_by',
                'sa.created_at',
                'l.nama_perusahaan'
            )
            ->whereBetween('sa.tgl_activity', [$startDate, $endDate])
            ->where('sa.created_by_user_id', $userId)
            ->orderBy('sa.tgl_activity', 'asc')
            ->orderBy('sa.created_at', 'asc')
            ->get();

        // ── 5. Batch-lookup ID dokumen per leads_id (anti N+1) ────────────────
        $leadsIdsNeedLookup = $activities
            ->whereIn('jenis_activity', ['Leads', 'Quotation', 'SPK', 'PKS'])
            ->pluck('leads_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $quotationMap = DB::table('sl_quotation')
            ->selectRaw('leads_id, MAX(id) as doc_id')
            ->whereIn('leads_id', $leadsIdsNeedLookup)
            ->whereNull('deleted_at')
            ->groupBy('leads_id')
            ->pluck('doc_id', 'leads_id');

        $spkMap = DB::table('sl_spk')
            ->selectRaw('leads_id, MAX(id) as doc_id')
            ->whereIn('leads_id', $leadsIdsNeedLookup)
            ->whereNull('deleted_at')
            ->groupBy('leads_id')
            ->pluck('doc_id', 'leads_id');

        $pksMap = DB::table('sl_pks')
            ->selectRaw('leads_id, MAX(id) as doc_id')
            ->whereIn('leads_id', $leadsIdsNeedLookup)
            ->whereNull('deleted_at')
            ->groupBy('leads_id')
            ->pluck('doc_id', 'leads_id');


        $data = $activities->map(function ($row, $index) use ($quotationMap, $spkMap, $pksMap) {
            $aksi = match ($row->jenis_activity) {
                'Leads' => $row->leads_id,
                'Quotation' => $quotationMap->get($row->leads_id),
                'SPK' => $spkMap->get($row->leads_id),
                'PKS' => $pksMap->get($row->leads_id),
                default => null,
            };

            return [
                'id' => $row->id,
                'tgl_activity' => Carbon::parse($row->tgl_activity)->locale('id')->isoFormat('D MMMM Y'),
                'nomor' => $index + 1,
                'nama_perusahaan' => $row->nama_perusahaan,
                'tipe' => $row->jenis_activity ?? '',
                'notes' => $row->notulen,
                'created_by' => $row->created_by,
                'created_at' => Carbon::parse($row->created_at)->format('d-m-Y H:i:s'),
                'aksi' => $aksi,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'user_id' => $userId,
            'sales_name' => $salesName,
            'cabang' => $cabang,
            'periode' => $periode,
            'data' => $data,
        ]);
    }

    // ============================================================
    //  ENDPOINT BARU: Activity Detail untuk cais_role_id = 30 (Telesales)
    // ============================================================

    /**
     * @OA\Get(
     *     path="/api/sales-report/activity-detail/tele/{user_id}",
     *     summary="Detail Aktivitas Telesales",
     *     description="Menampilkan daftar aktivitas telesales (cais_role_id=30) secara flat per bulan/tahun berdasarkan user_id. Hanya menampilkan aktivitas: Leads, Assignment, dan Appointment.",
     *     tags={"Sales Report"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *         description="ID user telesales. Harus terdaftar sebagai telesales aktif (cais_role_id=30).",
     *         @OA\Schema(type="integer", example=101)
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=false,
     *         description="Bulan laporan (1–12). Default: bulan berjalan.",
     *         @OA\Schema(type="integer", minimum=1, maximum=12, example=5)
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         required=false,
     *         description="Tahun laporan (4 digit). Default: tahun berjalan.",
     *         @OA\Schema(type="integer", example=2026)
     *     ),
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan ID cabang.",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data detail aktivitas telesales berhasil diambil.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user_id", type="integer", example=101),
     *             @OA\Property(property="sales_name", type="string", example="Budi Santoso"),
     *             @OA\Property(property="cabang", type="string", example="Central 1"),
     *             @OA\Property(property="periode", type="string", example="MEI - 2026"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="tgl_activity", type="string", example="5 Mei 2026"),
     *                     @OA\Property(property="nomor", type="integer", example=1),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT Maju Jaya"),
     *                     @OA\Property(
     *                         property="tipe",
     *                         type="string",
     *                         enum={"Leads", "Assignment", "Appointment"},
     *                         example="Assignment"
     *                     ),
     *                     @OA\Property(property="notes", type="string", example="Leads dari referral"),
     *                     @OA\Property(property="created_by", type="string", example="Budi Santoso"),
     *                     @OA\Property(property="created_at", type="string", example="05-05-2026 09:30:00"),
     *                     @OA\Property(
     *                         property="aksi",
     *                         type="integer",
     *                         nullable=true,
     *                         description="ID dokumen terkait: Leads→leads_id, Assignment→leads_id. null untuk Appointment.",
     *                         example=47
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="user_id tidak ditemukan dalam daftar telesales aktif.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User ID tidak ditemukan dalam daftar telesales aktif.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validasi input gagal.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The month must be between 1 and 12.")
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function activityDetailTele(Request $request, int $userId)
    {
        // ── 1. Validasi query params ──────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|digits:4',
            'branch_id' => 'nullable|integer|exists:mysqlhris.m_branch,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // ── 2. Resolusi parameter ─────────────────────────────────────────────
        $month = (int) ($request->month ?? now()->month);
        $year = (int) ($request->year ?? now()->year);
        $branchId = $request->branch_id;

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->endOfDay();

        $periode = strtoupper(
            Carbon::createFromDate($year, $month, 1)->locale('id')->monthName
        ) . ' - ' . $year;

        // ── 3. Validasi user_id ada di daftar telesales aktif (role 30) ───────
        $salesCollection = $this->getSalesNamesRole30($branchId);
        $matched = $salesCollection->firstWhere('user_id', $userId);

        if (!$matched) {
            return response()->json([
                'success' => false,
                'message' => 'User ID tidak ditemukan dalam daftar telesales aktif.',
            ], 404);
        }

        $salesName = $matched->nama_sales;
        $cabang = $matched->cabang;

        // ── 4. Query aktivitas (hanya Leads, Assignment, Appointment) ─────────

        $activities = DB::table('sl_customer_activity as sa')
            ->join('sl_leads as l', 'sa.leads_id', '=', 'l.id')
            ->select(
                'sa.id',
                'sa.leads_id',
                'sa.tgl_activity',
                'sa.tipe',
                'sa.tipe',
                DB::raw("COALESCE(sa.notulen, '') AS notulen"),
                'sa.created_by',
                'sa.created_at',
                'l.nama_perusahaan'
            )
            ->whereBetween('sa.tgl_activity', [$startDate, $endDate])
            ->where('sa.user_id', $userId)
            ->whereIn('sa.tipe', ['Leads', 'Assignment', 'Appointment'])
            ->orderBy('sa.tgl_activity', 'asc')
            ->orderBy('sa.created_at', 'asc')
            ->get();

        // ── 5. Map hasil ke response format ───────────────────────────────────
        // aksi: Leads & Assignment → leads_id (untuk navigasi ke detail leads)
        //       Appointment        → null (tidak ada dokumen terpisah)
        $data = $activities->map(function ($row, $index) {
            $aksi = match ($row->tipe) {
                'Leads', 'Assignment' => $row->leads_id,
                default => null,
            };

            return [
                'id' => $row->id,
                'tgl_activity' => Carbon::parse($row->tgl_activity)->locale('id')->isoFormat('D MMMM Y'),
                'nomor' => $index + 1,
                'nama_perusahaan' => $row->nama_perusahaan,
                'tipe' => $row->tipe ?? '',
                'notes' => $row->notulen,
                'created_by' => $row->created_by,
                'created_at' => Carbon::parse($row->created_at)->format('d-m-Y H:i:s'),
                'aksi' => $aksi,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'user_id' => $userId,
            'sales_name' => $salesName,
            'cabang' => $cabang,
            'periode' => $periode,
            'data' => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helper Methods
    // ──────────────────────────────────────────────

    /**
     * Ambil daftar sales KECUALI cais_role_id = 30 (existing behaviour).
     */
    private function getSalesNames($branchId = null)
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
            ->where('u.cais_role_id', '!=', 30) // Exclude admin/tele
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
    private function getSalesNamesRole30($branchId = null)
    {
        $query = DB::connection('mysqlhris')
            ->table('m_user as u')
            ->where('u.cais_role_id', '=', 30)
            ->whereNotIn('u.id', [101182, 123994]) // ✅ gunakan whereNotIn untuk array
            ->leftJoin('m_branch as b', 'b.id', '=', 'u.branch_id')
            ->select('u.full_name as nama_sales', 'b.name as cabang', 'u.id as user_id')
            ->groupBy('u.full_name', 'b.name', 'u.id');

        if ($branchId) {
            $query->where('u.branch_id', $branchId);
        }

        return $query->get();
    }

    /**
     * mentah
     */
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
    // private function getMonthlyAggregation($start, $end, array $salesNames)
    // {
    //     return DB::table('sl_activity_sales as sa')
    //         ->leftJoin('sl_leads as l', 'sa.leads_id', '=', 'l.id')
    //         ->select(
    //             'sa.created_by',

    //             // Kirim Proposal
    //             DB::raw("COUNT(CASE WHEN sa.jenis_activity IN ('Kirim Berkas', 'Email') THEN 1 END) as jumlah_kirim_proposal"),

    //             // Appointment
    //             DB::raw("COUNT(CASE
    //             WHEN sa.jenis_activity = 'Appointment'
    //              AND EXISTS (
    //                  SELECT 1 FROM sl_activity_sales sa2
    //                  WHERE sa2.leads_id = sa.leads_id
    //                    AND sa2.jenis_activity IN ('Kirim Berkas', 'Email')
    //                    AND sa2.tgl_activity <= sa.tgl_activity
    //              )
    //             THEN 1 END) as jumlah_appointment"),

    //             // Visit – sekarang termasuk Online Meeting, Email, Telepon
    //             DB::raw("COUNT(CASE
    //             WHEN sa.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
    //              AND EXISTS (
    //                  SELECT 1 FROM sl_activity_sales sa2
    //                  WHERE sa2.leads_id = sa.leads_id
    //                    AND sa2.jenis_activity = 'Appointment'
    //                    AND sa2.tgl_activity <= sa.tgl_activity
    //              )
    //             THEN 1 END) as jumlah_visit"),

    //             // Quotation – dengan pengecualian status 102, dan syarat Visit dalam arti luas
    //             DB::raw("COUNT(CASE
    //             WHEN sa.jenis_activity = 'Quotation'
    //              AND (
    //                  EXISTS (
    //                      SELECT 1 FROM sl_activity_sales sa2
    //                      WHERE sa2.leads_id = sa.leads_id
    //                        AND sa2.jenis_activity IN ('Visit', 'Online Meeting', 'Email', 'Telepon')
    //                        AND sa2.tgl_activity <= sa.tgl_activity
    //                  )
    //                  OR l.status_leads_id = 102
    //              )
    //             THEN 1 END) as jumlah_quotation"),

    //             // SPK
    //             DB::raw("COUNT(CASE
    //             WHEN sa.jenis_activity = 'SPK'
    //              AND EXISTS (
    //                  SELECT 1 FROM sl_activity_sales sa2
    //                  WHERE sa2.leads_id = sa.leads_id
    //                    AND sa2.jenis_activity = 'Quotation'
    //                    AND sa2.tgl_activity <= sa.tgl_activity
    //              )
    //             THEN 1 END) as jumlah_spk"),

    //             // PKS
    //             DB::raw("COUNT(CASE
    //             WHEN sa.jenis_activity = 'PKS'
    //              AND EXISTS (
    //                  SELECT 1 FROM sl_activity_sales sa2
    //                  WHERE sa2.leads_id = sa.leads_id
    //                    AND sa2.jenis_activity = 'SPK'
    //                    AND sa2.tgl_activity <= sa.tgl_activity
    //              )
    //             THEN 1 END) as jumlah_pks"),

    //             // Follow Up
    //             DB::raw("COUNT(CASE WHEN sa.jenis_activity = 'Follow Up' THEN 1 END) as jumlah_follow_up")
    //         )
    //         ->whereBetween('sa.tgl_activity', [$start, $end])
    //         ->whereIn('sa.created_by', $salesNames)
    //         ->groupBy('sa.created_by')
    //         ->get();
    // }


    //mentah
    private function getRole30MonthlyAggregation($start, $end, array $userIds)
    {
        return DB::table('sl_customer_activity as sa')
            ->select(
                DB::raw('ANY_VALUE(sa.created_by) as created_by'),
                'sa.user_id',

                // Leads: semua activity 'Leads' langsung dihitung (distinct per leads_id)
                DB::raw("COUNT(DISTINCT CASE
                WHEN sa.tipe = 'Leads'
                THEN sa.leads_id END) as jumlah_leads"),

                // Assignment: semua activity 'Assignment' langsung dihitung (distinct per leads_id)
                DB::raw("COUNT(DISTINCT CASE
                WHEN sa.tipe = 'Assignment'
                THEN sa.leads_id END) as jumlah_assignment"),

                // Appointment: semua activity 'Appointment' langsung dihitung
                DB::raw("COUNT(CASE
                WHEN sa.tipe = 'Appointment'
                THEN 1 END) as jumlah_appointment")
            )
            ->whereBetween('sa.tgl_activity', [$start, $end])
            ->whereIn('sa.user_id', $userIds)
            ->whereIn('sa.tipe', ['Leads', 'Assignment', 'Appointment'])
            ->groupBy('sa.user_id')
            ->get();
    }
    // private function getRole30MonthlyAggregation($start, $end, array $salesNames)
    // {
    //     return DB::table('sl_activity_sales as sa')
    //         ->select(
    //             'sa.created_by',

    //             // Leads: semua aktivitas 'Leads' (distinct per leads_id)
    //             DB::raw("COUNT(DISTINCT CASE
    //             WHEN sa.jenis_activity = 'Leads'
    //             THEN sa.leads_id END) as jumlah_leads"),

    //             // Appointment: semua appointment (tanpa syarat, karena setelah Leads)
    //             DB::raw("COUNT(CASE
    //             WHEN sa.jenis_activity = 'Appointment'
    //             THEN 1 END) as jumlah_appointment"),

    //             // Assignment: hanya jika sudah ada Appointment sebelumnya pada leads yang sama
    //             DB::raw("COUNT(CASE
    //             WHEN sa.jenis_activity = 'Assignment'
    //              AND EXISTS (
    //                  SELECT 1 FROM sl_activity_sales sa2
    //                  WHERE sa2.leads_id = sa.leads_id
    //                    AND sa2.jenis_activity = 'Appointment'
    //                    AND sa2.tgl_activity <= sa.tgl_activity
    //              )
    //             THEN 1 END) as jumlah_assignment")
    //         )
    //         ->whereBetween('sa.tgl_activity', [$start, $end])
    //         ->whereIn('sa.created_by', $salesNames)
    //         ->whereIn('sa.jenis_activity', ['Leads', 'Appointment', 'Assignment'])
    //         ->groupBy('sa.created_by')
    //         ->get();
    // }

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