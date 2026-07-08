<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmissionIdRequest;
use App\Models\Submission;
use App\Models\Leads;
use App\Models\CustomerActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Submission",
 *     description="API untuk manajemen Submission (data form/webhook calon leads sebelum dikonversi menjadi leads)"
 * )
 */
class SubmissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/submission/list",
     *     summary="Daftar submission dengan filter",
     *     tags={"Submission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="tgl_dari", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="tgl_sampai", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="branch", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function list(Request $request)
    {
        $tglDari = $request->get('tgl_dari', Carbon::now()->startOfMonth()->subMonths(3)->toDateString());
        $tglSampai = $request->get('tgl_sampai', Carbon::now()->toDateString());

        $query = Submission::query()
                ->with([
                    'branch:id,name',
                    'platform:id,nama',
                    'statusLeads:id,nama',
                    'timSalesD:id,nama',
                ])
                ->whereNull('customer_id')
                ->whereBetween('tgl_leads', [$tglDari . ' 00:00:00', $tglSampai . ' 23:59:59']);

            if ($request->filled('branch')) {
                $query->where('branch_id', $request->branch);
            }
            if ($request->filled('platform')) {
                $query->where('platform_id', $request->platform);
            }
            if ($request->filled('status')) {
                $query->where('status_leads_id', $request->status);
            }
            if ($request->filled('search')) {
                $term = '%' . $request->search . '%';
                $searchBy = $request->get('search_by');

                $directCols = [
                    'nama_perusahaan', 'pic', 'jabatan', 'no_telp', 'email',
                    'nomor', 'notes', 'created_by',
                ];

                if ($searchBy && in_array($searchBy, $directCols, true)) {
                    $query->where($searchBy, 'LIKE', $term);
                } elseif ($searchBy === 'wilayah') {
                    $query->whereHas('branch', fn($q) => $q->where('name', 'LIKE', $term));
                } elseif ($searchBy === 'sumber_submission') {
                    $query->whereHas('platform', fn($q) => $q->where('nama', 'LIKE', $term));
                } elseif ($searchBy === 'status') {
                    $query->whereHas('statusLeads', fn($q) => $q->where('nama', 'LIKE', $term));
                } else {
                    $query->where(function ($q) use ($term, $directCols) {
                        foreach ($directCols as $col) {
                            $q->orWhere($col, 'LIKE', $term);
                        }
                        $q->orWhereHas('branch', fn($qq) => $qq->where('name', 'LIKE', $term))
                          ->orWhereHas('platform', fn($qq) => $qq->where('nama', 'LIKE', $term))
                          ->orWhereHas('statusLeads', fn($qq) => $qq->where('nama', 'LIKE', $term));
                    });
                }
            }

            $data = $query->orderBy('id', 'desc')
                ->paginate($request->get('per_page', 15));

            $data->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'nomor' => $item->nomor,
                    'wilayah' => $item->branch->name ?? null,
                    'wilayah_id' => $item->branch_id,
                    'tgl_leads' => $item->tgl_leads
                        ? Carbon::parse($item->getRawOriginal('tgl_leads'))->isoFormat('D MMMM Y')
                        : null,
                    'nama_perusahaan' => $item->nama_perusahaan,
                    'pic' => $item->pic,
                    'jabatan' => $item->jabatan,
                    'no_telp' => $item->no_telp,
                    'email' => $item->email,
                    'status' => $item->statusLeads->nama ?? null,
                    'status_leads_id' => $item->status_leads_id,
                    'sumber_submission' => $item->platform->nama ?? null,
                    'platform_id' => $item->platform_id,
                    'created_by' => $item->created_by,
                    'notes' => $item->notes,
                    'kebutuhan_id' => $item->kebutuhan_id,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data submission berhasil diambil',
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'total' => $data->total(),
                    'total_per_page' => $data->count(),
                ],
                'filter' => [
                    'tgl_dari' => $tglDari,
                    'tgl_sampai' => $tglSampai,
                    'branch' => $request->branch,
                ],
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/submission/view/{id}",
     *     summary="Detail satu submission",
     *     tags={"Submission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function view($id)
    {
        $item = Submission::with([
            'branch:id,name',
            'platform:id,nama',
            'statusLeads:id,nama',
            'timSalesD:id,nama',
        ])->find($id);

        if (! $item) {
            return $this->notFoundResponse();
        }

        return $this->successResponse($item);
    }

    /**
     * @OA\Post(
     *     path="/api/submission/convert",
     *     summary="Konversi submission terpilih menjadi leads",
     *     tags={"Submission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function convert(SubmissionIdRequest $request)
    {
        $createdLeads = DB::transaction(function () use ($request) {
            $now = Carbon::now()->toDateTimeString();
            $userName = Auth::user()->full_name ?? Auth::user()->name ?? 'system';

            $nomor = '';
            $createdLeads = [];

            foreach ($request->id as $key => $submissionId) {
                $submission = Submission::find($submissionId);
                if (!$submission || $submission->deleted_at) {
                    continue;
                }

                $nomor = $key === 0 ? $this->generateNomor() : $this->generateNomorLanjutan($nomor);

                $lead = Leads::create([
                    'nomor' => $nomor,
                    'tgl_leads' => $submission->tgl_leads,
                    'nama_perusahaan' => $submission->nama_perusahaan,
                    'branch_id' => $submission->branch_id,
                    'platform_id' => $submission->platform_id,
                    'kebutuhan_id' => $submission->kebutuhan_id,
                    'pic' => $submission->pic,
                    'jabatan' => $submission->jabatan,
                    'no_telp' => $submission->no_telp,
                    'email' => $submission->email,
                    'status_leads_id' => $submission->status_leads_id ?: 1,
                    'notes' => $submission->notes,
                    'created_by' => $userName,
                    'created_by_user_id' => Auth::id(),
                ]);

                CustomerActivity::create([
                    'leads_id' => $lead->id,
                    'branch_id' => $submission->branch_id,
                    'tgl_activity' => $now,
                    'nomor' => 'CAT/' . str_pad($lead->id, 6, '0', STR_PAD_LEFT),
                    'notes' => 'Leads terbentuk dari Submission',
                    'tipe' => 'Leads',
                    'status_leads_id' => $submission->status_leads_id ?: 1,
                    'is_activity' => 0,
                    'user_id' => Auth::id(),
                    'created_by' => $userName,
                    'created_by_user_id' => Auth::id(),
                ]);

                $submission->update([
                    'leads_id' => $lead->id,
                    'deleted_at' => $now,
                    'deleted_by' => $userName,
                ]);

                $createdLeads[] = $lead->id;
            }

            return $createdLeads;
        });

        return $this->successResponse(
            ['leads_ids' => $createdLeads],
            'Submission berhasil dikonversi menjadi leads'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/submission/delete",
     *     summary="Hapus (soft delete) submission terpilih",
     *     tags={"Submission"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id"},
     *             @OA\Property(property="id", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function delete(SubmissionIdRequest $request)
    {
        $now = Carbon::now()->toDateTimeString();
        $userName = Auth::user()->full_name ?? Auth::user()->name ?? 'system';

        Submission::whereIn('id', $request->id)->update([
            'deleted_at' => $now,
            'deleted_by' => $userName,
        ]);

        return $this->messageResponse('Submission berhasil dihapus');
    }

    private function generateNomor()
    {
        $lastLeads = Leads::latest('id')->first();
        if (!$lastLeads?->nomor) {
            return 'AAAAA';
        }

        $nomor = $lastLeads->nomor;
        $chars = str_split($nomor);

        for ($i = count($chars) - 1; $i >= 0; $i--) {
            $current = $chars[$i];
            if (is_numeric($current)) {
                if ($current < '9') {
                    $chars[$i] = (string) ($current + 1);
                    break;
                } else {
                    $chars[$i] = 'A';
                    break;
                }
            }
            if (ctype_alpha($current)) {
                if ($current < 'Z') {
                    $chars[$i] = chr(ord($current) + 1);
                    break;
                } else {
                    $chars[$i] = '0';
                    continue;
                }
            }
        }

        return str_pad(implode('', $chars), 5, 'A', STR_PAD_RIGHT);
    }

    private function generateNomorLanjutan($nomor)
    {
        $chars = str_split($nomor);
        for ($i = count($chars) - 1; $i >= 0; $i--) {
            $ascii = ord($chars[$i]);
            if (($ascii >= 48 && $ascii < 57) || ($ascii >= 65 && $ascii < 90)) {
                $ascii += 1;
            } else if ($ascii == 90) {
                $ascii = 48;
            } else {
                continue;
            }
            $nomor = substr_replace($nomor, chr($ascii), $i, 1);
            break;
        }
        if (strlen($nomor) < 5) {
            $nomor = str_pad($nomor, 5, 'A', STR_PAD_RIGHT);
        }
        return $nomor;
    }
}
