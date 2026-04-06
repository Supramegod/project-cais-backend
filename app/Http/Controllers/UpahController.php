<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUmkRequest;
use App\Http\Requests\StoreUmpRequest;
use App\Http\Requests\StoreUmskRequest;
use App\Models\Province;
use App\Services\UpahService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Upah",
 *     description="Modul terpadu Upah Minimum: UMP (Provinsi), UMK & UMSK (Kota/Kabupaten)."
 * )
 */
class UpahController extends Controller
{
    public function __construct(private readonly UpahService $service)
    {
    }

    // ── Level 1 ───────────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/upah/provinsi",
     *     summary="[Level 1] List provinsi beserta UMP aktif",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination (default: 1)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function listProvinsi(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $paginator = $this->service->getProvinsiList($perPage);

            return response()->json([
                'success' => true,
                'data' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'total_per_page' => $paginator->count(),
                ],
                'message' => 'Data provinsi berhasil diambil',
            ]);

        } catch (\RuntimeException $e) {
            return $this->serviceError('listProvinsi', $e);

        } catch (\Throwable $e) {
            return $this->unexpectedError('listProvinsi', $e);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/upah/provinsi/{provinceId}/kota",
     *     summary="[Level 2] List kota/kabupaten beserta UMK aktif (dengan pencarian)",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="provinceId",
     *         in="path",
     *         required=true,
     *         description="ID Provinsi",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination (default: 1)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Jumlah data per halaman",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Cari berdasarkan nama kota/kabupaten (partial match)",
     *         @OA\Schema(type="string", example="Surabaya")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3578),
     *                     @OA\Property(property="kode", type="string", example="35.78"),
     *                     @OA\Property(property="nama", type="string", example="Kota Surabaya"),
     *                     @OA\Property(
     *                         property="umk",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=123),
     *                         @OA\Property(property="nilai", type="number", example=4725479.00),
     *                         @OA\Property(property="formatted", type="string", example="Rp 4.725.479"),
     *                         @OA\Property(property="tgl_berlaku", type="string", format="date", example="2024-01-01"),
     *                         @OA\Property(property="sumber", type="string", example="https://jatim.go.id/umk2024")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="total", type="integer", example=75),
     *                 @OA\Property(property="total_per_page", type="integer", example=15)
     *             ),
     *             @OA\Property(property="message", type="string", example="Data kota/kabupaten berhasil diambil")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function listKota(Request $request, int $provinceId): JsonResponse
    {
        try {
            $perPage = (int) $request->query('per_page', 15);
            $search = $request->query('search'); // parameter search opsional

            // Get UMP data for province
            $province = Province::with('activeUmp')->findOrFail($provinceId);
            $umpData = $province->activeUmp ? [
                'id' => $province->activeUmp->id,
                'nilai' => (float) $province->activeUmp->ump,
                'nama_provinsi' => $province->name,
                'formatted' => $province->activeUmp->formatump(),
            ] : null;

            // Get cities list
            $paginator = $this->service->getKotaList($provinceId, $perPage, $search);

            return response()->json([
                'success' => true,
                'ump' => $umpData,
                'data' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'total_per_page' => $paginator->count(),
                ],
                'message' => 'Data kota/kabupaten berhasil diambil',
            ]);

        } catch (ModelNotFoundException) {
            return $this->notFound("Provinsi dengan ID {$provinceId} tidak ditemukan.");
        } catch (\RuntimeException $e) {
            return $this->serviceError('listKota', $e);
        } catch (\Throwable $e) {
            return $this->unexpectedError('listKota', $e);
        }
    }

    // ── Level 3 ───────────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/upah/kota/{cityId}",
     *     summary="[Level 3] Detail kota: riwayat UMK & UMSK per sektor",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="cityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination (default: 1)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=404, description="Kota tidak ditemukan"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function detailKota(int $cityId): JsonResponse
    {
        try {
            $data = $this->service->getDetailKota($cityId);

            return $this->success($data);

        } catch (ModelNotFoundException) {
            return $this->notFound("Kota dengan ID {$cityId} tidak ditemukan.");

        } catch (\RuntimeException $e) {
            return $this->serviceError('detailKota', $e);

        } catch (\Throwable $e) {
            return $this->unexpectedError('detailKota', $e);
        }
    }

    // ── Store UMP ─────────────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/upah/ump",
     *     summary="Tambah UMP baru untuk suatu provinsi",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"province_id","province_name","ump","tgl_berlaku","sumber"},
     *             @OA\Property(property="province_id",   type="integer", example=35),
     *             @OA\Property(property="province_name", type="string",  example="Jawa Timur"),
     *             @OA\Property(property="ump",           type="number",  example=2165244.30),
     *             @OA\Property(property="tgl_berlaku",   type="string",  example="2024-01-01"),
     *             @OA\Property(property="sumber",        type="string",  example="https://jatim.go.id/ump2024")
     *         )
     *     ),
     *     @OA\Response(response=201, description="UMP berhasil ditambahkan"),
     *     @OA\Response(response=422, description="Validasi gagal"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function storeUmp(StoreUmpRequest $request): JsonResponse
    {
        try {
            $ump = $this->service->storeUmp($request->validated(), $this->actor());

            return $this->created('Data UMP berhasil ditambahkan', $ump);

        } catch (\RuntimeException $e) {
            return $this->serviceError('storeUmp', $e);

        } catch (\Throwable $e) {
            return $this->unexpectedError('storeUmp', $e);
        }
    }

    // ── Store UMK ─────────────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/upah/umk",
     *     summary="Tambah UMK baru untuk suatu kota/kabupaten",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"city_id","city_name","umk","tgl_berlaku","sumber"},
     *             @OA\Property(property="city_id",     type="integer", example=3578),
     *             @OA\Property(property="city_name",   type="string",  example="Kota Surabaya"),
     *             @OA\Property(property="umk",         type="number",  example=4725479.00),
     *             @OA\Property(property="tgl_berlaku", type="string",  example="2024-01-01"),
     *             @OA\Property(property="sumber",      type="string",  example="https://jatim.go.id/umk2024")
     *         )
     *     ),
     *     @OA\Response(response=201, description="UMK berhasil ditambahkan"),
     *     @OA\Response(response=422, description="Validasi gagal"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function storeUmk(StoreUmkRequest $request): JsonResponse
    {
        try {
            $umk = $this->service->storeUmk($request->validated(), $this->actor());

            return $this->created('Data UMK berhasil ditambahkan', $umk);

        } catch (\RuntimeException $e) {
            return $this->serviceError('storeUmk', $e);

        } catch (\Throwable $e) {
            return $this->unexpectedError('storeUmk', $e);
        }
    }

    // ── Store UMSK ────────────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/upah/umsk",
     *     summary="Tambah UMSK baru untuk suatu sektor di kota/kabupaten",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"city_id","city_name","sektor","umsk","tgl_berlaku","sumber"},
     *             @OA\Property(property="city_id",     type="integer", example=3578),
     *             @OA\Property(property="city_name",   type="string",  example="Kota Surabaya"),
     *             @OA\Property(property="umsk",        type="number",  example=4900000.00),
     *             @OA\Property(property="tgl_berlaku", type="string",  example="2024-01-01"),
     *             @OA\Property(property="sumber",      type="string",  example="https://jatim.go.id/umsk2024")
     *         )
     *     ),
     *     @OA\Response(response=201, description="UMSK berhasil ditambahkan"),
     *     @OA\Response(response=422, description="Validasi gagal"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function storeUmsk(StoreUmskRequest $request): JsonResponse
    {
        try {
            $umsk = $this->service->storeUmsk($request->validated(), $this->actor());

            return $this->created('Data UMSK berhasil ditambahkan', $umsk);

        } catch (\RuntimeException $e) {
            return $this->serviceError('storeUmsk', $e);

        } catch (\Throwable $e) {
            return $this->unexpectedError('storeUmsk', $e);
        }
    }

    // ── Response helpers ──────────────────────────────────────────────────────

    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function created(string $message, mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], 201);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 404);
    }

    /**
     * Error yang sudah diidentifikasi oleh service layer (RuntimeException).
     * Pesan sudah ramah user, tidak bocorkan detail teknis.
     */
    private function serviceError(string $context, \RuntimeException $e): JsonResponse
    {
        Log::warning("[UpahController::{$context}] Service error", [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }

    /**
     * Error tak terduga — log lengkap dengan stack trace, response generik ke client.
     */
    private function unexpectedError(string $context, \Throwable $e): JsonResponse
    {
        Log::critical("[UpahController::{$context}] Unexpected error", [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan pada server. Silakan hubungi administrator.',
        ], 500);
    }

    private function actor(): string
    {
        $user = Auth::user();
        return $user?->full_name ?? $user?->name ?? 'System';
    }
}