<?php

namespace App\Http\Controllers;

use App\Models\SubmissionV2;
use App\Models\Leads;
use App\Models\CustomerActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Submission V2",
 *     description="Submission V2 — data berasal dari Google Sheet, disimpan di sl_submission_v2"
 * )
 */
class SubmissionV2Controller extends Controller
{
    /**
     * Default Google Sheet source.
     * Override via env GSHEET_SUBMISSION_V2_ID & GSHEET_SUBMISSION_V2_GID.
     */
    private function sheetCsvUrl(): string
    {
        $id = env('GSHEET_SUBMISSION_V2_ID', '1P2-17PbD29p8XaRiPKpRSldNb8e1-fc6rLq8TRX3VKI');
        $gid = env('GSHEET_SUBMISSION_V2_GID', '2065310694');
        return "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}";
    }

    /**
     * @OA\Get(
     *     path="/api/submission-v2/list",
     *     summary="Daftar submission v2 dengan filter",
     *     tags={"Submission V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="tgl_dari", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="tgl_sampai", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="branch", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="platform", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="search_by", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function list(Request $request)
    {
        try {
            $tglDari = $request->get('tgl_dari', Carbon::now()->startOfMonth()->subMonths(3)->toDateString());
            $tglSampai = $request->get('tgl_sampai', Carbon::now()->toDateString());

            $query = SubmissionV2::query()
                ->with([
                    'branch:id,name',
                    'platform:id,nama',
                    'statusLeads:id,nama',
                    'kebutuhan:id,nama',
                ])
                ->whereNull('customer_id')
                ->whereBetween('tgl_leads', [$tglDari . ' 00:00:00', $tglSampai . ' 23:59:59']);

            if ($request->filled('branch'))
                $query->where('branch_id', $request->branch);
            if ($request->filled('platform'))
                $query->where('platform_id', $request->platform);
            if ($request->filled('status'))
                $query->where('status_leads_id', $request->status);

            if ($request->filled('search')) {
                $term = '%' . $request->search . '%';
                $searchBy = $request->get('search_by');

                $directCols = [
                    'nama_perusahaan',
                    'pic',
                    'jabatan',
                    'no_telp',
                    'email',
                    'nomor',
                    'notes',
                    'created_by',
                    'hc',
                    'total_invoice',
                    'wilayah_text',
                    'platform_text',
                    'kebutuhan_text',
                    'status_text',
                ];

                if ($searchBy && in_array($searchBy, $directCols, true)) {
                    $query->where($searchBy, 'LIKE', $term);
                } elseif ($searchBy === 'wilayah') {
                    $query->where(function ($q) use ($term) {
                        $q->whereHas('branch', fn($qq) => $qq->where('name', 'LIKE', $term))
                            ->orWhere('wilayah_text', 'LIKE', $term);
                    });
                } elseif ($searchBy === 'sumber_submission') {
                    $query->where(function ($q) use ($term) {
                        $q->whereHas('platform', fn($qq) => $qq->where('nama', 'LIKE', $term))
                            ->orWhere('platform_text', 'LIKE', $term);
                    });
                } elseif ($searchBy === 'status') {
                    $query->where(function ($q) use ($term) {
                        $q->whereHas('statusLeads', fn($qq) => $qq->where('nama', 'LIKE', $term))
                            ->orWhere('status_text', 'LIKE', $term);
                    });
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

            $data = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 15));

            $data->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'source_row_no' => $item->source_row_no,
                    'nomor' => $item->nomor,
                    'wilayah' => $item->branch->name ?? $item->wilayah_text,
                    'wilayah_id' => $item->branch_id,
                    'tgl_leads' => $item->tgl_leads
                        ? Carbon::parse($item->getRawOriginal('tgl_leads'))->isoFormat('D MMMM Y')
                        : null,
                    'nama_perusahaan' => $item->nama_perusahaan,
                    'pic' => $item->pic,
                    'jabatan' => $item->jabatan,
                    'no_telp' => $item->no_telp,
                    'email' => $item->email,
                    'kebutuhan' => $item->kebutuhan->nama ?? $item->kebutuhan_text,
                    'kebutuhan_id' => $item->kebutuhan_id,
                    'status' => $item->statusLeads->nama ?? $item->status_text,
                    'status_leads_id' => $item->status_leads_id,
                    'sumber_submission' => $item->platform->nama ?? $item->platform_text,
                    'platform_id' => $item->platform_id,
                    'hc' => $item->hc,
                    'total_invoice' => $item->total_invoice,
                    'created_by' => $item->created_by,
                    'notes' => $item->notes,
                    'synced_at' => $item->synced_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data submission v2 berhasil diambil',
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
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function view($id)
    {
        try {
            $item = SubmissionV2::with([
                'branch:id,name',
                'platform:id,nama',
                'statusLeads:id,nama',
                'kebutuhan:id,nama',
            ])->findOrFail($id);
            return response()->json(['success' => true, 'data' => $item]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
   * @OA\Post(
   *     path="/api/submission-v2/convert",
   *     summary="Konversi submission v2 terpilih menjadi leads",
   *     tags={"Submission V2"},
   *     security={{"bearerAuth":{}}},

   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"id"},
   *             @OA\Property(
   *                 property="id",
   *                 type="array",
   *                 @OA\Items(type="integer")
   *             )
   *         )
   *     ),

   *     @OA\Response(
   *         response=200,
   *         description="Submission berhasil dikonversi"
   *     ),

   *     @OA\Response(
   *         response=401,
   *         description="Unauthorized"
   *     ),

   *     @OA\Response(
   *         response=422,
   *         description="Validation error"
   *     )
   * )
   */
    public function convert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|array|min:1',
            'id.*' => 'integer|exists:sl_submission_v2,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            DB::beginTransaction();
            $now = Carbon::now()->toDateTimeString();
            $userName = Auth::user()->full_name ?? Auth::user()->name ?? 'system';

            $nomor = '';
            $createdLeads = [];

            foreach ($request->id as $key => $submissionId) {
                $submission = SubmissionV2::find($submissionId);
                if (!$submission || $submission->deleted_at)
                    continue;

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
                ]);

                CustomerActivity::create([
                    'leads_id' => $lead->id,
                    'branch_id' => $submission->branch_id,
                    'tgl_activity' => $now,
                    'nomor' => 'CAT/' . str_pad($lead->id, 6, '0', STR_PAD_LEFT),
                    'notes' => 'Leads terbentuk dari Submission V2',
                    'tipe' => 'Leads',
                    'status_leads_id' => $submission->status_leads_id ?: 1,
                    'is_activity' => 0,
                    'user_id' => Auth::id(),
                    'created_by' => $userName,
                ]);

                $submission->update([
                    'leads_id' => $lead->id,
                    'deleted_at' => $now,
                    'deleted_by' => $userName,
                ]);

                $createdLeads[] = $lead->id;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Submission V2 berhasil dikonversi menjadi leads',
                'data' => ['leads_ids' => $createdLeads],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|array|min:1',
            'id.*' => 'integer|exists:sl_submission_v2,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            DB::beginTransaction();
            $now = Carbon::now()->toDateTimeString();
            $userName = Auth::user()->full_name ?? Auth::user()->name ?? 'system';

            SubmissionV2::whereIn('id', $request->id)->update([
                'deleted_at' => $now,
                'deleted_by' => $userName,
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Submission V2 berhasil dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/submission-v2/sync",
     *     summary="Sync data submission v2 dari Google Sheet",
     *     description="Tarik CSV dari Google Sheet publik, parse, dan upsert ke sl_submission_v2 berdasarkan source_row_no.",
     *     tags={"Submission V2"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function sync(Request $request)
    {
        try {
            $url = $request->get('url', $this->sheetCsvUrl());
            $resp = Http::timeout(60)->get($url);
            if (!$resp->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data dari Google Sheet (HTTP ' . $resp->status() . ')',
                ], 502);
            }

            $rows = $this->parseCsv($resp->body());
            if (empty($rows)) {
                return response()->json(['success' => false, 'message' => 'Sheet kosong'], 422);
            }

            // Header detection: first row contains headers
            $header = array_map(fn($h) => $this->normalizeHeader($h), $rows[0]);
            $dataRows = array_slice($rows, 1);

            // Cache lookups
            $branches = DB::connection('mysqlhris')->table('m_branch')->where('is_active', 1)->get(['id', 'name']);
            $platforms = DB::table('m_platform')->whereNull('deleted_at')->get(['id', 'nama']);
            $kebutuhans = DB::table('m_kebutuhan')->whereNull('deleted_at')->get(['id', 'nama']);
            $statuses = DB::table('m_status_leads')->get(['id', 'nama']);

            // Selalu pakai 'sync' biar konsisten — siapa yang trigger di-track via synced_at
            $userName = 'sync';
            $now = Carbon::now()->toDateTimeString();

            $inserted = 0;
            $updated = 0;
            $restored = 0;
            $softDeleted = 0;
            $skipped = 0;
            $errors = [];
            $seenRowNos = [];

            DB::beginTransaction();
            foreach ($dataRows as $idx => $cols) {
                $row = $this->mapRowByHeader($header, $cols);

                // Skip jika baris kosong total
                if (empty(array_filter($row))) {
                    $skipped++;
                    continue;
                }

                // source_row_no = absolute CSV row position (1 = header, jadi data mulai 2)
                $sourceRowNo = $idx + 2;

                // skip kalau gak ada data inti
                if (empty($row['nama'] ?? null) && empty($row['nama_perusahaan'] ?? null) && empty($row['email'] ?? null) && empty($row['nomor_telpon'] ?? null)) {
                    $skipped++;
                    continue;
                }

                $tgl = $this->parseDate($row['tanggal'] ?? null);
                $branchId = $this->matchBranch($branches, $row['wilayah'] ?? null);
                $platformId = $this->matchPlatform($platforms, $row['platform'] ?? null);
                $kebutuhanId = $this->matchKebutuhan($kebutuhans, $row['kebutuhan'] ?? null);
                $statusId = $this->matchStatus($statuses, $row['status'] ?? null) ?: 1;

                $payload = [
                    'tgl_leads' => $tgl,
                    'nama_perusahaan' => $row['nama_perusahaan'] ?? null,
                    'pic' => $row['nama'] ?? null,
                    'jabatan' => $row['jabatan'] ?? null,
                    'no_telp' => $this->cleanPhone($row['nomor_telpon'] ?? null),
                    'email' => $row['email'] ?? null,
                    'branch_id' => $branchId,
                    'platform_id' => $platformId,
                    'kebutuhan_id' => $kebutuhanId,
                    'status_leads_id' => $statusId,
                    'hc' => $row['hc'] ?? null,
                    'total_invoice' => $row['total_invoice_revenue'] ?? null,
                    'notes' => $row['keterangan'] ?? null,
                    'wilayah_text' => $row['wilayah'] ?? null,
                    'platform_text' => $row['platform'] ?? null,
                    'kebutuhan_text' => $row['kebutuhan'] ?? null,
                    'status_text' => $row['status'] ?? null,
                    'synced_at' => $now,
                    'updated_at' => $now,
                    'updated_by' => $userName,
                ];

                $seenRowNos[] = $sourceRowNo;

                $existing = SubmissionV2::withTrashed()->where('source_row_no', $sourceRowNo)->first();

                if ($existing) {
                    // Sudah dikonversi user → jangan utak-atik
                    if ($existing->leads_id || $existing->customer_id) {
                        $skipped++;
                        continue;
                    }

                    // Sebelumnya ke-soft-delete oleh sync (atau dihapus manual user) → restore
                    if ($existing->deleted_at) {
                        $payload['deleted_at'] = null;
                        $payload['deleted_by'] = null;
                        $existing->update($payload);
                        $restored++;
                    } else {
                        $existing->update($payload);
                        $updated++;
                    }
                } else {
                    SubmissionV2::create(array_merge($payload, [
                        'source_row_no' => $sourceRowNo,
                        'created_at' => $now,
                        'created_by' => $userName,
                    ]));
                    $inserted++;
                }
            }

            // Soft delete row yang sudah ilang dari sheet (kecuali yang sudah dikonversi user)
            if (!empty($seenRowNos)) {
                $softDeleted = SubmissionV2::whereNotIn('source_row_no', $seenRowNos)
                    ->whereNull('leads_id')
                    ->whereNull('customer_id')
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => $now,
                        'deleted_by' => 'sync (removed from sheet)',
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Sync selesai. inserted={$inserted}, updated={$updated}, restored={$restored}, soft_deleted={$softDeleted}, skipped={$skipped}",
                'data' => compact('inserted', 'updated', 'restored', 'softDeleted', 'skipped', 'errors'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ---------- helpers ---------- */

    private function parseCsv(string $body): array
    {
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body); // strip BOM
        $rows = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $body);
        rewind($stream);
        while (($r = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            $rows[] = $r;
        }
        fclose($stream);
        return $rows;
    }

    private function normalizeHeader(string $h): string
    {
        $h = trim($h);
        $h = strtolower($h);
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        $h = trim($h, '_');
        return $h;
    }

    private function mapRowByHeader(array $header, array $cols): array
    {
        $row = [];
        foreach ($header as $i => $key) {
            if ($key === '' || $key === null)
                continue;
            $row[$key] = isset($cols[$i]) ? trim((string) $cols[$i]) : null;
        }
        return $row;
    }

    private function parseInt($v): ?int
    {
        if ($v === null || $v === '')
            return null;
        if (preg_match('/\d+/', (string) $v, $m))
            return (int) $m[0];
        return null;
    }

    private function parseDate(?string $v): ?string
    {
        if (!$v)
            return null;
        $v = trim($v);

        // Sheet pakai format Indonesia: D/M/Y atau D/M/YY (e.g. "29/1/26", "5/1/26")
        // Prioritaskan D/M dulu — kalau slot pertama > 12 jelas itu day bukan month
        foreach (['j/n/y', 'j/n/Y', 'd/m/y', 'd/m/Y', 'd-m-Y', 'Y-m-d'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $v);
                if ($d && $d->year > 2000 && $d->year < 2100) {
                    return $d->toDateString();
                }
            } catch (\Exception $e) {
                // try next
            }
        }

        // Fallback: tebak dengan Carbon dengan asumsi DMY (Indonesian locale)
        try {
            return Carbon::parse($v, null)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function cleanPhone(?string $v): ?string
    {
        if (!$v)
            return null;
        $v = trim(preg_replace('/\s+/', ' ', $v));
        // Ambil baris pertama kalau multi-line
        $first = explode("\n", $v)[0] ?? $v;
        return trim($first);
    }

    private function matchBranch($branches, ?string $text): ?int
    {
        if (!$text)
            return null;
        $needle = strtolower(trim($text));
        foreach ($branches as $b) {
            $name = strtolower($b->name);
            if ($needle === $name)
                return (int) $b->id;
        }
        foreach ($branches as $b) {
            $name = strtolower($b->name);
            if (str_starts_with($needle, $name) || str_contains($needle, $name)) {
                return (int) $b->id;
            }
        }
        return null;
    }

    private function matchPlatform($platforms, ?string $text): ?int
    {
        if (!$text)
            return null;
        $needle = strtolower(trim($text));
        foreach ($platforms as $p) {
            if ($needle === strtolower($p->nama))
                return (int) $p->id;
        }
        return null;
    }

    private function matchKebutuhan($kebutuhans, ?string $text): ?int
    {
        if (!$text)
            return null;
        $needle = strtolower(trim($text));
        $aliases = [
            'security guard' => 'security',
            'security' => 'security',
            'labor supply' => 'labour supply',
            'labour supply' => 'labour supply',
            'cleaning service' => 'cleaning',
            'cleaning' => 'cleaning',
            'logistic' => 'logistic',
            'logistik' => 'logistic',
        ];
        $target = $aliases[$needle] ?? $needle;
        foreach ($kebutuhans as $k) {
            if (strtolower($k->nama) === $target)
                return (int) $k->id;
        }
        foreach ($kebutuhans as $k) {
            if (str_contains($target, strtolower($k->nama)))
                return (int) $k->id;
        }
        return null;
    }

    private function matchStatus($statuses, ?string $text): ?int
    {
        if (!$text)
            return null;
        $needle = strtolower(trim($text));
        foreach ($statuses as $s) {
            if ($needle === strtolower($s->nama))
                return (int) $s->id;
        }
        return null;
    }

    /* ---------- nomor leads helpers (sama dengan SubmissionController) ---------- */

    private function generateNomor()
    {
        $lastLeads = Leads::latest('id')->first();
        if (!$lastLeads?->nomor)
            return 'AAAAA';

        $nomor = $lastLeads->nomor;
        $chars = str_split($nomor);
        for ($i = count($chars) - 1; $i >= 0; $i--) {
            $current = $chars[$i];
            if (is_numeric($current)) {
                if ($current < '9') {
                    $chars[$i] = (string) ($current + 1);
                    break;
                }
                $chars[$i] = 'A';
                break;
            }
            if (ctype_alpha($current)) {
                if ($current < 'Z') {
                    $chars[$i] = chr(ord($current) + 1);
                    break;
                }
                $chars[$i] = '0';
                continue;
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
        if (strlen($nomor) < 5)
            $nomor = str_pad($nomor, 5, 'A', STR_PAD_RIGHT);
        return $nomor;
    }
}
