<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TOP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="TOP",
 *     description="API Endpoints untuk Terms of Payment (TOP)"
 * )
 */
class TopController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/top/list",
     *     summary="Get list of TOP data",
     *     description="Mendapatkan daftar data Terms of Payment dengan pagination",
     *     tags={"TOP"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="TOP 30 Hari"),
     *                         @OA\Property(property="persentase", type="number", format="float", example=30.00),
     *                         @OA\Property(property="created_by", type="string", example="John Doe"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="last_page", type="integer", example=10),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $data = Top::all();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/top/add",
     *     summary="Create a new TOP",
     *     description="Membuat data Terms of Payment baru",
     *     tags={"TOP"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data TOP yang akan dibuat",
     *         @OA\JsonContent(
     *             required={"nama", "persentase"},
     *             @OA\Property(property="nama", type="string", example="TOP 30 Hari", description="Nama terms of payment", maxLength=255),
     *             @OA\Property(property="persentase", type="number", format="float", example=30.00, description="Persentase TOP (0-100)", minimum=0, maximum=100)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="TOP created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="TOP berhasil dibuat"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="TOP 30 Hari"),
     *                 @OA\Property(property="persentase", type="number", format="float", example=30.00),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="nama", type="array",
     *                     @OA\Items(type="string", example="Nama TOP harus diisi")
     *                 ),
     *                 @OA\Property(property="persentase", type="array",
     *                     @OA\Items(type="string", example="Persentase harus diisi")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function add(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255|unique:m_top,nama',
                'persentase' => 'required|numeric|min:0|max:100'
            ], [
                'nama.required' => 'Nama TOP harus diisi',
                'nama.max' => 'Nama maksimal 255 karakter',
                'nama.unique' => 'Nama TOP sudah ada',
                'persentase.required' => 'Persentase harus diisi',
                'persentase.numeric' => 'Persentase harus berupa angka',
                'persentase.min' => 'Persentase minimal 0',
                'persentase.max' => 'Persentase maksimal 100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $top = Top::create([
                'nama' => $request->nama,
                'persentase' => $request->persentase,
                'created_by' => Auth::user()->full_name ?? 'System'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'TOP berhasil dibuat',
                'data' => $top
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/top/view/{id}",
     *     summary="Get TOP by ID",
     *     description="Mendapatkan detail data Terms of Payment berdasarkan ID",
     *     tags={"TOP"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="TOP ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="TOP 30 Hari"),
     *                 @OA\Property(property="persentase", type="number", format="float", example=30.00),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="TOP not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="TOP tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function view($id)
    {
        try {
            $top = Top::find($id);

            if (!$top) {
                return response()->json([
                    'success' => false,
                    'message' => 'TOP tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $top
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/top/update/{id}",
     *     summary="Update TOP",
     *     description="Mengupdate data Terms of Payment yang sudah ada",
     *     tags={"TOP"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="TOP ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data TOP yang akan diupdate",
     *         @OA\JsonContent(
     *             required={"nama", "persentase"},
     *             @OA\Property(property="nama", type="string", example="TOP 60 Hari", description="Nama terms of payment", maxLength=255),
     *             @OA\Property(property="persentase", type="number", format="float", example=60.00, description="Persentase TOP (0-100)", minimum=0, maximum=100)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="TOP updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="TOP berhasil diupdate"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="TOP 60 Hari"),
     *                 @OA\Property(property="persentase", type="number", format="float", example=60.00),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-16T14:20:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="TOP not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="TOP tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="nama", type="array",
     *                     @OA\Items(type="string", example="Nama TOP sudah ada")
     *                 ),
     *                 @OA\Property(property="persentase", type="array",
     *                     @OA\Items(type="string", example="Persentase harus berupa angka")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $top = Top::find($id);

            if (!$top) {
                return response()->json([
                    'success' => false,
                    'message' => 'TOP tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255|unique:m_top,nama,' . $id,
                'persentase' => 'required|numeric|min:0|max:100'
            ], [
                'nama.required' => 'Nama TOP harus diisi',
                'nama.max' => 'Nama maksimal 255 karakter',
                'nama.unique' => 'Nama TOP sudah ada',
                'persentase.required' => 'Persentase harus diisi',
                'persentase.numeric' => 'Persentase harus berupa angka',
                'persentase.min' => 'Persentase minimal 0',
                'persentase.max' => 'Persentase maksimal 100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $top->update([
                'nama' => $request->nama,
                'persentase' => $request->persentase,
                'updated_by' => Auth::user()->full_name ?? 'System'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'TOP berhasil diupdate',
                'data' => $top
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/top/delete/{id}",
     *     summary="Delete TOP",
     *     description="Menghapus data Terms of Payment (soft delete)",
     *     tags={"TOP"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="TOP ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="TOP deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="TOP berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="TOP not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="TOP tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Cannot delete TOP (constraint violation)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="TOP tidak dapat dihapus karena masih digunakan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function delete($id)
    {
        try {
            $top = Top::find($id);

            if (!$top) {
                return response()->json([
                    'success' => false,
                    'message' => 'TOP tidak ditemukan'
                ], 404);
            }

            $top->update([
                'deleted_by' => Auth::user()->full_name ?? 'System'
            ]);
            $top->delete();

            return response()->json([
                'success' => true,
                'message' => 'TOP berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}