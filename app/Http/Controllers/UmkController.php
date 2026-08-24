<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Upah\StoreUmkRequest;
use App\Models\Umk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="UMK",
 *     description="API Endpoints untuk Management Upah Minimum Kabupaten/Kota (UMK)"
 * )
 */
class UmkController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/umk/list",
     *     summary="Get list of active UMK data",
     *     tags={"UMK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="city_id", type="integer", example=1),
     *                     @OA\Property(property="city_name", type="string", example="Kota Bandung"),
     *                     @OA\Property(property="umk", type="number", format="float", example=3500000.00),
     *                     @OA\Property(property="tgl_berlaku", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="sumber", type="string", example="https://example.com/sumber-umk"),
     *                     @OA\Property(property="is_aktif", type="boolean", example=true),
     *                     @OA\Property(property="created_by", type="string", example="John Doe"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $data = Umk::getActive();

            return $this->successResponse($data);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Internal server error: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/umk/view/{cityId}",
     *     summary="Get UMK detail by City ID",
     *     tags={"UMK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="cityId",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="UMK not found"
     *     )
     * )
     */
    public function view($cityId)
    {
        try {
            $data = Umk::where('city_id', $cityId)->first();

            if (!$data) {
                return $this->notFoundResponse('Data UMK tidak ditemukan');
            }

            return $this->successResponse($data);

        } catch (\Exception $e) {
            return $this->serverErrorResponse('Internal server error: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Get(
     *     path="/api/umk/city/{cityId}",
     *     summary="Get UMK data by city ID",
     *     tags={"UMK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="cityId",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="city_id", type="integer", example=1),
     *                     @OA\Property(property="city_name", type="string", example="Kota Bandung"),
     *                     @OA\Property(property="umk", type="number", format="float", example=3500000.00),
     *                     @OA\Property(property="tgl_berlaku", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="sumber", type="string", example="https://example.com/sumber-umk"),
     *                     @OA\Property(property="is_aktif", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listUmk($cityId)
    {
        try {
            $data = Umk::getByCity($cityId);

            return $this->successResponse($data);

        } catch (\Exception $e) {
            return $this->serverErrorResponse('Internal server error: ' . $e->getMessage());
        }
    }

    /**
     * @OA\Post(
     *     path="/api/umk/add",
     *     summary="Create a new UMK data",
     *     tags={"UMK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"city_id", "city_name", "umk", "tgl_berlaku", "sumber"},
     *             @OA\Property(property="city_id", type="integer", example=1, description="ID Kota/Kabupaten"),
     *             @OA\Property(property="city_name", type="string", example="Kota Bandung", description="Nama Kota/Kabupaten"),
     *             @OA\Property(property="umk", type="number", format="float", example=3500000.00, description="Nilai UMK"),
     *             @OA\Property(property="tgl_berlaku", type="string", format="date", example="2024-01-01", description="Tanggal berlaku UMK"),
     *             @OA\Property(property="sumber", type="string", example="https://example.com/sumber-umk", description="Sumber informasi UMK")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="UMK created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data UMK berhasil ditambahkan"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function add(StoreUmkRequest $request)
    {
        try {
            // Non-aktifkan UMK lama untuk city yang sama
            Umk::where('city_id', $request->city_id)
                ->update([
                    'is_aktif' => 0,
                    'updated_by' => Auth::user()->full_name ?? 'System'
                ]);

            // Buat UMK baru
            $umk = Umk::create([
                'city_id' => $request->city_id,
                'city_name' => $request->city_name,
                'umk' => $request->umk,
                'tgl_berlaku' => $request->tgl_berlaku,
                'sumber' => $request->sumber,
                'is_aktif' => 1,
                'created_by' => Auth::user()->full_name ?? 'System',
                'created_by_user_id' => Auth::id()
            ]);

            return $this->successResponse($umk, 'Data UMK berhasil ditambahkan');

        } catch (\Exception $e) {
            return $this->serverErrorResponse('Data gagal ditambahkan: ' . $e->getMessage());
        }
    }

}