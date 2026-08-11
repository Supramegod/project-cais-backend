<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pks\ItemFulfillmentBulkStoreRequest;
use App\Http\Requests\Pks\ItemFulfillmentEditRequest;
use App\Http\Requests\Pks\ItemFulfillmentReceiveRequest;
use App\Http\Requests\Pks\ItemFulfillmentStoreRequest;
use App\Http\Requests\Pks\VisitRecordStoreRequest;
use App\Http\Requests\Pks\VisitRescheduleRequest;
use App\Http\Requests\Pks\VisitScheduleManualStoreRequest;
use App\Models\Pks;
use App\Models\PksFulfillmentLog;
use App\Models\PksItemFulfillment;
use App\Models\PksItemRequest;
use App\Models\PksVisitSchedule;
use App\Services\Pks\Fulfillment\FulfillmentLogService;
use App\Services\Pks\Fulfillment\HcFulfillmentService;
use App\Services\Pks\Fulfillment\ItemFulfillmentService;
use App\Services\Pks\Fulfillment\ItemReceivingService;
use App\Services\Pks\Fulfillment\PksFulfillmentDashboardService;
use App\Services\Pks\Fulfillment\PksFulfillmentSummaryService;
use App\Services\Pks\Fulfillment\VisitFulfillmentService;
use App\Services\Pks\Fulfillment\VisitSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="PKS Fulfillment",
 *     description="API untuk PKS Item Fulfillment, Visit Scheduling, dan Visit Record"
 * )
 *
 * @OA\Schema(
 *     schema="PksFulfillmentItem",
 *     type="object",
 *
 *     @OA\Property(property="item_type_id", type="integer", enum={1,2,3}, description="1=kaporlap, 2=device, 3=chemical"),
 *     @OA\Property(property="item_type", type="string"),
 *     @OA\Property(property="item_id", type="integer"),
 *     @OA\Property(property="nama", type="string"),
 *     @OA\Property(property="qty_diminta", type="integer"),
 *     @OA\Property(property="qty_request", type="integer", description="Sudah dikirim, menunggu konfirmasi penerimaan"),
 *     @OA\Property(property="qty_terpenuhi", type="integer", description="Sudah diterima site"),
 *     @OA\Property(property="remaining", type="integer", description="qty_diminta - qty_terpenuhi"),
 *     @OA\Property(property="boleh_direquest", type="integer", description="Batas qty request berikutnya: qty_diminta - qty_terpenuhi - qty_request"),
 *     @OA\Property(property="status", type="string", enum={"not_yet_fulfilled","requested","partially_fulfilled","fully_fulfilled"})
 * )
 *
 * @OA\Schema(
 *     schema="PksItemRequestRow",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="fulfillment_id", type="integer"),
 *     @OA\Property(property="site_id", type="integer"),
 *     @OA\Property(property="batch_id", type="string", format="uuid", description="DEPRECATED — alias request_batch_id. Isinya batch PENGIRIMAN, bukan penerimaan."),
 *     @OA\Property(property="batch_ke", type="integer", nullable=true, description="DEPRECATED — alias request_batch_ke"),
 *     @OA\Property(property="request_batch_id", type="string", format="uuid", description="Batch pengiriman yang membuat baris ini"),
 *     @OA\Property(property="request_batch_ke", type="integer", nullable=true),
 *     @OA\Property(property="received_batch_id", type="string", format="uuid", nullable=true, description="Batch penerimaan yang menutup baris ini. NULL selama status masih open, atau bila log lama tidak menyimpan tautannya. Pakai id ini untuk membuka batch detail beraksi 'receive'."),
 *     @OA\Property(property="received_batch_ke", type="integer", nullable=true),
 *     @OA\Property(property="item_type", type="string"),
 *     @OA\Property(property="item_id", type="integer"),
 *     @OA\Property(property="nama", type="string", nullable=true),
 *     @OA\Property(property="qty_request", type="integer"),
 *     @OA\Property(property="qty_diterima", type="integer"),
 *     @OA\Property(property="kurang", type="integer"),
 *     @OA\Property(property="status", type="string", enum={"open","received","short"}),
 *     @OA\Property(property="received_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="PksVisitSchedule",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="pks_id", type="integer"),
 *     @OA\Property(property="site_id", type="integer"),
 *     @OA\Property(property="role", type="string"),
 *     @OA\Property(property="tgl_jadwal", type="string", format="date"),
 *     @OA\Property(property="tgl_jadwal_asli", type="string", format="date", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"scheduled","rescheduled","done","missed"})
 * )
 *
 * @OA\Schema(
 *     schema="PksVisitRecord",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="pks_id", type="integer"),
 *     @OA\Property(property="schedule_id", type="integer", nullable=true),
 *     @OA\Property(property="role", type="string"),
 *     @OA\Property(property="tgl_visit_aktual", type="string", format="date"),
 *     @OA\Property(property="hasil_visit", type="string", enum={"selesai","ada_kendala","ditunda"}),
 *     @OA\Property(property="catatan", type="string"),
 *     @OA\Property(property="fotos", type="array", @OA\Items(ref="#/components/schemas/PksVisitRecordFoto"))
 * )
 *
 * @OA\Schema(
 *     schema="PksVisitRecordFoto",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="visit_record_id", type="integer"),
 *     @OA\Property(property="url_file", type="string"),
 *     @OA\Property(property="nama_file", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="PksVisitTarget",
 *     type="object",
 *
 *     @OA\Property(property="role", type="string"),
 *     @OA\Property(property="target_total", type="integer"),
 *     @OA\Property(property="target_terpakai", type="integer"),
 *     @OA\Property(property="sisa", type="integer")
 * )
 */
class PksFulfillmentController extends Controller
{
    /**
     * Role (cais_role_id) yang boleh melakukan aksi tulis (create/edit) fulfillment.
     * CATATAN: daftar role final masih menunggu konfirmasi bisnis — ubah di satu
     * tempat ini saja. Sementara mengikuti set yang sudah dipakai editFulfillment.
     */
    private const MANAGE_ROLES = [2, 8, 10, 54, 55, 56, 98];

    public function __construct(
        private ItemFulfillmentService $itemFulfillmentService,
        private ItemReceivingService $itemReceivingService,
        private FulfillmentLogService $fulfillmentLogService,
        private VisitSchedulingService $visitSchedulingService,
        private VisitFulfillmentService $visitFulfillmentService,
    ) {}

    /**
     * Gate aksi tulis fulfillment. Return JsonResponse 403 bila tidak berhak,
     * atau null bila boleh lanjut.
     */
    private function ensureCanManage(): ?JsonResponse
    {
        $user = Auth::user();

        if (! $user || ! in_array($user->cais_role_id, self::MANAGE_ROLES, true)) {
            return $this->errorResponse('Anda tidak memiliki akses untuk aksi fulfillment ini.', 403);
        }

        return null;
    }

    // ==================== DASHBOARD (SEMUA PKS) ====================

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/dashboard",
     *     tags={"PKS Fulfillment"},
     *     summary="Dashboard rekap pemenuhan PKS",
     *     description="Daftar PKS dengan rekap ringkas pemenuhan Item & Visit per PKS. Search & pagination mengikuti pola PKS list (search_by nama_perusahaan fulltext / nomor / created_by LIKE; default rentang tanggal). Response memakai blok pagination + meta yang sama.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="search_by", in="query", required=false, description="nama_perusahaan | nomor | created_by", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter status_pks_id", @OA\Schema(type="integer")),
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
     *             @OA\Property(property="message", type="string", example="PKS fulfillment dashboard retrieved successfully"),
     *             @OA\Property(property="summary", type="object", description="Rekap fulfillment agregat seluruh PKS yang cocok filter",
     *                 @OA\Property(property="item", type="object",
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="fully_fulfilled", type="integer"),
     *                     @OA\Property(property="qty_diminta", type="integer"),
     *                     @OA\Property(property="qty_terpenuhi", type="integer"),
     *                     @OA\Property(property="persen", type="number", format="float")
     *                 ),
     *                 @OA\Property(property="visit", type="object",
     *                     @OA\Property(property="target_total", type="integer"),
     *                     @OA\Property(property="target_terpakai", type="integer"),
     *                     @OA\Property(property="persen", type="number", format="float"),
     *                     @OA\Property(property="missed", type="integer")
     *                 ),
     *                 @OA\Property(property="hc", type="object",
     *                     @OA\Property(property="total_vacancy", type="integer"),
     *                     @OA\Property(property="target_kebutuhan", type="integer"),
     *                     @OA\Property(property="akumulasi_pengiriman", type="integer"),
     *                     @OA\Property(property="sisa_outstanding", type="integer"),
     *                     @OA\Property(property="persen", type="number", format="float")
     *                 )
     *             ),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
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
    public function dashboard(Request $request, PksFulfillmentDashboardService $dashboardService): JsonResponse
    {
        try {
            $tglDari = $request->tgl_dari ?? Carbon::now()->startOfMonth()->subMonths(6)->toDateString();
            $tglSampai = $request->tgl_sampai ?? Carbon::now()->toDateString();

            // Base query + filter (dipakai untuk summary keseluruhan & list paginated).
            $base = Pks::query()
                ->leftJoin('sl_leads', 'sl_pks.leads_id', '=', 'sl_leads.id')
                ->where('sl_pks.status_pks_id', 7); // hanya PKS aktif

            // Search — pola yang sama dengan PksController@index.
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $searchBy = $request->get('search_by', 'nama_perusahaan');

                if ($searchBy === 'nama_perusahaan') {
                    $searchTerm = str_contains($searchTerm, ' ')
                        ? '"'.$searchTerm.'"'
                        : $searchTerm.'*';
                    $base->whereRaw('MATCH(sl_pks.nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
                } elseif (in_array($searchBy, ['nomor', 'created_by'])) {
                    $base->where("sl_pks.{$searchBy}", 'LIKE', '%'.$searchTerm.'%');
                }
            } else {
                $base->whereBetween(
                    DB::raw('DATE(COALESCE(sl_pks.tgl_pks, sl_pks.initialized_at, sl_pks.created_at))'),
                    [$tglDari, $tglSampai]
                );
            }

            if ($request->filled('branch')) {
                $base->where('sl_leads.branch_id', $request->branch);
            }

            // Rekap atas SELURUH PKS yang cocok filter — dihitung sekali,
            // dipakai untuk summary fulfillment agregat & enrich list.
            $allRows = (clone $base)->get(['sl_pks.id', 'sl_pks.quotation_id']);
            $recap = $dashboardService->recapForPage($allRows);

            // Summary = rekap fulfillment agregat (item + visit) lintas semua PKS.
            $summary = $dashboardService->aggregateSummary($recap);

            // List paginated.
            $pksList = (clone $base)
                ->select([
                    'sl_pks.id',
                    'sl_pks.leads_id',
                    'sl_pks.nomor',
                    'sl_pks.nama_perusahaan',
                    'sl_pks.quotation_id',
                    'sl_pks.status_pks_id',
                    'sl_pks.kontrak_awal',
                    'sl_pks.kontrak_akhir',
                    'sl_pks.tgl_pks',
                    'sl_pks.initialized_at',
                    'sl_pks.created_at',
                ])
                ->with([
                    'statusPks:id,nama',
                    'sites:id,pks_id,nama_site',
                ])
                ->orderBy('sl_pks.created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            $pksList->getCollection()->transform(function ($pks) use ($recap) {
                $r = $recap[$pks->id] ?? null;

                return [
                    'id' => $pks->id,
                    'nomor' => $pks->nomor,
                    'nama_perusahaan' => $pks->nama_perusahaan,
                    'status' => $pks->statusPks->nama ?? '-',
                    'status_pks_id' => $pks->status_pks_id,
                    'nama_site' => $pks->sites->pluck('nama_site')->toArray(),
                    'kontrak_awal' => $pks->getRawOriginal('kontrak_awal'),
                    'kontrak_akhir' => $pks->getRawOriginal('kontrak_akhir'),
                    'item' => $r['item'] ?? null,
                    'visit' => $r['visit'] ?? null,
                    'hc' => $r['hc'] ?? null,
                    'is_complete' => $r['is_complete'] ?? false,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'PKS fulfillment dashboard retrieved successfully',
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
            \Log::error('Error in PksFulfillmentController@dashboard: '.$e->getMessage());

            return $this->serverErrorResponse($e->getMessage());
        }
    }

    // ==================== SUMMARY ====================

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/{pks}/summary",
     *     tags={"PKS Fulfillment"},
     *     summary="Ringkasan pemenuhan per PKS (detail)",
     *     description="Rekap pemenuhan Item (overall + per site) dan Visit (target per role, jumlah jadwal per status, jadwal terdekat) untuk satu PKS. Slot training & hc menyusul.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="pks",
     *         in="path",
     *         required=true,
     *         description="PKS ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Fulfillment summary retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="pks", type="object"),
     *                 @OA\Property(property="item", type="object",
     *                     @OA\Property(property="overall", type="object"),
     *                     @OA\Property(property="per_site", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="visit", type="object",
     *                     @OA\Property(property="per_role", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="schedule_counts", type="object"),
     *                     @OA\Property(property="upcoming", type="object", nullable=true)
     *                 ),
     *                 @OA\Property(property="training", type="object", nullable=true),
     *                 @OA\Property(property="hc", type="object", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function getFulfillmentSummary(Pks $pks, PksFulfillmentSummaryService $summaryService): JsonResponse
    {
        $summary = $summaryService->build($pks);

        return $this->successResponse($summary, 'Fulfillment summary retrieved successfully.');
    }

    // ==================== HC FULFILLMENT (READ-ONLY, HRIS) ====================

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/{pks}/hc",
     *     tags={"PKS Fulfillment"},
     *     summary="Pemenuhan HC per PKS (read-only, dari HRIS)",
     *     description="Rekap pemenuhan HC/rekrutmen per lowongan (vacancy) untuk PKS: target kebutuhan vs pemanggilan/pengiriman/akumulasi & sisa outstanding. Data ditarik dari HRIS (mysqlhris), tanpa aksi tulis.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="pks", in="path", required=true, description="PKS ID", @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="HC fulfillment retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="overall", type="object",
     *                     @OA\Property(property="total_vacancy", type="integer"),
     *                     @OA\Property(property="target_kebutuhan", type="integer"),
     *                     @OA\Property(property="jumlah_pemanggilan_only", type="integer"),
     *                     @OA\Property(property="jumlah_pengiriman_only", type="integer"),
     *                     @OA\Property(property="akumulasi_pengiriman", type="integer"),
     *                     @OA\Property(property="sisa_outstanding", type="integer"),
     *                     @OA\Property(property="persen", type="number", format="float")
     *                 ),
     *                 @OA\Property(property="per_vacancy", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="PKS not found")
     * )
     */
    public function getHcFulfillment(Pks $pks, HcFulfillmentService $hcFulfillmentService): JsonResponse
    {
        $hc = $hcFulfillmentService->forPks($pks);

        return $this->successResponse($hc, 'HC fulfillment retrieved successfully.');
    }

    // ==================== ITEM FULFILLMENT ====================

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/{pks}/items",
     *     tags={"PKS Fulfillment"},
     *     summary="Get requested items and fulfillment status",
     *     description="Mengambil daftar item yang diminta beserta status pemenuhannya per site.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="pks",
     *         in="path",
     *         required=true,
     *         description="PKS ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="site_id",
     *         in="query",
     *         required=true,
     *         description="Site ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item list retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/PksFulfillmentItem")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Parameter site_id wajib diisi.")
     *         )
     *     )
     * )
     */
    public function getRequestedItems(Pks $pks, Request $request): JsonResponse
    {
        $siteId = (int) $request->query('site_id');
        if (! $siteId) {
            return $this->errorResponse('Parameter site_id wajib diisi.', 422);
        }

        $items = $this->itemFulfillmentService->getRequestedItems($pks, $siteId);

        return $this->successResponse($items, 'Item list retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/pks-fulfillment/item-fulfillment",
     *     tags={"PKS Fulfillment"},
     *     summary="Create item fulfillment session",
     *     description="Membuat sesi pemenuhan item dan mencatat log awal.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"pks_id","site_id","item_type_id","item_id","qty"},
     *
     *             @OA\Property(property="pks_id", type="integer", example=99),
     *             @OA\Property(property="site_id", type="integer", example=5),
     *             @OA\Property(property="item_type_id", type="integer", enum={1,2,3}, example=1, description="1=kaporlap, 2=device, 3=chemical"),
     *             @OA\Property(property="item_id", type="integer", example=10),
     *             @OA\Property(property="qty", type="integer", minimum=1, example=5),
     *             @OA\Property(property="catatan", type="string", nullable=true, minLength=10, example="Pengiriman batch pertama")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Fulfillment berhasil disimpan."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="pks_id", type="integer", example=99),
     *                 @OA\Property(property="qty_terpenuhi", type="integer", example=5),
     *                 @OA\Property(property="status", type="string", example="partially_fulfilled")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Quantity melebihi sisa yang belum terpenuhi.")
     *         )
     *     )
     * )
     */
    public function storeFulfillment(ItemFulfillmentStoreRequest $request): JsonResponse
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        try {
            $user = Auth::user();
            $fulfillment = $this->itemFulfillmentService->createFulfillment(
                $request->validated(),
                $user
            );

            return $this->createdResponse($fulfillment, 'Fulfillment berhasil disimpan.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pks-fulfillment/item-fulfillment/bulk",
     *     tags={"PKS Fulfillment"},
     *     summary="Create item fulfillment sessions (bulk)",
     *     description="Versi bulk dari POST /item-fulfillment. Semua item diproses dalam satu transaksi — gagal satu item, seluruh batch dibatalkan. Body boleh berupa {items:[...]} atau bare array [...]. Maksimal 100 item.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"items"},
     *
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 minItems=1,
     *                 maxItems=100,
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"pks_id","site_id","item_type_id","item_id","qty"},
     *
     *                     @OA\Property(property="pks_id", type="integer", example=99),
     *                     @OA\Property(property="site_id", type="integer", example=5),
     *                     @OA\Property(property="item_type_id", type="integer", enum={1,2,3}, example=1, description="1=kaporlap, 2=device, 3=chemical"),
     *                     @OA\Property(property="item_id", type="integer", example=10),
     *                     @OA\Property(property="qty", type="integer", minimum=1, example=5),
     *                     @OA\Property(property="catatan", type="string", nullable=true, minLength=10, example="Pengiriman batch pertama")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="3 fulfillment berhasil disimpan."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="batch_id", type="string", format="uuid", description="Penanda satu kelompok pengiriman; dipakai untuk menarik ulang log batch ini"),
     *                 @OA\Property(property="batch_ke", type="integer", nullable=true, example=3, description="Nomor urut batch dalam PKS ini. NULL bila satu batch mencakup lebih dari satu PKS"),
     *                 @OA\Property(property="batch_ke_per_pks", type="object", description="Nomor batch per pks_id,"),
     *                 @OA\Property(property="fulfillments", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function storeBulkFulfillment(ItemFulfillmentBulkStoreRequest $request): JsonResponse
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        try {
            $batch = $this->itemFulfillmentService->createBulkFulfillment(
                $request->validated('items'),
                Auth::user()
            );

            return $this->createdResponse(
                [
                    // batch_id dipakai client untuk menarik ulang satu kelompok
                    // pengiriman lewat log. batch_ke versi terbacanya: nomor urut
                    // batch dalam satu PKS. Satu batch hampir selalu satu PKS —
                    // kalau ternyata lintas PKS, nomornya beda per PKS, jadi
                    // yang tunggal dikosongkan dan yang dipakai peta di bawahnya.
                    'batch_id' => $batch['batch_id'],
                    'batch_ke' => count($batch['batch_ke']) === 1
                        ? reset($batch['batch_ke'])
                        : null,
                    'batch_ke_per_pks' => $batch['batch_ke'],
                    'fulfillments' => $batch['items'],
                ],
                count($batch['items']).' fulfillment berhasil disimpan.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pks-fulfillment/item-fulfillment/receive",
     *     tags={"PKS Fulfillment"},
     *     summary="Catat penerimaan barang di site (tahap 2)",
     *     description="Tahap kedua alur item fulfillment. qty_terpenuhi baru naik di sini, bukan saat request. Qty diterima boleh lebih kecil dari yang dikirim — selisihnya kembali menjadi sisa yang boleh di-request ulang. All-or-nothing: satu item gagal, seluruh penerimaan dibatalkan.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"items"},
     *
     *             @OA\Property(property="batch_id", type="string", format="uuid", nullable=true, description="Batasi penutupan ke satu batch pengiriman. Kosong = pengiriman paling lama ditutup lebih dulu"),
     *             @OA\Property(property="catatan", type="string", nullable=true, minLength=10, example="Satu seragam rusak saat diterima"),
     *             @OA\Property(property="items", type="array", minItems=1, maxItems=100,
     *
     *                 @OA\Items(type="object",
     *                     required={"fulfillment_id","qty"},
     *
     *                     @OA\Property(property="fulfillment_id", type="integer", example=12),
     *                     @OA\Property(property="qty", type="integer", minimum=1, example=4, description="Qty yang benar-benar diterima"),
     *                     @OA\Property(property="catatan", type="string", nullable=true, minLength=10)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="2 item berhasil diterima."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="batch_id", type="string", format="uuid"),
     *                 @OA\Property(property="batch_ke", type="integer", example=1, description="Nomor batch penerimaan; deretnya terpisah dari batch pengiriman"),
     *                 @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function receiveFulfillment(ItemFulfillmentReceiveRequest $request): JsonResponse
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        try {
            $batch = $this->itemReceivingService->receive(
                $request->validated('items'),
                Auth::user(),
                $request->validated('batch_id'),
                $request->validated('catatan'),
            );

            return $this->createdResponse(
                $batch,
                count($batch['items']).' item berhasil diterima.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/{pks}/item-request",
     *     tags={"PKS Fulfillment"},
     *     summary="Daftar permintaan barang per batch",
     *     description="Sumber data form penerimaan: barang apa saja yang sudah dikirim dan menunggu dikonfirmasi diterima. Default hanya yang berstatus open.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="pks", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="site_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="open (default) | received | short | all",
     *
     *         @OA\Schema(type="string", enum={"open","received","short","all"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PksItemRequestRow"))
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function getItemRequests(Pks $pks, Request $request): JsonResponse
    {
        $status = $request->query('status', PksItemRequest::STATUS_OPEN);

        if (! in_array($status, [PksItemRequest::STATUS_OPEN, PksItemRequest::STATUS_RECEIVED, PksItemRequest::STATUS_SHORT, 'all'], true)) {
            return $this->errorResponse('Status harus open, received, short, atau all.', 422);
        }

        $siteId = $request->query('site_id');

        return $this->successResponse(
            $this->itemReceivingService->getPksRequests(
                $pks->id,
                $siteId !== null ? (int) $siteId : null,
                $status === 'all' ? null : $status,
            ),
            'Item request retrieved successfully.'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/pks-fulfillment/item-fulfillment/{fulfillment}",
     *     tags={"PKS Fulfillment"},
     *     summary="Edit item fulfillment",
     *     description="Mengoreksi jumlah yang DITERIMA (qty_terpenuhi) dan catatannya. Hanya untuk role tertentu (cais_role_id 8/10/98).
     *
     * Koreksi tidak boleh menyerobot barang yang masih menunggu penerimaan: `qty_terpenuhi + qty_request` harus tetap <= `qty_diminta`, kalau tidak 422. Barang yang sudah sampai dicatat lewat endpoint receive, bukan lewat koreksi ini — supaya baris permintaannya ikut ditutup dan riwayat batch-nya utuh.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="fulfillment",
     *         in="path",
     *         required=true,
     *         description="PksItemFulfillment ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"new_qty","catatan"},
     *
     *             @OA\Property(property="new_qty", type="integer", minimum=1, example=10),
     *             @OA\Property(property="catatan", type="string", minLength=10, example="Revisi quantity menjadi 10 unit")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Fulfillment berhasil diupdate."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="qty_terpenuhi", type="integer", example=10),
     *                 @OA\Property(property="status", type="string", example="fully_fulfilled")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - user tidak memiliki akses",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Anda tidak memiliki akses untuk mengedit fulfillment.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Quantity melebihi sisa yang belum terpenuhi.")
     *         )
     *     )
     * )
     */
    public function editFulfillment(PksItemFulfillment $fulfillment, ItemFulfillmentEditRequest $request): JsonResponse
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        $user = Auth::user();

        try {
            $updated = $this->itemFulfillmentService->editFulfillment(
                $fulfillment,
                (int) $request->validated('new_qty'),
                $request->validated('catatan'),
                $user
            );

            return $this->successResponse($updated, 'Fulfillment berhasil diupdate.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/item-fulfillment/{fulfillment}/log",
     *     tags={"PKS Fulfillment"},
     *     summary="Get fulfillment change log",
     *     description="Mengambil riwayat perubahan (log) dari sebuah item fulfillment.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="fulfillment",
     *         in="path",
     *         required=true,
     *         description="PksItemFulfillment ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Fulfillment log retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Fulfillment not found"
     *     )
     * )
     */
    public function getFulfillmentLog(PksItemFulfillment $fulfillment): JsonResponse
    {
        $logs = $this->itemFulfillmentService->getFulfillmentLog($fulfillment->id);

        return $this->successResponse($logs, 'Fulfillment log retrieved successfully.');
    }

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/{pks}/fulfillment-log",
     *     tags={"PKS Fulfillment"},
     *     summary="Get fulfillment log per PKS (item + visit), dikelompokkan per batch",
     *     description="Log seluruh aktivitas fulfillment satu PKS, dikelompokkan per batch pengiriman, terbaru dulu. Tiap grup berisi batch_id, batch_ke, dan daftar log di dalamnya. Log lama sebelum kolom batch ada muncul sebagai grup berisi satu log dengan batch_id null. Filter opsional ?jenis=item|visit.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="pks", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="jenis", in="query", required=false, @OA\Schema(type="string", enum={"item","visit"})),
     *
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=422, description="Parameter jenis tidak valid")
     * )
     */
    public function getPksLog(Pks $pks, Request $request): JsonResponse
    {
        $jenis = $request->query('jenis');
        if ($jenis !== null && ! in_array($jenis, [PksFulfillmentLog::JENIS_ITEM, PksFulfillmentLog::JENIS_VISIT], true)) {
            return $this->errorResponse('Parameter jenis tidak valid (item / visit).', 422);
        }

        $logs = $this->fulfillmentLogService->getPksLog($pks->id, $jenis);

        return $this->successResponse($logs, 'Fulfillment log retrieved successfully.');
    }

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/fulfillment-log/batch/{batchId}",
     *     tags={"PKS Fulfillment"},
     *     summary="Detail satu batch pengiriman",
     *     description="Isi satu batch: barang apa saja yang dikirim beserta qty batch tersebut, sisa sebelum/sesudah, dan catatan. Untuk batch jenis visit yang dikembalikan adalah data kunjungannya.
     *
     * `aksi` menyatakan JENIS batch dan tidak pernah berubah — batch pengiriman selamanya `request`, penerimaannya dicatat sebagai batch terpisah beraksi `receive`. Untuk tahu apakah barang batch ini sudah diterima, baca `status_penerimaan` (batch) atau `status_penerimaan`/`received_batch_id` per item, bukan `aksi`.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="batchId", in="path", required=true, description="batch_id (UUID) dari response bulk atau dari fulfillment-log", @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="batch_id", type="string", format="uuid"),
     *                 @OA\Property(property="batch_ke", type="integer", example=3),
     *                 @OA\Property(property="jenis", type="string", enum={"item","visit"}),
     *                 @OA\Property(property="aksi", type="string", enum={"request","receive","edit"}, description="Jenis batch, bukan status. Selalu tetap."),
     *                 @OA\Property(property="pks_id", type="integer"),
     *                 @OA\Property(property="jumlah_item", type="integer"),
     *                 @OA\Property(property="status_penerimaan", type="string", enum={"belum","sebagian","selesai","selesai_kurang"}, nullable=true, description="Hanya terisi untuk batch item beraksi request. `selesai_kurang` = tidak ada lagi yang ditunggu, tapi barangnya kurang. NULL bila tidak relevan atau seluruh lognya lama tanpa tautan ke baris permintaan."),
     *                 @OA\Property(property="jumlah_diterima", type="integer", description="Baris permintaan yang sudah ditutup (received + short)"),
     *                 @OA\Property(property="jumlah_menunggu", type="integer", description="Baris permintaan yang masih open"),
     *                 @OA\Property(property="jumlah_kurang", type="integer", description="Bagian dari jumlah_diterima yang ditutup sebagai short"),
     *                 @OA\Property(property="jumlah_tanpa_tautan", type="integer", description="Item log lama yang tidak bisa ditautkan ke baris permintaan. jumlah_diterima + jumlah_menunggu + jumlah_tanpa_tautan = jumlah_item."),
     *                 @OA\Property(property="qty_kurang", type="integer", description="Total unit yang tidak jadi diterima di batch ini"),
     *                 @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Batch tidak ditemukan")
     * )
     */
    public function getBatchDetail(string $batchId): JsonResponse
    {
        $batch = $this->fulfillmentLogService->getBatchDetail($batchId);

        if ($batch === null) {
            return $this->errorResponse('Batch tidak ditemukan.', 404);
        }

        return $this->successResponse($batch, 'Batch detail retrieved successfully.');
    }

    // ==================== VISIT SCHEDULING ====================

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/{pks}/visit-schedule",
     *     tags={"PKS Fulfillment"},
     *     summary="Get visit schedule list",
     *     description="Mengambil daftar jadwal visit untuk sebuah PKS, dapat difilter berdasarkan role.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="pks",
     *         in="path",
     *         required=true,
     *         description="PKS ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         required=false,
     *         description="Filter jadwal berdasarkan role",
     *
     *         @OA\Schema(type="string", enum={"operasional","crm"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Visit schedule retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/PksVisitSchedule")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function getVisitSchedule(Pks $pks, Request $request): JsonResponse
    {
        $role = $request->query('role');
        $schedules = $this->visitFulfillmentService->getScheduleByPks($pks, $role);

        return $this->successResponse($schedules, 'Visit schedule retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/pks-fulfillment/visit-schedule",
     *     tags={"PKS Fulfillment"},
     *     summary="Create manual visit schedule",
     *     description="Membuat jadwal visit secara manual. Hanya untuk Admin / CRM Supervisor.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"pks_id","site_id","leads_id","role","pic_user_id","tgl_jadwal"},
     *
     *             @OA\Property(property="pks_id", type="integer", example=99),
     *             @OA\Property(property="site_id", type="integer", example=5),
     *             @OA\Property(property="leads_id", type="integer", example=123),
     *             @OA\Property(property="role", type="string", enum={"operasional","crm"}, example="operasional"),
     *             @OA\Property(property="pic_user_id", type="integer", example=42),
     *             @OA\Property(property="tgl_jadwal", type="string", format="date", example="2026-07-20")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Jadwal manual berhasil dibuat."),
     *             @OA\Property(property="data", ref="#/components/schemas/PksVisitSchedule")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="PIC tidak sesuai dengan role yang dipilih.")
     *         )
     *     )
     * )
     */
    public function storeManualSchedule(VisitScheduleManualStoreRequest $request): JsonResponse
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        try {
            $user = Auth::user();
            $schedule = $this->visitSchedulingService->createManualSchedule(
                $request->validated(),
                $user
            );

            return $this->createdResponse($schedule, 'Jadwal manual berhasil dibuat.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/pks-fulfillment/visit-schedule/{schedule}/reschedule",
     *     tags={"PKS Fulfillment"},
     *     summary="Reschedule visit",
     *     description="Mengubah jadwal visit yang sudah ada (reschedule). PIC atau Admin.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="schedule",
     *         in="path",
     *         required=true,
     *         description="PksVisitSchedule ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"tgl_jadwal","alasan"},
     *
     *             @OA\Property(property="tgl_jadwal", type="string", format="date", example="2026-08-01"),
     *             @OA\Property(property="alasan", type="string", example="Klien meminta perubahan jadwal")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Jadwal berhasil di-reschedule."),
     *             @OA\Property(property="data", ref="#/components/schemas/PksVisitSchedule")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Jadwal sudah dilakukan / terlewat.")
     *         )
     *     )
     * )
     */
    public function reschedule(PksVisitSchedule $schedule, VisitRescheduleRequest $request): JsonResponse
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        try {
            $user = Auth::user();
            $newDate = Carbon::parse($request->validated('tgl_jadwal'));

            $updated = $this->visitSchedulingService->reschedule(
                $schedule,
                $newDate,
                $request->validated('alasan'),
                $user
            );

            return $this->successResponse($updated, 'Jadwal berhasil di-reschedule.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    // ==================== VISIT FULFILLMENT ====================

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/{pks}/visit-target",
     *     tags={"PKS Fulfillment"},
     *     summary="Get visit target summary",
     *     description="Mengambil ringkasan target visit PKS (total target, terpakai, dan sisa).",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="pks",
     *         in="path",
     *         required=true,
     *         description="PKS ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         required=false,
     *         description="Filter target berdasarkan role",
     *
     *         @OA\Schema(type="string", enum={"operasional","crm"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Visit target retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/PksVisitTarget")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function getVisitTarget(Pks $pks, Request $request): JsonResponse
    {
        $role = $request->query('role');
        $targets = $this->visitFulfillmentService->getVisitTarget($pks, $role);

        return $this->successResponse($targets, 'Visit target retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api/pks-fulfillment/visit-record",
     *     tags={"PKS Fulfillment"},
     *     summary="Store visit record with photos",
     *     description="Mencatat hasil visit beserta foto dokumentasi (multipart/form-data).",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"pks_id","site_id","leads_id","role","tgl_visit_aktual","hasil_visit"},
     *
     *                 @OA\Property(property="schedule_id", type="integer", nullable=true, example=10),
     *                 @OA\Property(property="pks_id", type="integer", example=99),
     *                 @OA\Property(property="site_id", type="integer", example=5),
     *                 @OA\Property(property="leads_id", type="integer", example=123),
     *                 @OA\Property(property="role", type="string", enum={"operasional","crm"}, example="operasional"),
     *                 @OA\Property(property="tgl_visit_aktual", type="string", format="date", example="2026-07-14"),
     *                 @OA\Property(property="hasil_visit", type="string", enum={"selesai","ada_kendala","ditunda"}, example="selesai"),
     *                 @OA\Property(property="catatan", type="string", example="Semua item sudah terpenuhi dengan baik"),
     *                 @OA\Property(
     *                     property="fotos[]",
     *                     type="array",
     *                     description="Array file foto dokumentasi",
     *
     *                     @OA\Items(type="string", format="binary")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Hasil visit berhasil disimpan."),
     *             @OA\Property(property="data", ref="#/components/schemas/PksVisitRecord")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Schedule sudah memiliki record visit.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan: ...")
     *         )
     *     )
     * )
     */
    public function storeVisitRecord(VisitRecordStoreRequest $request): JsonResponse
    {
        if ($denied = $this->ensureCanManage()) {
            return $denied;
        }

        set_time_limit(300); // 5 menit untuk upload foto
        try {

            $user = Auth::user();
            $data = $request->validated();
            $fotos = $request->file('fotos', []);

            $record = $this->visitFulfillmentService->createVisitRecord($data, $fotos, $user);

            return $this->createdResponse($record, 'Hasil visit berhasil disimpan.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Terjadi kesalahan: '.$e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pks-fulfillment/{pks}/visit-record",
     *     tags={"PKS Fulfillment"},
     *     summary="Get visit history",
     *     description="Mengambil riwayat visit lintas role untuk sebuah PKS.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="pks",
     *         in="path",
     *         required=true,
     *         description="PKS ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Visit history retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/PksVisitRecord")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="PKS not found"
     *     )
     * )
     */
    public function getVisitHistory(Pks $pks): JsonResponse
    {
        $records = $this->visitFulfillmentService->getVisitHistory($pks);

        return $this->successResponse($records, 'Visit history retrieved successfully.');
    }

    /**
     * GET /pks-fulfillment/visit-photo/{foto}
     * Generate fresh signed URL untuk foto visit (valid 1 jam).
     * Dipakai client setiap kali mau render foto.
     */
    public function getPhotoUrl(int $foto): JsonResponse
    {
        $fotoModel = \App\Models\PksVisitRecordFoto::find($foto);

        if (! $fotoModel) {
            return $this->notFoundResponse('Foto tidak ditemukan.');
        }

        $url = \Illuminate\Support\Facades\Storage::disk('visit-photo')
            ->temporaryUrl($fotoModel->nama_file, now()->addHour());

        return $this->successResponse(['url' => $url], 'Photo URL generated.');
    }
}
