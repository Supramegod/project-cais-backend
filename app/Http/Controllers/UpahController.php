<?php


namespace App\Http\Controllers;

use App\Enums\ProvinceDetailType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUmkRequest;
use App\Http\Requests\StoreUmpRequest;
use App\Http\Requests\StoreUmskRequest;
use App\Http\Requests\StoreUmspRequest;
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
 *     description="Modul terpadu Upah Minimum: UMP & UMSP (Provinsi), UMK & UMSK (Kota/Kabupaten)."
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
     *     summary="[Level 1] List provinsi beserta UMP aktif dan UMSP aktif per sektor",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page",     in="query", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id",   type="integer", example=35),
     *                     @OA\Property(property="nama", type="string",  example="Jawa Timur"),
     *                     @OA\Property(
     *                         property="ump",
     *                         nullable=true,
     *                         @OA\Property(property="id",          type="integer", example=1),
     *                         @OA\Property(property="nilai",       type="number",  example=2165244.30),
     *                         @OA\Property(property="formatted",   type="string",  example="Rp 2.165.244"),
     *                         @OA\Property(property="tgl_berlaku", type="string",  example="2024-01-01"),
     *                         @OA\Property(property="sumber",      type="string",  example="https://jatim.go.id")
     *                     ),
     *                     @OA\Property(
     *                         property="umsps",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id",          type="integer", example=10),
     *                             @OA\Property(property="sektor",      type="string",  example="Tekstil"),
     *                             @OA\Property(property="nilai",       type="number",  example=2300000.00),
     *                             @OA\Property(property="formatted",   type="string",  example="Rp 2.300.000"),
     *                             @OA\Property(property="tgl_berlaku", type="string",  example="2024-01-01"),
     *                             @OA\Property(property="sumber",      type="string",  example="https://jatim.go.id")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Data provinsi berhasil diambil")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function listProvinsi(Request $request): JsonResponse
    {
        try {
            $perPage   = (int) $request->query('per_page', 15);
            $paginator = $this->service->getProvinsiList($perPage);

            return response()->json([
                'success'    => true,
                'data'       => $paginator->items(),
                'pagination' => $this->paginationMeta($paginator),
                'message'    => 'Data provinsi berhasil diambil',
            ]);

        } catch (\RuntimeException $e) {
            return $this->serviceError('listProvinsi', $e);
        } catch (\Throwable $e) {
            return $this->unexpectedError('listProvinsi', $e);
        }
    }

 

    /**
     * @OA\Get(
     *     path="/api/upah/provinsi/{provinceId}",
     *     summary="[Level 2] Detail provinsi: tab kota, UMP, atau UMSP",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="provinceId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=true,
     *         description="Tipe data: cities, ump, umsp",
     *         @OA\Schema(type="string", enum={"cities","ump","umsp"})
     *     ),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="search", in="query", description="Pencarian nama kota (hanya untuk type=cities)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sektor", in="query", description="Filter sektor UMSP (hanya untuk type=umsp)", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="OK"),
     *     @OA\Response(response=400, description="Type tidak valid"),
     *     @OA\Response(response=404, description="Provinsi tidak ditemukan")
     * )
     */
    public function getProvinceDetail(Request $request, int $provinceId): JsonResponse
    {
        $type = $request->query('type');

        if (!in_array($type, ProvinceDetailType::values())) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter type tidak valid. Harus salah satu: ' . implode(', ', ProvinceDetailType::values()),
            ], 400);
        }

        try {
            // Pastikan provinsi ada
            $province = Province::findOrFail($provinceId);

            $perPage = (int) $request->query('per_page', 15);
            $search  = $request->query('search');
            $sektor  = $request->query('sektor');

            $data = null;
            $message = '';

            switch ($type) {
                case ProvinceDetailType::CITIES->value:
                    $paginator = $this->service->getKotaList($provinceId, $perPage, $search);
                    $data = $paginator->items();
                    $message = 'Data kota/kabupaten berhasil diambil';
                    break;

                case ProvinceDetailType::UMP->value:
                    $paginator = $this->service->getUmpListByProvince($provinceId, $perPage);
                    $data = $paginator->items();
                    $message = 'Data UMP provinsi berhasil diambil';
                    break;

                case ProvinceDetailType::UMSP->value:
                    $paginator = $this->service->getUmspListByProvince($provinceId, $perPage, $sektor);
                    $data = $paginator->items();
                    $message = 'Data UMSP provinsi berhasil diambil';
                    break;
            }
            $umpdata = $province->activeUmp;


            return response()->json([
                'success'    => true,
                'data'       => $data,
                'umpdata'   => $umpdata,
                'pagination' => $this->paginationMeta($paginator),
                'message'    => $message,
            ]);

        } catch (ModelNotFoundException) {
            return $this->notFound("Provinsi dengan ID {$provinceId} tidak ditemukan.");
        } catch (\RuntimeException $e) {
            return $this->serviceError('getProvinceDetail', $e);
        } catch (\Throwable $e) {
            return $this->unexpectedError('getProvinceDetail', $e);
        }
    }

    // ── Level 3 ───────────────────────────────────────────────────────────────

    /**
     * @OA\Get(
     *     path="/api/upah/kota/{cityId}",
     *     summary="[Level 3] Detail kota: UMK aktif, UMSK aktif per sektor, dan riwayat lengkap",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="cityId", in="path", required=true, @OA\Schema(type="integer", example=3578)),
     *     @OA\Response(
     *         response=200,
     *         description="OK",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="kota",
     *                     type="object",
     *                     @OA\Property(property="id",   type="integer"),
     *                     @OA\Property(property="kode", type="string"),
     *                     @OA\Property(property="nama", type="string"),
     *                     @OA\Property(property="umk_aktif", nullable=true, type="object",
     *                         @OA\Property(property="id",          type="integer"),
     *                         @OA\Property(property="nilai",       type="number"),
     *                         @OA\Property(property="formatted",   type="string"),
     *                         @OA\Property(property="tgl_berlaku", type="string"),
     *                         @OA\Property(property="sumber",      type="string")
     *                     ),
     *                     @OA\Property(
     *                         property="umsk_aktif",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id",          type="integer"),
     *                             @OA\Property(property="sektor",      type="string",  example="Tekstil"),
     *                             @OA\Property(property="nilai",       type="number"),
     *                             @OA\Property(property="formatted",   type="string"),
     *                             @OA\Property(property="tgl_berlaku", type="string"),
     *                             @OA\Property(property="sumber",      type="string")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="umk_history",  type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="umsk_history", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="sektor",  type="string"),
     *                     @OA\Property(property="riwayat", type="array", @OA\Items(type="object"))
     *                 ))
     *             )
     *         )
     *     ),
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
     *             @OA\Property(property="tgl_berlaku",   type="string",  format="date", example="2024-01-01"),
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

    // ── Store UMSP ────────────────────────────────────────────────────────────

    /**
     * @OA\Post(
     *     path="/api/upah/umsp",
     *     summary="Tambah UMSP baru untuk suatu sektor di provinsi",
     *     tags={"Upah"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"province_id","province_name","sektor","umsp","tgl_berlaku","sumber"},
     *             @OA\Property(property="province_id",   type="integer", example=35),
     *             @OA\Property(property="province_name", type="string",  example="Jawa Timur"),
     *             @OA\Property(property="sektor",        type="string",  example="Tekstil"),
     *             @OA\Property(property="umsp",          type="number",  example=2350000.00),
     *             @OA\Property(property="tgl_berlaku",   type="string",  format="date", example="2024-01-01"),
     *             @OA\Property(property="sumber",        type="string",  example="https://jatim.go.id/umsp2024")
     *         )
     *     ),
     *     @OA\Response(response=201, description="UMSP berhasil ditambahkan"),
     *     @OA\Response(response=422, description="Validasi gagal"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function storeUmsp(StoreUmspRequest $request): JsonResponse
    {
        try {
            $umsp = $this->service->storeUmsp($request->validated(), $this->actor());

            return $this->created('Data UMSP berhasil ditambahkan', $umsp);

        } catch (\RuntimeException $e) {
            return $this->serviceError('storeUmsp', $e);
        } catch (\Throwable $e) {
            return $this->unexpectedError('storeUmsp', $e);
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
     *             @OA\Property(property="tgl_berlaku", type="string",  format="date", example="2024-01-01"),
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
     *             @OA\Property(property="sektor",      type="string",  example="Otomotif"),
     *             @OA\Property(property="umsk",        type="number",  example=4900000.00),
     *             @OA\Property(property="tgl_berlaku", type="string",  format="date", example="2024-01-01"),
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

    // ── Response Helpers ──────────────────────────────────────────────────────

    private function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'OK'], $status);
    }

    private function created(string $message, mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], 201);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], 404);
    }

    private function serviceError(string $context, \RuntimeException $e): JsonResponse
    {
        Log::warning("[UpahController::{$context}] Service error", [
            'message' => $e->getMessage(),
        ]);

        return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 500);
    }

    private function unexpectedError(string $context, \Throwable $e): JsonResponse
    {
        Log::critical("[UpahController::{$context}] Unexpected error", [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan pada server. Silakan hubungi administrator.',
            'data'    => null,
        ], 500);
    }

    private function paginationMeta(\Illuminate\Pagination\LengthAwarePaginator $paginator): array
    {
        return [
            'current_page'  => $paginator->currentPage(),
            'last_page'     => $paginator->lastPage(),
            'total'         => $paginator->total(),
            'total_per_page'=> $paginator->count(),
        ];
    }

    private function actor(): string
    {
        $user = Auth::user();
        return $user?->full_name ?? $user?->name ?? 'System';
    }
}