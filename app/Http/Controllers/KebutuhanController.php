<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Kebutuhan;
use App\Models\KebutuhanDetail;
use App\Models\KebutuhanDetailTunjangan;
use App\Models\KebutuhanDetailRequirement;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Kebutuhan",
 *     description="API untuk mengelola master data kebutuhan"
 * )
 */
class KebutuhanController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/kebutuhan/list",
     *     operationId="getKebutuhanList",
     *     tags={"Kebutuhan"},
     *     summary="Get all kebutuhan",
     *     description="Mengambil semua data kebutuhan yang tersedia dalam sistem",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data kebutuhan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar kebutuhan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Kebutuhan IT"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-05-01 10:00:00"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-05-01 10:00:00")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $data = Kebutuhan::all();

            return response()->json([
                'success' => true,
                'message' => 'Daftar kebutuhan',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error in kebutuhan list', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => Auth::user()->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'data' => []
            ], 500);
        }
    }

    
    /**
     * @OA\Get(
     *     path="/api/kebutuhan/list-detail/{id}",
     *     operationId="getKebutuhanDetail",
     *     tags={"Kebutuhan"},
     *     summary="Get kebutuhan detail",
     *     description="Mengambil daftar detail kebutuhan berdasarkan kebutuhan_id",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID kebutuhan untuk mengambil detail",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar detail kebutuhan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar detail kebutuhan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                     @OA\Property(property="position_id", type="integer", example=1),
     *                     @OA\Property(property="jumlah", type="integer", example=5),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-05-01 10:00:00"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-05-01 10:00:00")
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
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function listDetail(Request $request, $id)
    {
        try {
            $data = KebutuhanDetail::where('kebutuhan_id', $id)->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar detail kebutuhan',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error in kebutuhan list detail', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => Auth::user()->id ?? null,
                'kebutuhan_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'data' => []
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/kebutuhan/list-detail-tunjangan/{id}",
     *     operationId="getKebutuhanDetailTunjangan",
     *     tags={"Kebutuhan"},
     *     summary="Get kebutuhan detail tunjangan",
     *     description="Mengambil daftar tunjangan berdasarkan kebutuhan_id",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID kebutuhan untuk mengambil daftar tunjangan",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar tunjangan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar detail tunjangan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Tunjangan Transport"),
     *                     @OA\Property(property="nominal", type="string", example="500000"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-05-01 10:00:00"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-05-01 10:00:00")
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
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function listDetailTunjangan(Request $request, $id)
    {
        try {
            $data = KebutuhanDetailTunjangan::where('kebutuhan_id', $id)->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar detail tunjangan',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error in kebutuhan list detail tunjangan', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => Auth::user()->id ?? null,
                'kebutuhan_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'data' => []
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/kebutuhan/add-detail-tunjangan",
     *     operationId="addKebutuhanDetailTunjangan",
     *     tags={"Kebutuhan"},
     *     summary="Add kebutuhan detail tunjangan",
     *     description="Menambahkan detail tunjangan baru untuk kebutuhan tertentu",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data tunjangan yang akan ditambahkan",
     *         @OA\JsonContent(
     *             required={"kebutuhan_id", "nama", "nominal"},
     *             @OA\Property(
     *                 property="kebutuhan_id",
     *                 type="integer",
     *                 description="ID kebutuhan",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="nama",
     *                 type="string",
     *                 description="Nama tunjangan",
     *                 maxLength=255,
     *                 example="Tunjangan Transport"
     *             ),
     *             @OA\Property(
     *                 property="nominal",
     *                 type="string",
     *                 description="Nominal tunjangan (bisa menggunakan format dengan koma)",
     *                 example="500,000"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Data tunjangan berhasil ditambahkan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data berhasil ditambahkan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Tunjangan Transport"),
     *                 @OA\Property(property="nominal", type="string", example="500000"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-05-01 10:00:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-05-01 10:00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="kebutuhan_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The kebutuhan_id field is required.")
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
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function addDetailTunjangan(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kebutuhan_id' => 'required|integer',
                'nama' => 'required|string',
                'nominal' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $nominal = str_replace(",", "", $request->nominal);

            $data = KebutuhanDetailTunjangan::create([
                'kebutuhan_id' => $request->kebutuhan_id,
                'position_id' => 0,
                'nama' => $request->nama,
                'nominal' => $nominal,
                'created_by' => Auth::user()->full_name ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditambahkan',
                'data' => $data
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in add detail tunjangan', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => Auth::user()->id ?? null,
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'data' => []
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/kebutuhan/delete-detail-tunjangan/{id}",
     *     operationId="deleteKebutuhanDetailTunjangan",
     *     tags={"Kebutuhan"},
     *     summary="Delete kebutuhan detail tunjangan",
     *     description="Menghapus detail tunjangan berdasarkan ID (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID tunjangan yang akan dihapus",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data tunjangan berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil menghapus data"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tunjangan tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function deleteDetailTunjangan(Request $request, $id)
    {
        try {
            $data = KebutuhanDetailTunjangan::find($id);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $data->update([
                'deleted_by' => Auth::user()->full_name ?? 'system'
            ]);
            $data->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus data',
                'data' => []
            ]);
        } catch (\Exception $e) {
            Log::error('Error in delete detail tunjangan', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => Auth::user()->id ?? null,
                'tunjangan_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'data' => []
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/kebutuhan/list-detail-requirement/{id}",
     *     operationId="getKebutuhanDetailRequirement",
     *     tags={"Kebutuhan"},
     *     summary="Get kebutuhan detail requirement",
     *     description="Mengambil daftar requirement berdasarkan kebutuhan_id",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID kebutuhan untuk mengambil daftar requirement",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar requirement",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar detail requirement"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                     @OA\Property(property="requirement", type="string", example="Minimal S1 Teknik Informatika dengan pengalaman 2 tahun"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-05-01 10:00:00"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-05-01 10:00:00")
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
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function listDetailRequirement(Request $request, $id)
    {
        try {
            $data = KebutuhanDetailRequirement::where('kebutuhan_id', $id)->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar detail requirement',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error in list detail requirement', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => Auth::user()->id ?? null,
                'kebutuhan_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'data' => []
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/kebutuhan/add-detail-requirement",
     *     operationId="addKebutuhanDetailRequirement",
     *     tags={"Kebutuhan"},
     *     summary="Add kebutuhan detail requirement",
     *     description="Menambahkan detail requirement baru untuk kebutuhan tertentu",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data requirement yang akan ditambahkan",
     *         @OA\JsonContent(
     *             required={"kebutuhan_id", "requirement"},
     *             @OA\Property(
     *                 property="kebutuhan_id",
     *                 type="integer",
     *                 description="ID kebutuhan",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="requirement",
     *                 type="string",
     *                 description="Deskripsi requirement atau persyaratan",
     *                 example="Minimal S1 Teknik Informatika dengan pengalaman 2 tahun"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Data requirement berhasil ditambahkan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data berhasil ditambahkan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                 @OA\Property(property="requirement", type="string", example="Minimal S1 Teknik Informatika dengan pengalaman 2 tahun"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-05-01 10:00:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-05-01 10:00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="kebutuhan_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The kebutuhan_id field is required.")
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
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function addDetailRequirement(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kebutuhan_id' => 'required|integer',
                'requirement' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = KebutuhanDetailRequirement::create([
                'kebutuhan_id' => $request->kebutuhan_id,
                'position_id' => 0,
                'requirement' => $request->requirement,
                'created_by' => Auth::user()->full_name ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil ditambahkan',
                'data' => $data
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in add detail requirement', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => Auth::user()->id ?? null,
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'data' => []
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/kebutuhan/delete-detail-requirement/{id}",
     *     operationId="deleteKebutuhanDetailRequirement",
     *     tags={"Kebutuhan"},
     *     summary="Delete kebutuhan detail requirement",
     *     description="Menghapus detail requirement berdasarkan ID (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID requirement yang akan dihapus",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data requirement berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil menghapus data"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data requirement tidak ditemukan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan sistem"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function deleteDetailRequirement(Request $request, $id)
    {
        try {
            $data = KebutuhanDetailRequirement::find($id);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $data->update([
                'deleted_by' => Auth::user()->full_name ?? 'system'
            ]);
            $data->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus data',
                'data' => []
            ]);
        } catch (\Exception $e) {
            Log::error('Error in delete detail requirement', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user' => Auth::user()->id ?? null,
                'requirement_id' => $id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'data' => []
            ], 500);
        }
    }
}