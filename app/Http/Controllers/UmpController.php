<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ump;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="UMP",
 *     description="API Endpoints untuk Management Upah Minimum Provinsi (UMP)"
 * )
 */
class UmpController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/ump/list",
     *     summary="Get list of active UMP data",
     *     tags={"UMP"},
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
     *                     @OA\Property(property="province_id", type="integer", example=1),
     *                     @OA\Property(property="province_name", type="string", example="Jawa Barat"),
     *                     @OA\Property(property="ump", type="number", format="float", example=3500000.00),
     *                     @OA\Property(property="tgl_berlaku", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="sumber", type="string", example="https://example.com/sumber-ump"),
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
    public function index(Request $request)
    {
        try {
            $data = Ump::getActive();

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
 *     path="/api/ump/view/{provinceId}",
 *     summary="Get UMP detail by Province ID",
 *     tags={"UMP"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="provinceId",
 *         in="path",
 *         required=true,
 *         description="Province ID",
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
 *         description="UMP not found"
 *     )
 * )
 */
public function view($provinceId)
{
    try {
        $data = Ump::where('province_id', $provinceId)->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data UMP tidak ditemukan'
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
     *     path="/api/ump/province/{provinceId}",
     *     summary="Get UMP data by province ID",
     *     tags={"UMP"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="provinceId",
     *         in="path",
     *         required=true,
     *         description="Province ID",
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
     *                     @OA\Property(property="province_id", type="integer", example=1),
     *                     @OA\Property(property="province_name", type="string", example="Jawa Barat"),
     *                     @OA\Property(property="ump", type="number", format="float", example=3500000.00),
     *                     @OA\Property(property="tgl_berlaku", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="sumber", type="string", example="https://example.com/sumber-ump"),
     *                     @OA\Property(property="is_aktif", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listUmp($provinceId)
    {
        try {
            $data = Ump::getByProvince($provinceId);

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
     *     path="/api/ump/add",
     *     summary="Create a new UMP data",
     *     tags={"UMP"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"province_id", "province_name", "ump", "tgl_berlaku", "sumber"},
     *             @OA\Property(property="province_id", type="integer", example=1, description="ID Provinsi"),
     *             @OA\Property(property="province_name", type="string", example="Jawa Barat", description="Nama Provinsi"),
     *             @OA\Property(property="ump", type="number", format="float", example=3500000.00, description="Nilai UMP"),
     *             @OA\Property(property="tgl_berlaku", type="string", format="date", example="2024-01-01", description="Tanggal berlaku UMP"),
     *             @OA\Property(property="sumber", type="string", example="https://example.com/sumber-ump", description="Sumber informasi UMP")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="UMP created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data UMP berhasil ditambahkan"),
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
                'province_id' => 'required|integer',
                'province_name' => 'required|string|max:255',
                'ump' => 'required|numeric|min:0',
                'tgl_berlaku' => 'required|date',
                'sumber' => 'required|url|max:500'
            ], [
                'province_id.required' => 'Province ID harus diisi',
                'province_name.required' => 'Nama provinsi harus diisi',
                'province_name.max' => 'Nama provinsi maksimal 255 karakter',
                'ump.required' => 'Nilai UMP harus diisi',
                'ump.numeric' => 'Nilai UMP harus berupa angka',
                'ump.min' => 'Nilai UMP minimal 0',
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

            // Non-aktifkan UMP lama untuk province yang sama
            Ump::where('province_id', $request->province_id)
                ->update([
                    'is_aktif' => 0,
                    'updated_by' => Auth::user()->full_name ?? 'System'
                ]);

            // Buat UMP baru
            $ump = Ump::create([
                'province_id' => $request->province_id,
                'province_name' => $request->province_name,
                'ump' => $request->ump,
                'tgl_berlaku' => $request->tgl_berlaku,
                'sumber' => $request->sumber,
                'is_aktif' => 1,
                'created_by' => Auth::user()->full_name ?? 'System'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data UMP berhasil ditambahkan',
                'data' => $ump
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data gagal ditambahkan: ' . $e->getMessage()
            ], 500);
        }
    }
}