<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Umk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/umk/view/{id}",
     *     summary="Get UMK detail by ID",
     *     tags={"UMK"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UMK ID",
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
    public function view($id)
    {
        try {
            $data = Umk::find($id);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data UMK tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
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

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
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
    public function add(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'city_id' => 'required|integer',
                'city_name' => 'required|string|max:255',
                'umk' => 'required|numeric|min:0',
                'tgl_berlaku' => 'required|date',
                'sumber' => 'required|url|max:500'
            ], [
                'city_id.required' => 'City ID harus diisi',
                'city_name.required' => 'Nama kota/kabupaten harus diisi',
                'city_name.max' => 'Nama kota/kabupaten maksimal 255 karakter',
                'umk.required' => 'Nilai UMK harus diisi',
                'umk.numeric' => 'Nilai UMK harus berupa angka',
                'umk.min' => 'Nilai UMK minimal 0',
                'tgl_berlaku.required' => 'Tanggal berlaku harus diisi',
                'tgl_berlaku.date' => 'Format tanggal tidak valid',
                'sumber.required' => 'Sumber harus diisi',
                'sumber.url' => 'Sumber harus berupa URL yang valid',
                'sumber.max' => 'Sumber maksimal 500 karakter'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

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
                'created_by' => Auth::user()->full_name ?? 'System'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data UMK berhasil ditambahkan',
                'data' => $umk
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data gagal ditambahkan: ' . $e->getMessage()
            ], 500);
        }
    }

}