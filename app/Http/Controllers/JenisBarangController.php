<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\JenisBarang; // Asumsikan model ini sudah dibuat
use App\Models\Barang; // Model yang sudah ada

/**
 * @OA\Tag(
 *     name="Jenis Barang",
 *     description="API Endpoints untuk Master Jenis Barang"
 * )
 */
class JenisBarangController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/jenis-barang/list",
     *     summary="Get all jenis barang",
     *     description="Menampilkan daftar semua jenis barang yang aktif",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success - Data berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data", 
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Elektronik"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z", nullable=true),
     *                     @OA\Property(property="created_by", type="string", example="Admin"),
     *                     @OA\Property(property="updated_by", type="string", example="Admin", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server")
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $data = JenisBarang::whereNull('deleted_at')->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/jenis-barang/view/{id}",
     *     summary="Get detail jenis barang",
     *     description="Menampilkan detail jenis barang berdasarkan ID",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID jenis barang",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Data berhasil ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Elektronik"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z", nullable=true),
     *                 @OA\Property(property="created_by", type="string", example="Admin"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server")
     *         )
     *     )
     * )
     */
    public function view($id)
    {
        try {
            $data = JenisBarang::where('id', $id)->whereNull('deleted_at')->first();

            if (!$data) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/jenis-barang/list-detail/{id}",
     *     summary="Get list barang by jenis barang",
     *     description="Menampilkan daftar barang berdasarkan jenis barang tertentu",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID jenis barang",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Data berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="jenis_barang", type="string", example="Elektronik"),
     *                     @OA\Property(property="nama_barang", type="string", example="Laptop"),
     *                     @OA\Property(property="harga", type="number", format="float", example=15000000),
     *                     @OA\Property(property="satuan", type="string", example="Unit"),
     *                     @OA\Property(property="masa_pakai", type="integer", example=5),
     *                     @OA\Property(property="merk", type="string", example="Dell")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server")
     *         )
     *     )
     * )
     */
    public function listdetail($id)
    {
        try {
            $data = DB::table('m_jenis_barang')
                    ->join('m_barang', 'm_jenis_barang.id', '=', 'm_barang.jenis_barang_id')
                    ->select('m_jenis_barang.nama as jenis_barang', 'm_barang.nama as nama_barang', 'm_barang.harga as harga', 'm_barang.satuan as satuan', 'm_barang.masa_pakai as masa_pakai', 'm_barang.merk as merk')
                    ->where('m_jenis_barang.id', $id)
                    ->whereNull('m_jenis_barang.deleted_at')
                    ->get();

            return response()->json([
                'status' => 'success',
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/jenis-barang/add",
     *     summary="Create new jenis barang",
     *     description="Menambahkan jenis barang baru ke dalam sistem",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data jenis barang yang akan ditambahkan",
     *         @OA\JsonContent(
     *             required={"nama"},
     *             @OA\Property(property="nama", type="string", maxLength=255, example="Elektronik", description="Nama jenis barang")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Success - Jenis barang berhasil ditambahkan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Jenis Barang berhasil disimpan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Elektronik"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
     *                 @OA\Property(property="created_by", type="string", example="Admin")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Data tidak valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="nama",
     *                     type="array",
     *                     @OA\Items(type="string", example="Nama jenis barang harus diisi")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server")
     *         )
     *     )
     * )
     */
    public function add(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
            ], [
                'nama.required' => 'Nama jenis barang harus diisi',
                'nama.string' => 'Nama harus berupa string',
                'nama.max' => 'Nama maksimal 255 karakter',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisBarang = JenisBarang::create([
                'nama' => $request->nama,
                'created_at' => Carbon::now(),
                'created_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jenis Barang berhasil disimpan',
                'data' => $jenisBarang
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/jenis-barang/update/{id}",
     *     summary="Update jenis barang",
     *     description="Memperbarui data jenis barang berdasarkan ID",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID jenis barang yang akan diupdate",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data jenis barang yang akan diperbarui",
     *         @OA\JsonContent(
     *             required={"nama"},
     *             @OA\Property(property="nama", type="string", maxLength=255, example="Elektronik Updated", description="Nama jenis barang")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Jenis barang berhasil diperbarui",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Jenis Barang berhasil diupdate"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Elektronik Updated"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T12:00:00.000000Z"),
     *                 @OA\Property(property="created_by", type="string", example="Admin"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Data tidak valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="nama",
     *                     type="array",
     *                     @OA\Items(type="string", example="Nama jenis barang harus diisi")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $jenisBarang = JenisBarang::where('id', $id)->whereNull('deleted_at')->first();

            if (!$jenisBarang) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
            ], [
                'nama.required' => 'Nama jenis barang harus diisi',
                'nama.string' => 'Nama harus berupa string',
                'nama.max' => 'Nama maksimal 255 karakter',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisBarang->update([
                'nama' => $request->nama,
                'updated_at' => Carbon::now(),
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jenis Barang berhasil diupdate',
                'data' => $jenisBarang
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/jenis-barang/delete/{id}",
     *     summary="Delete jenis barang",
     *     description="Menghapus jenis barang berdasarkan ID (soft delete)",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID jenis barang yang akan dihapus",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Jenis barang berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Jenis Barang berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan server")
     *         )
     *     )
     * )
     */
    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $jenisBarang = JenisBarang::where('id', $id)->whereNull('deleted_at')->first();

            if (!$jenisBarang) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $jenisBarang->update([
                'deleted_at' => Carbon::now(),
                'deleted_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Jenis Barang berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server'
            ], 500);
        }
    }
}