<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\ReportActivityDetailRequest;
use App\Http\Requests\Report\ReportActivityDetailTeleRequest;
use App\Http\Requests\Report\ReportPeriodRequiredRequest;
use App\Services\Report\ReportDetailService;
use App\Services\Report\ReportRole30Service;
use App\Services\Report\ReportSalesService;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Sales Report",
 *     description="API Endpoints untuk Dashboard Sales Report"
 * )
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportSalesService $reportSalesService,
        private readonly ReportRole30Service $reportRole30Service,
        private readonly ReportDetailService $reportDetailService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/sales-report/monthly",
     *     summary="Laporan Aktivitas Sales Bulanan",
     *     description="Menampilkan laporan bulanan aktivitas sales per orang, membandingkan bulan ini vs bulan lalu...",
     *     tags={"Sales Report"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="month", in="query", required=true,
     *         description="Bulan laporan (1-12)",
     *
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Parameter(
     *         name="year", in="query", required=true,
     *         description="Tahun laporan (4 digit)",
     *
     *         @OA\Schema(type="integer", example=2026)
     *     ),
     *
     *     @OA\Parameter(
     *         name="branch_id", in="query", required=false,
     *         description="Filter berdasarkan ID cabang (opsional)",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data laporan bulanan berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="periode", type="string", example="05-2026"),
     *             @OA\Property(
     *                 property="data", type="array",
     *
     *                 @OA\Items(
     *
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
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The month field is required.")
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function monthly(ReportPeriodRequiredRequest $request)
    {
        $result = $this->reportSalesService->monthly(
            (int) $request->month,
            (int) $request->year,
            $request->branch_id
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/sales-report/weekly",
     *     summary="Laporan Aktivitas Sales Mingguan",
     *     description="Menampilkan laporan mingguan aktivitas sales per orang dalam satu bulan...",
     *     tags={"Sales Report"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="month", in="query", required=true,
     *         description="Bulan laporan (1-12)",
     *
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Parameter(
     *         name="year", in="query", required=true,
     *         description="Tahun laporan (4 digit)",
     *
     *         @OA\Schema(type="integer", example=2026)
     *     ),
     *
     *     @OA\Parameter(
     *         name="branch_id", in="query", required=false,
     *         description="Filter berdasarkan ID cabang (opsional)",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data laporan mingguan berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="periode", type="string", example="MEI - 2026"),
     *             @OA\Property(
     *                 property="data", type="array",
     *
     *                 @OA\Items(
     *
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
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The month field is required.")
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function weekly(ReportPeriodRequiredRequest $request)
    {
        $result = $this->reportSalesService->weekly(
            (int) $request->month,
            (int) $request->year,
            $request->branch_id
        );

        return response()->json($result);
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
     *
     *     @OA\Parameter(
     *         name="month", in="query", required=true,
     *         description="Bulan laporan (1-12)",
     *
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Parameter(
     *         name="year", in="query", required=true,
     *         description="Tahun laporan (4 digit)",
     *
     *         @OA\Schema(type="integer", example=2026)
     *     ),
     *
     *     @OA\Parameter(
     *         name="branch_id", in="query", required=false,
     *         description="Filter berdasarkan ID cabang (opsional)",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data laporan bulanan role-30 berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="periode", type="string", example="MEI - 2026"),
     *             @OA\Property(property="count", type="integer", example=5),
     *             @OA\Property(
     *                 property="data", type="array",
     *
     *                 @OA\Items(
     *
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
     *
     *     @OA\Response(response=422, description="Validation Error"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function monthlyRole30(ReportPeriodRequiredRequest $request)
    {
        $result = $this->reportRole30Service->monthlyRole30(
            (int) $request->month,
            (int) $request->year,
            $request->branch_id
        );

        return response()->json($result);
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
     *
     *     @OA\Parameter(
     *         name="month", in="query", required=true,
     *         description="Bulan laporan (1-12)",
     *
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Parameter(
     *         name="year", in="query", required=true,
     *         description="Tahun laporan (4 digit)",
     *
     *         @OA\Schema(type="integer", example=2026)
     *     ),
     *
     *     @OA\Parameter(
     *         name="branch_id", in="query", required=false,
     *         description="Filter berdasarkan ID cabang (opsional)",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data laporan mingguan role-30 berhasil diambil",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="periode", type="string", example="MEI - 2026"),
     *             @OA\Property(property="count", type="integer", example=5),
     *             @OA\Property(
     *                 property="data", type="array",
     *
     *                 @OA\Items(
     *
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
     *
     *     @OA\Response(response=422, description="Validation Error"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function weeklyRole30(ReportPeriodRequiredRequest $request)
    {
        $result = $this->reportRole30Service->weeklyRole30(
            (int) $request->month,
            (int) $request->year,
            $request->branch_id
        );

        return response()->json($result);
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
     *
     *         @OA\Schema(type="integer", example=101)
     *     ),
     *
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=false,
     *         description="Bulan laporan (1–12). Default: bulan berjalan.",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=12, example=9)
     *     ),
     *
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         required=false,
     *         description="Tahun laporan (4 digit). Default: tahun berjalan.",
     *
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan ID cabang.",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="jenis_activity",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan jenis aktivitas. Kosongkan untuk menampilkan semua jenis.",
     *
     *         @OA\Schema(
     *             type="string",
     *             enum={"Leads", "Appointment", "Visit", "Quotation", "SPK", "PKS", "Follow Up", "Kirim Berkas", "Email"},
     *             example="Visit"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data detail aktivitas berhasil diambil.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user_id", type="integer", example=101),
     *             @OA\Property(property="sales_name", type="string", example="S. Wulandari Ayuningdiah"),
     *             @OA\Property(property="cabang", type="string", example="East"),
     *             @OA\Property(property="periode", type="string", example="SEPTEMBER - 2025"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
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
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User ID tidak ditemukan dalam daftar sales aktif.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validasi input gagal.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The month must be between 1 and 12.")
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function activityDetail(ReportActivityDetailRequest $request, int $userId)
    {
        $result = $this->reportDetailService->activityDetail(
            $userId,
            (int) ($request->month ?? now()->month),
            (int) ($request->year ?? now()->year),
            $request->branch_id,
            $request->jenis_activity
        );

        if ($result === null) {
            return $this->notFoundResponse('User ID tidak ditemukan dalam daftar sales aktif.');
        }

        return response()->json([
            'success' => true,
            ...$result,
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
     *
     *         @OA\Schema(type="integer", example=101)
     *     ),
     *
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         required=false,
     *         description="Bulan laporan (1–12). Default: bulan berjalan.",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=12, example=5)
     *     ),
     *
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         required=false,
     *         description="Tahun laporan (4 digit). Default: tahun berjalan.",
     *
     *         @OA\Schema(type="integer", example=2026)
     *     ),
     *
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan ID cabang.",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="jenis_activity",
     *         in="query",
     *         required=false,
     *         description="Filter berdasarkan jenis aktivitas telesales. Kosongkan untuk menampilkan semua jenis.",
     *
     *         @OA\Schema(
     *             type="string",
     *             enum={"Leads", "Assignment", "Appointment"},
     *             example="Appointment"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Data detail aktivitas telesales berhasil diambil.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user_id", type="integer", example=101),
     *             @OA\Property(property="sales_name", type="string", example="Budi Santoso"),
     *             @OA\Property(property="cabang", type="string", example="Central 1"),
     *             @OA\Property(property="periode", type="string", example="MEI - 2026"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
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
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User ID tidak ditemukan dalam daftar telesales aktif.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validasi input gagal.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The month must be between 1 and 12.")
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function activityDetailTele(ReportActivityDetailTeleRequest $request, int $userId)
    {
        $result = $this->reportDetailService->activityDetailTele(
            $userId,
            (int) ($request->month ?? now()->month),
            (int) ($request->year ?? now()->year),
            $request->branch_id,
            $request->jenis_activity
        );

        if ($result === null) {
            return $this->notFoundResponse('User ID tidak ditemukan dalam daftar telesales aktif.');
        }

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }
}
