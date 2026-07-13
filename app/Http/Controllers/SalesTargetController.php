<?php

namespace App\Http\Controllers;

use App\Models\SalesTarget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesTargetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Sales Target",
 *     description="CRUD endpoint untuk manajemen KPI Sales Target"
 * )
 */
class SalesTargetController extends Controller
{
    // ─── Constants ────────────────────────────────────────────────────────────

    private const PER_PAGE_DEFAULT = 15;
    private const PER_PAGE_MAX     = 100;

    // ─── CREATE ───────────────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/sales-target",
     *     summary="Buat target penjualan baru",
     *     tags={"Sales Target"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"type","period_type","year","target_amount"},
     *             @OA\Property(property="type", type="string", enum={"personal","branch","company"}),
     *             @OA\Property(property="period_type", type="string", enum={"monthly","yearly"}),
     *             @OA\Property(property="year", type="integer", example=2025),
     *             @OA\Property(property="month", type="integer", example=6, nullable=true),
     *             @OA\Property(property="target_amount", type="number", example=50000000),
     *             @OA\Property(property="user_id", type="string", nullable=true),
     *             @OA\Property(property="branch_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Target berhasil dibuat"),
     *     @OA\Response(response=422, description="Validasi gagal"),
     *     @OA\Response(response=409, description="Target sudah ada")
     * )
     */
    public function store(SalesTargetRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Cek duplikasi sebelum insert agar pesan error lebih jelas
        if ($this->isDuplicate($validated)) {
            return $this->errorResponse('Target untuk periode & tipe yang sama sudah ada.', 409);
        }

        $target = SalesTarget::create($validated);
        $target->load(['user', 'branch']);

        return $this->createdResponse($this->transform($target), 'Target berhasil dibuat.');
    }

    // ─── READ (list) ──────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/sales-target",
     *     summary="Daftar target penjualan",
     *     tags={"Sales Target"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="year",      in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month",     in="query", @OA\Schema(type="integer", minimum=1, maximum=12)),
     *     @OA\Parameter(name="type",      in="query", @OA\Schema(type="string", enum={"personal","branch","company"})),
     *     @OA\Parameter(name="user_id",   in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page",  in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="OK")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'year'      => 'nullable|integer|min:2000|max:2100',
            'month'     => 'nullable|integer|between:1,12',
            'type'      => 'nullable|in:personal,branch,company',
            'user_id'   => 'nullable|string',
            'branch_id' => 'nullable|integer',
            'per_page'  => 'nullable|integer|min:1|max:' . self::PER_PAGE_MAX,
        ]);

        $perPage = (int) $request->get('per_page', self::PER_PAGE_DEFAULT);

        $targets = SalesTarget::with(['user:id,full_name', 'branch:id,name'])
            ->when($request->year,      fn ($q) => $q->forYear($request->year))
            ->when($request->month,     fn ($q) => $q->forMonth($request->month))
            ->when($request->type,      fn ($q) => $q->ofType($request->type))
            ->when($request->user_id,   fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate($perPage);

        return $this->successResponse($targets->through(fn ($t) => $this->transform($t)));
    }

    // ─── READ (single) ────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/sales-target/{id}",
     *     summary="Detail target penjualan",
     *     tags={"Sales Target"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $target = SalesTarget::with(['user:id,full_name', 'branch:id,name'])->find($id);

        if (!$target) {
            return $this->errorResponse('Target tidak ditemukan.', 404);
        }

        return $this->successResponse($this->transform($target));
    }

    // ─── UPDATE ───────────────────────────────────────────────────────────────

    /**
     * @OA\Put(
     *     path="/api/sales-target/{id}",
     *     summary="Update target penjualan",
     *     tags={"Sales Target"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="target_amount", type="number", example=75000000)
     *         )
     *     ),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(SalesTargetRequest $request, int $id): JsonResponse
    {
        $target = SalesTarget::find($id);

        if (!$target) {
            return $this->errorResponse('Target tidak ditemukan.', 404);
        }

        $validated = $request->validated();

        // Cek duplikasi hanya jika key fields berubah
        $keyChanged = collect(['type', 'user_id', 'branch_id', 'year', 'month', 'period_type'])
            ->contains(fn ($field) => array_key_exists($field, $validated)
                && $validated[$field] != $target->{$field});

        if ($keyChanged && $this->isDuplicate($validated, $target->id)) {
            return $this->errorResponse('Target untuk periode & tipe yang sama sudah ada.', 409);
        }

        $target->update($validated);
        $target->load(['user:id,full_name', 'branch:id,name']);

        return $this->successResponse($this->transform($target), 'Target berhasil diupdate.');
    }

    // ─── DELETE ───────────────────────────────────────────────────────────────

    /**
     * @OA\Delete(
     *     path="/api/sales-target/{id}",
     *     summary="Hapus target penjualan",
     *     tags={"Sales Target"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $target = SalesTarget::find($id);

        if (!$target) {
            return $this->errorResponse('Target tidak ditemukan.', 404);
        }

        $target->delete();

        return $this->messageResponse('Target berhasil dihapus.');
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Cek apakah sudah ada target dengan key yang sama.
     */
    private function isDuplicate(array $data, ?int $ignoreId = null): bool
    {
        $query = SalesTarget::where('type',        $data['type']        ?? null)
            ->where('period_type', $data['period_type'] ?? null)
            ->where('year',        $data['year']        ?? null)
            ->where('month',       $data['month']       ?? null)
            ->where('user_id',     $data['user_id']     ?? null)
            ->where('branch_id',   $data['branch_id']   ?? null);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Transformer: ubah model menjadi array response.
     */
    private function transform(SalesTarget $target): array
    {
        return [
            'id'                     => $target->id,
            'type'                   => $target->type,
            'period_type'            => $target->period_type,
            'year'                   => $target->year,
            'month'                  => $target->month,
            'target_amount'          => $target->target_amount,
            'target_amount_formatted' => 'Rp ' . number_format($target->target_amount, 0, ',', '.'),
            'user'    => $target->user ? [
                'id'   => $target->user->id,
                'name' => $target->user->full_name,
            ] : null,
            'branch'  => $target->branch ? [
                'id'   => $target->branch->id,
                'name' => $target->branch->name,
            ] : null,
            'created_at' => $target->created_at?->toDateTimeString(),
            'updated_at' => $target->updated_at?->toDateTimeString(),
        ];
    }
}