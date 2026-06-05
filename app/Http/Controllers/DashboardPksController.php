<?php

namespace App\Http\Controllers;

use App\Models\Pks;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Dashboard PKS",
 *     description="API untuk dashboard monitoring kontrak PKS"
 * )
 */
class DashboardPksController extends Controller
{
    // Status PKS
    const STATUS_BELUM_UPLOAD   = 5; // Menunggu Upload PKS Disetujui
    const STATUS_BELUM_AKTIVASI = 6; // Menunggu Aktivasi Manager CRM
    const STATUS_AKTIF          = 7; // Kontrak Aktif Oleh Manager CRM
    const STATUS_TERMINATED     = 100;

    /**
     * @OA\Get(
     *     path="/api/dashboard-pks/summary",
     *     tags={"Dashboard PKS"},
     *     summary="Ringkasan status & jumlah kontrak PKS",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function summary(Request $request)
    {
        $branchId = $request->get('branch_id') ?: null;
        $cacheKey = 'dashboard_pks_summary_' . ($branchId ?: 'all');

        // Cache 60 detik supaya tidak hitung ulang tiap reload (DB remote = mahal)
        $data = \Cache::remember($cacheKey, 60, function () use ($branchId) {
            $today      = Carbon::today()->format('Y-m-d');
            $threeMonth = Carbon::today()->addMonths(3)->format('Y-m-d');

            // 1 query: bucket kontrak aktif pakai conditional aggregation.
            // Pakai perbandingan langsung (bukan whereDate) supaya index kepakai.
            $buckets = Pks::whereNull('deleted_at')
                ->where('status_pks_id', self::STATUS_AKTIF)
                ->whereNotNull('kontrak_akhir')
                ->where('kontrak_akhir', '!=', '0000-00-00')
                ->where('kontrak_akhir', '!=', '')
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->selectRaw(
                    'SUM(CASE WHEN kontrak_akhir > ? THEN 1 ELSE 0 END) as lebih_dari_3_bulan,
                     SUM(CASE WHEN kontrak_akhir >= ? AND kontrak_akhir <= ? THEN 1 ELSE 0 END) as mau_habis,
                     SUM(CASE WHEN kontrak_akhir < ? THEN 1 ELSE 0 END) as kontrak_habis',
                    [$threeMonth, $today, $threeMonth, $today]
                )
                ->first();

            // 1 query: hitung status (belum upload & belum aktivasi) sekaligus
            $statusCounts = Pks::whereNull('deleted_at')
                ->whereIn('status_pks_id', [self::STATUS_BELUM_UPLOAD, self::STATUS_BELUM_AKTIVASI])
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->selectRaw('status_pks_id, COUNT(*) as c')
                ->groupBy('status_pks_id')
                ->pluck('c', 'status_pks_id');

            $lebihDari3Bulan = (int) ($buckets->lebih_dari_3_bulan ?? 0);
            $mauHabis        = (int) ($buckets->mau_habis ?? 0);
            $kontrakHabis    = (int) ($buckets->kontrak_habis ?? 0);

            return [
                'lebih_dari_3_bulan'   => $lebihDari3Bulan,
                'mau_habis'            => $mauHabis,        // < 3 bulan
                'kontrak_habis'        => $kontrakHabis,    // sudah lewat
                'belum_upload_pks'     => (int) ($statusCounts[self::STATUS_BELUM_UPLOAD] ?? 0),
                'belum_aktivasi_site'  => (int) ($statusCounts[self::STATUS_BELUM_AKTIVASI] ?? 0),
                'total_kontrak_aktif'  => $lebihDari3Bulan + $mauHabis + $kontrakHabis,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard-pks/expiring",
     *     tags={"Dashboard PKS"},
     *     summary="List PKS teratas yang mendekati habis kontrak (pagination)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=5)),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function expiring(Request $request)
    {
        $today    = Carbon::today();
        $branchId = $request->get('branch_id') ?: null;
        $perPage  = (int) $request->get('per_page', 5);
        if ($perPage < 1)  $perPage = 5;
        if ($perPage > 50) $perPage = 50;

        $todayStr = $today->format('Y-m-d');

        $query = Pks::with(['branch:id,name'])
            ->select(['id', 'nomor', 'nama_perusahaan', 'branch_id', 'kontrak_awal', 'kontrak_akhir'])
            ->whereNull('deleted_at')
            ->where('status_pks_id', self::STATUS_AKTIF)
            ->whereNotNull('kontrak_akhir')
            ->where('kontrak_akhir', '!=', '0000-00-00')
            ->where('kontrak_akhir', '!=', '')
            ->where('kontrak_akhir', '>=', $todayStr) // hanya yg belum habis
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->orderBy('kontrak_akhir', 'asc'); // paling dekat habis di atas

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(function ($pks) use ($today) {
            $akhir    = Carbon::parse($pks->kontrak_akhir)->startOfDay();
            $sisaHari = $today->diffInDays($akhir, false); // negatif kalau lewat

            if ($sisaHari < 0) {
                $keterangan = 'Kontrak Habis';
            } elseif ($sisaHari <= 90) {
                $keterangan = 'Akan Habis';
            } else {
                $keterangan = 'Berjalan';
            }

            return [
                'id'              => $pks->id,
                'nomor'           => $pks->nomor,
                'nama_perusahaan' => $pks->nama_perusahaan,
                'branch'          => $pks->branch->name ?? '-',
                'kontrak_awal'    => $pks->kontrak_awal ? Carbon::parse($pks->kontrak_awal)->format('d-m-Y') : '-',
                'kontrak_akhir'   => Carbon::parse($pks->kontrak_akhir)->format('d-m-Y'),
                'sisa_hari'       => $sisaHari,
                'keterangan'      => $keterangan,
            ];
        });

        return response()->json([
            'success'    => true,
            'data'       => $data,
            'pagination' => [
                'current_page'   => $paginator->currentPage(),
                'total_per_page' => $paginator->perPage(),
                'total'          => $paginator->total(),
                'last_page'      => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard-pks/list",
     *     tags={"Dashboard PKS"},
     *     summary="List PKS by tipe (terbaru / belum-aktivasi / site-aktif)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"latest","belum-aktivasi","site-aktif"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=5)),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function list(Request $request)
    {
        $type     = $request->get('type', 'latest');
        $branchId = $request->get('branch_id') ?: null;
        $perPage  = (int) $request->get('per_page', 5);
        if ($perPage < 1)  $perPage = 5;
        if ($perPage > 50) $perPage = 50;

        $query = Pks::with(['branch:id,name', 'statusPks:id,nama'])
            ->select(['id', 'nomor', 'nama_perusahaan', 'branch_id', 'kontrak_awal', 'kontrak_akhir', 'status_pks_id', 'created_at'])
            ->whereNull('deleted_at')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId));

        if ($type === 'belum-aktivasi') {
            $query->where('status_pks_id', self::STATUS_BELUM_AKTIVASI);
        } elseif ($type === 'site-aktif') {
            $query->where('status_pks_id', self::STATUS_AKTIF);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = collect($paginator->items())->map(function ($pks) {
            $raw = $pks->getRawOriginal('kontrak_awal');
            $kontrakAwal = '-';
            if (!empty($raw) && $raw !== '0000-00-00') {
                try { $kontrakAwal = Carbon::parse($raw)->format('d-m-Y'); } catch (\Exception $e) {}
            }

            return [
                'id'              => $pks->id,
                'nomor'           => $pks->nomor,
                'nama_perusahaan' => $pks->nama_perusahaan,
                'branch'          => $pks->branch->name ?? '-',
                'kontrak_awal'    => $kontrakAwal,
                'status'          => $pks->statusPks->nama ?? '-',
                'status_pks_id'   => $pks->status_pks_id,
            ];
        });

        return response()->json([
            'success'    => true,
            'data'       => $data,
            'pagination' => [
                'current_page'   => $paginator->currentPage(),
                'total_per_page' => $paginator->perPage(),
                'total'          => $paginator->total(),
                'last_page'      => $paginator->lastPage(),
            ],
        ]);
    }
}
