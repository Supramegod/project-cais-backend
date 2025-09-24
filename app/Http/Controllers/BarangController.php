<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\JenisBarang;
use App\Models\Kebutuhan;
use App\Models\BarangDefaultQty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Barang",
 *     description="API untuk manajemen data barang"
 * )
 */
class BarangController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/barang/list",
     *     summary="Get semua data barang",
     *     description="Mengambil semua data barang yang aktif (belum dihapus) dengan informasi jenis barang",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Data berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", 
     *                 type="array", 
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Barang Contoh"),
     *                     @OA\Property(property="jenis_barang_id", type="integer", example=1),
     *                     @OA\Property(property="jenis_barang", type="string", example="Kaporlap"),
     *                     @OA\Property(property="harga", type="number", format="float", example=100000),
     *                     @OA\Property(property="satuan", type="string", example="pcs"),
     *                     @OA\Property(property="masa_pakai", type="integer", example=12),
     *                     @OA\Property(property="merk", type="string", example="Merk Contoh"),
     *                     @OA\Property(property="jumlah_default", type="integer", example=10),
     *                     @OA\Property(property="urutan", type="integer", example=1),
     *                     @OA\Property(property="created_by", type="string", example="Admin User"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z"),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Token tidak valid atau tidak ada",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error: Internal server error")
     *         )
     *     )
     * )
     */
    public function list()
    {
        try {
            $data = Barang::with('jenisBarang')
                ->whereNull('deleted_at')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/barang/view/{id}",
     *     summary="Get detail barang",
     *     description="Mengambil detail barang berdasarkan ID dengan informasi jenis barang dan default quantity",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID barang yang ingin dilihat",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data barang berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data", 
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Barang Contoh"),
     *                 @OA\Property(property="jenis_barang_id", type="integer", example=1),
     *                 @OA\Property(property="jenis_barang", type="string", example="Kaporlap"),
     *                 @OA\Property(property="harga", type="number", format="float", example=100000),
     *                 @OA\Property(property="satuan", type="string", example="pcs"),
     *                 @OA\Property(property="masa_pakai", type="integer", example=12),
     *                 @OA\Property(property="merk", type="string", example="Merk Contoh"),
     *                 @OA\Property(property="jumlah_default", type="integer", example=10),
     *                 @OA\Property(property="urutan", type="integer", example=1),
     *                 @OA\Property(property="created_by", type="string", example="Admin User"),
     *                 @OA\Property(property="updated_by", type="string", example="Admin User"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="default_qty",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 )
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
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error: Internal server error")
     *         )
     *     )
     * )
     */
    public function view($id)
    {
        try {
            $data = Barang::with(['jenisBarang', 'defaultQty'])
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->first();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/barang/add",
     *     summary="Tambah barang baru",
     *     description="Menambahkan data barang baru ke dalam sistem",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data barang yang akan ditambahkan",
     *         @OA\JsonContent(
     *             required={"nama", "jenis_barang_id", "harga"},
     *             @OA\Property(property="nama", type="string", example="Barang Baru", description="Nama barang"),
     *             @OA\Property(property="jenis_barang_id", type="integer", example=1, description="ID jenis barang"),
     *             @OA\Property(property="harga", type="string", example="150,000", description="Harga barang (bisa dengan koma sebagai pemisah ribuan)"),
     *             @OA\Property(property="satuan", type="string", example="pcs", description="Satuan barang"),
     *             @OA\Property(property="masa_pakai", type="integer", example=12, description="Masa pakai dalam bulan"),
     *             @OA\Property(property="merk", type="string", example="Merk Baru", description="Merk barang"),
     *             @OA\Property(property="jumlah_default", type="integer", example=5, description="Jumlah default barang"),
     *             @OA\Property(property="urutan", type="integer", example=2, description="Urutan barang")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Barang berhasil ditambahkan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Barang berhasil disimpan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Jenis barang tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Jenis barang tidak ditemukan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 @OA\Property(
     *                     property="nama",
     *                     type="array",
     *                     @OA\Items(type="string", example="The nama field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="jenis_barang_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The jenis barang id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="harga",
     *                     type="array",
     *                     @OA\Items(type="string", example="The harga field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error: Internal server error")
     *         )
     *     )
     * )
     */
    public function add(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required',
                'jenis_barang_id' => 'required',
                'harga' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisBarang = JenisBarang::find($request->jenis_barang_id);
            if (!$jenisBarang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis barang tidak ditemukan'
                ], 404);
            }

            $harga = str_replace(",", "", $request->harga);

            Barang::create([
                'nama' => $request->nama,
                'jenis_barang_id' => $request->jenis_barang_id,
                'jenis_barang' => $jenisBarang->nama,
                'harga' => $harga,
                'satuan' => $request->satuan,
                'masa_pakai' => $request->masa_pakai,
                'merk' => $request->merk,
                'jumlah_default' => $request->jumlah_default,
                'urutan' => $request->urutan,
                'created_by' => Auth::user()->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil disimpan'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/barang/update/{id}",
     *     summary="Update data barang",
     *     description="Mengupdate data barang berdasarkan ID",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID barang yang ingin diupdate",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data barang yang akan diupdate",
     *         @OA\JsonContent(
     *             required={"nama", "jenis_barang_id", "harga"},
     *             @OA\Property(property="nama", type="string", example="Barang Updated", description="Nama barang"),
     *             @OA\Property(property="jenis_barang_id", type="integer", example=1, description="ID jenis barang"),
     *             @OA\Property(property="harga", type="string", example="200,000", description="Harga barang (bisa dengan koma sebagai pemisah ribuan)"),
     *             @OA\Property(property="satuan", type="string", example="pcs", description="Satuan barang"),
     *             @OA\Property(property="masa_pakai", type="integer", example=24, description="Masa pakai dalam bulan"),
     *             @OA\Property(property="merk", type="string", example="Merk Updated", description="Merk barang"),
     *             @OA\Property(property="jumlah_default", type="integer", example=8, description="Jumlah default barang"),
     *             @OA\Property(property="urutan", type="integer", example=3, description="Urutan barang")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Barang berhasil diupdate",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Barang berhasil diupdate")
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
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validasi gagal"),
     *             @OA\Property(
     *                 property="errors", 
     *                 type="object",
     *                 @OA\Property(
     *                     property="nama",
     *                     type="array",
     *                     @OA\Items(type="string", example="The nama field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="jenis_barang_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The jenis barang id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="harga",
     *                     type="array",
     *                     @OA\Items(type="string", example="The harga field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error: Internal server error")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $barang = Barang::whereNull('deleted_at')->find($id);
            if (!$barang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nama' => 'required',
                'jenis_barang_id' => 'required',
                'harga' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisBarang = JenisBarang::find($request->jenis_barang_id);
            if (!$jenisBarang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis barang tidak ditemukan'
                ], 404);
            }

            $harga = str_replace(",", "", $request->harga);

            $barang->update([
                'nama' => $request->nama,
                'jenis_barang_id' => $request->jenis_barang_id,
                'jenis_barang' => $jenisBarang->nama,
                'harga' => $harga,
                'satuan' => $request->satuan,
                'masa_pakai' => $request->masa_pakai,
                'merk' => $request->merk,
                'jumlah_default' => $request->jumlah_default,
                'urutan' => $request->urutan,
                'updated_by' => Auth::user()->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil diupdate'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/barang/delete/{id}",
     *     summary="Hapus data barang",
     *     description="Menghapus data barang berdasarkan ID (soft delete)",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID barang yang ingin dihapus",
     *         required=true,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Barang berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Barang berhasil dihapus")
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
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error: Internal server error")
     *         )
     *     )
     * )
     */
    public function Delete($id)
    {
        try {
            $barang = Barang::whereNull('deleted_at')->find($id);
            if (!$barang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            $barang->update([
                'deleted_at' => Carbon::now(),
                'deleted_by' => Auth::user()->full_name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}