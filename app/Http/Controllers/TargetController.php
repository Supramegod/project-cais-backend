<?php

namespace App\Http\Controllers;

use App\Models\Target;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Target",
 *     description="API untuk manajemen target sales/user"
 * )
 */
class TargetController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/targets/list",
     *     summary="Mendapatkan daftar target",
     *     description="Endpoint untuk mengambil daftar target dengan filter tahun opsional",
     *     tags={"Target"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="tahun",
     *         in="query",
     *         description="Filter berdasarkan tahun (format: YYYY)",
     *         required=false,
     *         @OA\Schema(type="integer", example=2024)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data target",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="tahun", type="integer", example=2024),
     *                     @OA\Property(property="branch_id", type="integer", example=1),
     *                     @OA\Property(property="target", type="number", example=1000000),
     *                     @OA\Property(property="nama", type="string", example="John Doe"),
     *                     @OA\Property(property="cais_role_id", type="integer", example=29),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="full_name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(
     *                         property="branch",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Jakarta")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Target::with(['user', 'branch']);

        if ($request->tahun) {
            $query->where('tahun', $request->tahun);
        }

        return response()->json([
            'success' => true,
            'data' => $query->whereNull('deleted_at')->get()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/targets/add",
     *     summary="Membuat atau mengupdate target",
     *     description="Endpoint untuk membuat target baru atau mengupdate target yang sudah ada berdasarkan user_id dan tahun",
     *     tags={"Target"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "tahun", "branch_id", "target", "nama"},
     *             @OA\Property(property="user_id", type="integer", example=1, description="ID user yang akan diberi target"),
     *             @OA\Property(property="tahun", type="integer", example=2024, description="Tahun target (format: YYYY)"),
     *             @OA\Property(property="branch_id", type="integer", example=1, description="ID branch/cabang"),
     *             @OA\Property(property="target", type="number", example=1000000, description="Nilai target"),
     *             @OA\Property(property="nama", type="string", example="John Doe", description="Nama user"),
     *             @OA\Property(property="cais_role_id", type="integer", example=29, description="ID role user (opsional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Target berhasil dibuat atau diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Target baru tahun 2024 berhasil dibuat"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="tahun", type="integer", example=2024),
     *                 @OA\Property(property="branch_id", type="integer", example=1),
     *                 @OA\Property(property="target", type="number", example=1000000),
     *                 @OA\Property(property="nama", type="string", example="John Doe"),
     *                 @OA\Property(property="cais_role_id", type="integer", example=29),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="user_id", type="array", @OA\Items(type="string", example="The user id field is required.")),
     *                 @OA\Property(property="tahun", type="array", @OA\Items(type="string", example="The tahun must be 4 digits.")),
     *                 @OA\Property(property="target", type="array", @OA\Items(type="string", example="The target must be a number."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function storeOrUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'tahun' => 'required|digits:4', // Validasi format tahun (YYYY)
            'branch_id' => 'required|integer',
            'target' => 'required|numeric',
            'nama' => 'required|string',
            'cais_role_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user_login = Auth::user()->full_name ?? Auth::user()->name;

        // LOGIC: Cek berdasarkan user_id DAN tahun
        $target = Target::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'tahun' => $request->tahun
            ],
            [
                'cais_role_id' => $request->cais_role_id,
                'branch_id' => $request->branch_id,
                'nama' => $request->nama,
                'target' => $request->target,
                'updated_by' => $user_login,
                // Kita gunakan closure untuk created_by agar hanya terisi saat data baru dibuat
            ]
        );

        // Manual check untuk created_by jika datanya baru saja dibuat (wasRecentlyCreated)
        if ($target->wasRecentlyCreated) {
            $target->update(['created_by' => $user_login]);
            $message = "Target baru tahun $request->tahun berhasil dibuat";
        } else {
            $message = "Target user tahun $request->tahun berhasil diperbarui";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $target
        ]);
    }
}