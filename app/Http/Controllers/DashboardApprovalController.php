<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quotation;
use App\Models\LogNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Dashboard Approval",
 *     description="API untuk data dashboard approval quotation"
 * )
 */
class DashboardApprovalController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/dashboard-approval/list",
     *     tags={"Dashboard Approval"},
     *     summary="Get list dashboard approval data",
     *     description="Mengambil data quotation untuk dashboard approval berdasarkan role user dan filter tipe",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="tipe",
     *         in="query",
     *         description="Tipe filter data",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"menunggu-anda", "menunggu-approval", "quotation-belum-lengkap"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="step", type="integer", example=100),
     *                     @OA\Property(property="top", type="string", example="Lebih Dari 7 Hari"),
     *                     @OA\Property(property="ot3", type="string", example=null),
     *                     @OA\Property(property="ot2", type="string", example=null),
     *                     @OA\Property(property="ot1", type="string", example=null),
     *                     @OA\Property(property="status", type="string", example="Menunggu Approval"),
     *                     @OA\Property(property="is_aktif", type="integer", example=0),
     *                     @OA\Property(property="quotation_id", type="integer", example=1),
     *                     @OA\Property(property="jenis_kontrak", type="string", example="Kontrak Baru"),
     *                     @OA\Property(property="company", type="string", example="PT ABC"),
     *                     @OA\Property(property="kebutuhan", type="string", example="Security"),
     *                     @OA\Property(property="created_by", type="string", example="John Doe"),
     *                     @OA\Property(property="leads_id", type="integer", example=1),
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nomor", type="string", example="QUO/2023/001"),
     *                     @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia"),
     *                     @OA\Property(property="tgl_quotation", type="string", example="01-01-2023"),
     *                     @OA\Property(property="tgl", type="string", example="1 Januari 2023"),
     *                     @OA\Property(
     *                         property="sites",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="nama_site", type="string", example="Site A")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=5),
     *             @OA\Property(
     *                 property="pending_approval_summary",
     *                 type="object",
     *                 description="Jumlah quotation yang belum diapprove per role",
     *                 @OA\Property(property="role_96", type="integer", example=3, description="Quotation menunggu approval Manager (role 96)"),
     *                 @OA\Property(property="role_97_40", type="integer", example=5, description="Quotation menunggu approval Director (role 97 & 40)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid parameter")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function getListDashboardApprovalData(Request $request): JsonResponse
    {
        $request->validate([
            'tipe' => 'nullable|in:menunggu-anda,menunggu-approval,quotation-belum-lengkap'
        ]);

        $user = Auth::user();

        // Base query factory — kondisi dasar yang berlaku untuk semua keperluan
        $baseQuery = fn() => Quotation::query()
            ->where('is_aktif', 0)
            ->whereHas('leads', fn($q) => $q->whereNull('deleted_at'))
            ->byUserRole($user);

        // ---------------------------------------------------------------
        // Semua count dihitung sekaligus SEBELUM switch menggunakan
        // query COUNT() ringan — tanpa fetch record ke PHP.
        // Frontend cukup panggil 1x API dan baca key yang dibutuhkan.
        // ---------------------------------------------------------------
        $counts = $this->getAllCaseCounts($baseQuery, $user);
        $pendingSummary = $this->getPendingApprovalSummary($baseQuery);

        // Query list data (dengan select & eager load)
        $query = $baseQuery()
            ->select([
                'id',
                'step',
                'top',
                'ot3',
                'ot2',
                'ot1',
                'is_aktif',
                'jenis_kontrak',
                'company',
                'kebutuhan',
                'created_by',
                'leads_id',
                'nomor',
                'nama_perusahaan',
                'tgl_quotation',
                'status_quotation_id',
            ])
            ->with([
                'statusQuotation:id,nama',
                'quotationSites:id,quotation_id,nama_site',
            ])
            ->orderBy('id', 'desc');

        // Apply filter tambahan berdasarkan tipe — hanya memengaruhi $data
        switch ($request->tipe) {
            case 'menunggu-anda':
                $this->applyMenungguAndaFilter($query, $user);
                break;

            case 'menunggu-approval':
                $query->where('step', 100)
                    ->where('is_aktif', 0)
                    ->where('status_quotation_id', 2)
                    ->where(function ($q) {
                        $q->whereNull('ot1')
                            ->orWhere(function ($subQ) {
                                $subQ->whereNull('ot2')
                                    ->where('top', 'Lebih Dari 7 Hari');
                            });
                    });
                break;
            case 'quotation-belum-lengkap':
                $query->where('step', '!=', 100)
                    ->where('status_quotation_id', 1);
                break;

            // default: tidak ada filter tambahan, ambil semua dari base query
        }

        $data = $query->get()->map(fn($q) => $this->transformQuotationData($q));

        return response()->json([
            'success' => true,
            'data' => $data,
            'counts' => $counts,
            'pending_approval_summary' => $pendingSummary,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard-approval/notifications",
     *     tags={"Dashboard Approval"},
     *     summary="Get notifications for dashboard approval",
     *     description="Mengambil notifikasi approval quotation untuk user yang sedang login",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="is_read",
     *         in="query",
     *         description="Filter berdasarkan status baca (0=unread, 1=read)",
     *         required=false,
     *         @OA\Schema(type="integer", enum={0, 1})
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Jumlah notifikasi yang ditampilkan",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         description="Offset untuk pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="notifications",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="tabel", type="string", example="sl_quotation"),
     *                         @OA\Property(property="doc_id", type="integer", example=123),
     *                         @OA\Property(property="transaksi", type="string", example="Quotation"),
     *                         @OA\Property(property="pesan", type="string", example="Quotation dengan nomor: QUO/2023/001 di approve oleh John Doe"),
     *                         @OA\Property(property="is_read", type="boolean", example=false),
     *                         @OA\Property(property="created_at", type="string", example="2023-01-01T10:00:00.000000Z"),
     *                         @OA\Property(property="created_by", type="string", example="John Doe"),
     *                         @OA\Property(property="time_ago", type="string", example="2 jam yang lalu"),
     *                         @OA\Property(
     *                             property="quotation",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=123),
     *                             @OA\Property(property="nomor", type="string", example="QUO/2023/001"),
     *                             @OA\Property(property="nama_perusahaan", type="string", example="PT ABC Indonesia")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="unread_count", type="integer", example=5),
     *                 @OA\Property(property="total", type="integer", example=25)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function getNotifications(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'is_read' => 'nullable|in:0,1',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            $user = Auth::user();
            $limit = $request->input('limit', 10);
            $offset = $request->input('offset', 0);

            $query = LogNotification::where('user_id', $user->id)
                ->where('tabel', 'sl_quotation')
                ->orderBy('created_at', 'desc');

            if ($request->has('is_read')) {
                $query->where('is_read', (bool) $request->input('is_read'));
            }

            // Jalankan count & unread secara paralel (2 query ringan, tanpa fetch semua data)
            $total = $query->count();
            $unreadCount = LogNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            $notifications = $query->skip($offset)->take($limit)->get();

            $transformedNotifications = $notifications->map(fn($n) => [
                'id' => $n->id,
                'tabel' => $n->tabel,
                'doc_id' => $n->doc_id,
                'transaksi' => $n->transaksi,
                'pesan' => $n->pesan,
                'is_read' => (bool) $n->is_read,
                'created_at' => $n->created_at,
                'created_by' => $n->created_by,
                'time_ago' => $n->created_at?->diffForHumans(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => $transformedNotifications,
                    'unread_count' => $unreadCount,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('getNotifications error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan, silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/dashboard-approval/notifications/{id}/read",
     *     tags={"Dashboard Approval"},
     *     summary="Mark notification as read",
     *     description="Menandai notifikasi sebagai sudah dibaca",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID notifikasi",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notification marked as read"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="is_read", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Notification not found")
     *         )
     *     )
     * )
     */
    public function markAsRead($id): JsonResponse
    {
        $user = Auth::user();

        $notification = LogNotification::forUser($user->id)
            ->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsRead($user->full_name);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => [
                'id' => $notification->id,
                'is_read' => $notification->is_read
            ]
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/dashboard-approval/notifications/read-all",
     *     tags={"Dashboard Approval"},
     *     summary="Mark all notifications as read",
     *     description="Menandai semua notifikasi sebagai sudah dibaca",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All notifications marked as read"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="updated_count", type="integer", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        $updatedCount = LogNotification::forUser($user->id)
            ->unread(true)
            ->update([
                'is_read' => true,
                'updated_at' => now(),
                'updated_by' => $user->full_name
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'updated_count' => $updatedCount
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard-approval/notifications/unread-count",
     *     tags={"Dashboard Approval"},
     *     summary="Get unread notifications count",
     *     description="Mengambil jumlah notifikasi yang belum dibaca",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="unread_count", type="integer", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function getUnreadCount(): JsonResponse
    {
        $user = Auth::user();
        $unreadCount = LogNotification::getUnreadCount($user->id);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unreadCount
            ]
        ]);
    }
    private function getAllCaseCounts(\Closure $baseQuery, $user): array
    {
        // semua  → base query tanpa filter tambahan
        $countSemua = $baseQuery()->count();

        // menunggu-anda → step 100 + kondisi per role
        $qMenungguAnda = $baseQuery()->where('step', 100);
        $this->applyMenungguAndaFilter($qMenungguAnda, $user);
        $countMenungguAnda = $qMenungguAnda->count();

        // menunggu-approval → total semua quotation yang menunggu approval (dir-sales + dir-keu)
        $baseConditions = $baseQuery()
            ->where('is_aktif', 0)
            ->where('status_quotation_id', 2)
            ->where('step', 100);

        $countDirSales = (clone $baseConditions)->whereNull('ot1')->count();
        $countDirKeu = (clone $baseConditions)->whereNull('ot2')->where('top', 'Lebih Dari 7 Hari')->count();

        $countMenungguApproval = $countDirSales + $countDirKeu;

        // quotation-belum-lengkap → step belum 100 + status belum lengkap
        $countBelumLengkap = $baseQuery()
            ->where('step', '!=', 100)
            ->where('status_quotation_id', 1)
            ->count();

        return [
            'semua' => $countSemua,
            'menunggu_anda' => $countMenungguAnda,
            'menunggu_approval' => $countMenungguApproval,
            'quotation_belum_lengkap' => $countBelumLengkap,
        ];
    }

    private function getPendingApprovalSummary(\Closure $baseQuery): array
    {
        $baseConditions = fn() => $baseQuery()
            ->where('is_aktif', 0)
            ->where('status_quotation_id', 2)
            ->where('step', 100);

        $role96Count = (clone $baseConditions())
            ->whereNull('ot1')
            ->count();

        $role9740Count = (clone $baseConditions())
            ->whereNull('ot2')
            ->where('top', 'Lebih Dari 7 Hari')
            ->count();

        return [
            'dir_sales' => $role96Count,
            'dir_keu' => $role9740Count,
        ];
    }

    /**
     * Apply filter untuk tipe "menunggu-anda" berdasarkan role user
     */
    private function applyMenungguAndaFilter($query, $user): void
    {
        $query->where('step', 100)
            ->where('is_aktif', 0)
            ->where('status_quotation_id', 2);

        // Build conditions berdasarkan role
        $conditions = [];

        if ($user->cais_role_id == 96) {
            $conditions[] = function ($q) {
                $q->whereNull('ot1');
            };
        }

        if (in_array($user->cais_role_id, [97, 40])) {
            $conditions[] = function ($q) {
                $q->whereNull('ot2')
                    ->where('top', 'Lebih Dari 7 Hari');
            };
        }

        if ($user->cais_role_id == 99) {
            $conditions[] = function ($q) {
                $q->whereNotNull('ot1')
                    ->whereNotNull('ot2')
                    ->whereNull('ot3')
                    ->where('top', 'Lebih Dari 7 Hari');
            };
        }

        // Jika user tidak memiliki role yang relevan, tidak ada data yang ditampilkan
        if (empty($conditions)) {
            $query->whereRaw('1 = 0'); // Always false
            return;
        }

        // Apply OR conditions untuk berbagai role
        $query->where(function ($q) use ($conditions) {
            foreach ($conditions as $condition) {
                $q->orWhere($condition);
            }
        });
    }

    /**
     * Transform quotation data untuk response
     */
    private function transformQuotationData($quotation): array
    {
        // Format tanggal menggunakan accessor yang sudah ada di model
        $tglFormatted = $quotation->tgl_quotation
            ? Carbon::createFromFormat('d-m-Y', $quotation->tgl_quotation)->isoFormat('D MMMM Y')
            : null;

        return [
            'step' => $quotation->step,
            'top' => $quotation->top,
            'ot3' => $quotation->ot3,
            'ot2' => $quotation->ot2,
            'ot1' => $quotation->ot1,
            'status' => $quotation->statusQuotation->nama ?? null,
            'is_aktif' => $quotation->is_aktif,
            'quotation_id' => $quotation->id,
            'jenis_kontrak' => $quotation->jenis_kontrak,
            'company' => $quotation->company,
            'kebutuhan' => $quotation->kebutuhan,
            'created_by' => $quotation->created_by,
            'leads_id' => $quotation->leads_id,
            'id' => $quotation->id,
            'nomor' => $quotation->nomor,
            'nama_perusahaan' => $quotation->nama_perusahaan,
            'tgl_quotation' => $quotation->tgl_quotation,
            'tgl' => $tglFormatted,
            'sites' => $quotation->quotationSites->map(function ($site) {
                return [
                    'nama_site' => $site->nama_site
                ];
            })
        ];
    }

    /**
     * Transform notification data untuk response
     */
    private function transformNotificationData($notification): array
    {
        $data = [
            'id' => $notification->id,
            'tabel' => $notification->tabel,
            'doc_id' => $notification->doc_id,
            'transaksi' => $notification->transaksi,
            'pesan' => $notification->pesan,
            'is_read' => $notification->is_read,
            'created_at' => $notification->created_at,
            'created_by' => $notification->created_by,
            'time_ago' => $notification->created_at->diffForHumans(),
        ];

        // Tambahkan info quotation jika ada
        if ($notification->tabel === 'sl_quotation' && $notification->doc_id) {
            $quotation = Quotation::select('id', 'nomor', 'nama_perusahaan')
                ->find($notification->doc_id);

            if ($quotation) {
                $data['quotation'] = [
                    'id' => $quotation->id,
                    'nomor' => $quotation->nomor,
                    'nama_perusahaan' => $quotation->nama_perusahaan
                ];
            }
        }

        return $data;
    }
}