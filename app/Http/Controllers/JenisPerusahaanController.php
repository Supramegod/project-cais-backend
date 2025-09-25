<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\JenisPerusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
/**
 * @OA\Tag(
 *     name="Jenis Perusahaan",
 *     description="API Endpoints untuk Jenis Perusahaan"
 * )
 */class JenisPerusahaanController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/jenis-perusahaan/list",
     *     tags={"Jenis Perusahaan"},
     *     summary="List semua jenis perusahaan",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Daftar jenis perusahaan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="PT"),
     *                     @OA\Property(property="resiko", type="string", example="Rendah"),
     *                     @OA\Property(property="created_by", type="string", example="Admin"),
     *                     @OA\Property(property="updated_by", type="string", example=null),
     *                     @OA\Property(property="deleted_by", type="string", example=null)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function list()
    {
        $data = JenisPerusahaan::with(['creator', 'updater', 'deleter'])->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * @OA\Post(
     *     path="/api/jenis-perusahaan/save",
     *     tags={"Jenis Perusahaan"},
     *     summary="Tambah jenis perusahaan baru",
     *     security={{"bearerAuth":{}}}, 
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama","resiko"},
     *             @OA\Property(property="nama", type="string", example="CV"),
     *             @OA\Property(property="resiko", type="string", example="Tinggi")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Jenis perusahaan berhasil ditambahkan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil ditambahkan"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="nama", type="string", example="CV"),
     *                 @OA\Property(property="resiko", type="string", example="Tinggi"),
     *                 @OA\Property(property="created_by", type="string", example="Admin")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The nama field is required."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'resiko' => 'required|string|max:100',
        ]);

        // $validated['created_by'] = Auth::user()->full_name ?? 'system';

        $data = JenisPerusahaan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil ditambahkan',
            'data' => $data
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/jenis-perusahaan/update/{id}",
     *     tags={"Jenis Perusahaan"},
     *     summary="Update jenis perusahaan",
     *     security={{"bearerAuth":{}}}, 
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama","resiko"},
     *             @OA\Property(property="nama", type="string", example="PT"),
     *             @OA\Property(property="resiko", type="string", example="Rendah")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Jenis perusahaan berhasil diperbarui",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil diperbarui"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="PT"),
     *                 @OA\Property(property="resiko", type="string", example="Rendah"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'resiko' => 'required|string|max:100',
        ]);

        $data = JenisPerusahaan::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        // $validated['updated_by'] = Auth::user()->full_name ?? 'system';

        $data->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil diperbarui',
            'data' => $data
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/jenis-perusahaan/view/{id}",
     *     tags={"Jenis Perusahaan"},
     *     summary="Lihat detail jenis perusahaan",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Detail jenis perusahaan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="PT"),
     *                 @OA\Property(property="resiko", type="string", example="Sedang"),
     *                 @OA\Property(property="created_by", type="string", example="Admin")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     )
     * )
     */
    public function view($id)
    {
        $data = JenisPerusahaan::findOrFail($id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * @OA\Delete(
     *     path="/api/jenis-perusahaan/delete/{id}",
     *     tags={"Jenis Perusahaan"},
     *     summary="Hapus jenis perusahaan",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Jenis perusahaan berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak ditemukan")
     *         )
     *     )
     * )
     */
    public function delete($id)
    {
        $data = JenisPerusahaan::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        // $data->deleted_by = Auth::user()->full_name ?? 'system';
        // $data->save();

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil dihapus'
        ]);
    }
}
