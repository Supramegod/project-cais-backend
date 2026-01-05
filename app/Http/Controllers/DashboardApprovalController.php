<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quotation;
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
     *                     @OA\Property(property="nama_site", type="string", example="Site A<br />Site B")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=5)
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
        // Validasi parameter tipe
        $request->validate([
            'tipe' => 'nullable|in:menunggu-anda,menunggu-approval,quotation-belum-lengkap'
        ]);

        $user = Auth::user();

        // Build query dengan select yang spesifik untuk mengurangi data yang diambil
        $query = Quotation::query()
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
                'status_quotation_id'
            ])
            ->with([
                'statusQuotation:id,nama',
                'quotationSites:id,quotation_id,nama_site'
            ])
            ->where('is_aktif', 0)
            ->whereHas('leads', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->byUserRole($user);

        // Filter berdasarkan tipe dengan kondisi di database (lebih optimal)
        switch ($request->tipe) {
            case 'menunggu-anda':
                $query->where('status_quotation_id', 2);
                $this->applyMenungguAndaFilter($query, $user);
                break;

            case 'menunggu-approval':
                $query->where('status_quotation_id', 2)
                    ->where('step', 100);
                break;

            case 'quotation-belum-lengkap':
                $query->where('step', '!=', 100);
                break;

            default:
                // Jika tidak ada tipe, return semua data yang tidak aktif dengan status_quotation_id = 2
                $query->where('status_quotation_id', 2);
                break;
        }

        // Eksekusi query dan transform data
        $data = $query->get()->map(function ($quotation) {
            return $this->transformQuotationData($quotation);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $data->count()
        ]);
    }

    /**
     * Apply filter untuk tipe "menunggu-anda" berdasarkan role user
     */
    private function applyMenungguAndaFilter($query, $user): void
    {
        $query->where('step', 100);

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
}