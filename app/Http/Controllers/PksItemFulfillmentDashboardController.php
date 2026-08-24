<?php

namespace App\Http\Controllers;

use App\Services\Pks\Fulfillment\ItemFulfillmentDashboardService;
use App\Services\Pks\Fulfillment\PksDashboardFilterBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PksItemFulfillmentDashboardController extends Controller
{
    private const PER_PAGE_DEFAULT = 15;

    public function __construct(
        private PksDashboardFilterBuilder $filterBuilder,
        private ItemFulfillmentDashboardService $dashboardService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/item-dashboard",
     *     tags={"PKS Fulfillment"},
     *     summary="Dashboard item fulfillment PKS",
     *     description="Daftar PKS aktif beserta jumlah item request & receive, plus empat angka agregat.
     *
     * Semantik yang dipakai adalah JUMLAH BARIS, bukan kuantitas:
     * - jumlah_request  : baris sl_pks_item_request berstatus open — barang sedang di jalan, turun saat penerimaan dicatat
     * - jumlah_receive  : baris sl_pks_item_fulfillment dengan qty_terpenuhi > 0 — jenis item yang sudah pernah sampai di site
     * - total_fulfilled : baris sl_pks_item_fulfillment berstatus fully_fulfilled
     * - total_remaining : semesta item quotation dikurangi yang fully_fulfilled, minimum 0
     *
     * Blok `summary` dihitung atas SELURUH PKS yang cocok filter, bukan hanya halaman aktif.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="search_by", in="query", required=false, description="nama_perusahaan | nomor | created_by", @OA\Schema(type="string")),
     *     @OA\Parameter(name="branch", in="query", required=false, description="Filter branch (sl_leads.branch_id)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="tgl_dari", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="tgl_sampai", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PKS item fulfillment dashboard retrieved successfully"),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total_request", type="integer"),
     *                 @OA\Property(property="total_receive", type="integer"),
     *                 @OA\Property(property="total_fulfilled", type="integer"),
     *                 @OA\Property(property="total_remaining", type="integer")
     *             ),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(type="object",
     *
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="nomor", type="string"),
     *                     @OA\Property(property="nama_perusahaan", type="string"),
     *                     @OA\Property(property="jumlah_request", type="integer"),
     *                     @OA\Property(property="jumlah_receive", type="integer"),
     *                     @OA\Property(property="created_by", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="total_per_page", type="integer")
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="tgl_dari", type="string"),
     *                 @OA\Property(property="tgl_sampai", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function itemDashboard(Request $request): JsonResponse
    {
        try {
            $tglDari = $request->input('tgl_dari') ?? $this->filterBuilder->defaultTglDari();
            $tglSampai = $request->input('tgl_sampai') ?? $this->filterBuilder->defaultTglSampai();

            $base = $this->filterBuilder->build($request, $tglDari, $tglSampai);

            // Rekap dihitung sekali atas seluruh himpunan terfilter lalu dipetakan
            // ke baris halaman aktif — supaya jumlah query tetap konstan terhadap
            // per_page maupun jumlah PKS.
            $quotationIdByPksId = (clone $base)->pluck('sl_pks.quotation_id', 'sl_pks.id')->all();
            $perPks = $this->dashboardService->perPks($quotationIdByPksId);
            $summary = $this->dashboardService->summaryFor($perPks);

            $pksList = (clone $base)
                ->select([
                    'sl_pks.id',
                    'sl_pks.nomor',
                    'sl_pks.nama_perusahaan',
                    'sl_pks.created_by',
                ])
                ->orderBy('sl_pks.created_at', 'desc')
                ->paginate((int) $request->input('per_page', self::PER_PAGE_DEFAULT));

            $pksList->getCollection()->transform(function ($pks) use ($perPks) {
                $rekap = $perPks[$pks->id] ?? null;

                return [
                    'id' => $pks->id,
                    'nomor' => $pks->nomor ?: '-',
                    'nama_perusahaan' => $pks->nama_perusahaan ?: '-',
                    'jumlah_request' => $rekap['jumlah_request'] ?? 0,
                    'jumlah_receive' => $rekap['jumlah_receive'] ?? 0,
                    'created_by' => $pks->created_by ?: '-',
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'PKS item fulfillment dashboard retrieved successfully',
                'summary' => $summary,
                'data' => $pksList->items(),
                'pagination' => [
                    'current_page' => $pksList->currentPage(),
                    'last_page' => $pksList->lastPage(),
                    'total' => $pksList->total(),
                    'total_per_page' => $pksList->count(),
                ],
                'meta' => ['tgl_dari' => $tglDari, 'tgl_sampai' => $tglSampai],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error in PksItemFulfillmentDashboardController@itemDashboard: '.$e->getMessage());

            return $this->serverErrorResponse($e->getMessage());
        }
    }
}
