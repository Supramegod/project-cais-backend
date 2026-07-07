<?php

namespace App\Http\Controllers;

use App\Http\Requests\JenisBarangRequest;
use App\Models\JenisBarang;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
     *     @OA\Response(response=200, description="Success - Data berhasil diambil"),
     *     @OA\Response(response=401, description="Unauthorized - Token tidak valid")
     * )
     */
    public function list()
    {
        $data = JenisBarang::whereNull('deleted_at')->get();

        return $this->successResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/jenis-barang/view/{id}",
     *     summary="Get detail jenis barang",
     *     description="Menampilkan detail jenis barang berdasarkan ID",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Success - Data berhasil ditemukan"),
     *     @OA\Response(response=401, description="Unauthorized - Token tidak valid"),
     *     @OA\Response(response=404, description="Data tidak ditemukan")
     * )
     */
    public function view($id)
    {
        $data = JenisBarang::where('id', $id)->whereNull('deleted_at')->first();

        if (! $data) {
            return $this->notFoundResponse();
        }

        return $this->successResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/jenis-barang/list-detail/{id}",
     *     summary="Get list barang by jenis barang",
     *     description="Menampilkan daftar barang berdasarkan jenis barang tertentu",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Success - Data berhasil diambil"),
     *     @OA\Response(response=401, description="Unauthorized - Token tidak valid")
     * )
     */
    public function listdetail($id)
    {
        $data = DB::table('m_jenis_barang')
            ->join('m_barang', 'm_jenis_barang.id', '=', 'm_barang.jenis_barang_id')
            ->select('m_jenis_barang.nama as jenis_barang', 'm_barang.nama as nama_barang', 'm_barang.harga as harga', 'm_barang.satuan as satuan', 'm_barang.masa_pakai as masa_pakai', 'm_barang.merk as merk')
            ->where('m_jenis_barang.id', $id)
            ->whereNull('m_jenis_barang.deleted_at')
            ->get();

        return $this->successResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/jenis-barang/add",
     *     summary="Create new jenis barang",
     *     description="Menambahkan jenis barang baru ke dalam sistem",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"nama"}, @OA\Property(property="nama", type="string", maxLength=255, example="Elektronik"))),
     *     @OA\Response(response=201, description="Success - Jenis barang berhasil ditambahkan"),
     *     @OA\Response(response=401, description="Unauthorized - Token tidak valid"),
     *     @OA\Response(response=422, description="Validation error - Data tidak valid")
     * )
     */
    public function add(JenisBarangRequest $request)
    {
        $jenisBarang = JenisBarang::create([
            'nama' => $request->nama,
            'created_at' => Carbon::now(),
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);

        return $this->successResponse($jenisBarang, 'Jenis Barang berhasil disimpan', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/jenis-barang/update/{id}",
     *     summary="Update jenis barang",
     *     description="Memperbarui data jenis barang berdasarkan ID",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"nama"}, @OA\Property(property="nama", type="string", maxLength=255, example="Elektronik Updated"))),
     *     @OA\Response(response=200, description="Success - Jenis barang berhasil diperbarui"),
     *     @OA\Response(response=401, description="Unauthorized - Token tidak valid"),
     *     @OA\Response(response=404, description="Data tidak ditemukan"),
     *     @OA\Response(response=422, description="Validation error - Data tidak valid")
     * )
     */
    public function update(JenisBarangRequest $request, $id)
    {
        $jenisBarang = JenisBarang::where('id', $id)->whereNull('deleted_at')->first();

        if (! $jenisBarang) {
            return $this->notFoundResponse();
        }

        $jenisBarang->update([
            'nama' => $request->nama,
            'updated_at' => Carbon::now(),
            'updated_by' => Auth::user()->full_name,
        ]);

        return $this->successResponse($jenisBarang, 'Jenis Barang berhasil diupdate');
    }

    /**
     * @OA\Delete(
     *     path="/api/jenis-barang/delete/{id}",
     *     summary="Delete jenis barang",
     *     description="Menghapus jenis barang berdasarkan ID (soft delete)",
     *     tags={"Jenis Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Success - Jenis barang berhasil dihapus"),
     *     @OA\Response(response=401, description="Unauthorized - Token tidak valid"),
     *     @OA\Response(response=404, description="Data tidak ditemukan")
     * )
     */
    public function delete($id)
    {
        $jenisBarang = JenisBarang::where('id', $id)->whereNull('deleted_at')->first();

        if (! $jenisBarang) {
            return $this->notFoundResponse();
        }

        // Set audit column lalu soft-delete via SoftDeletes (deleted_at bukan
        // fillable, jadi tak bisa lewat mass-update — harus pakai ->delete()).
        $jenisBarang->update(['deleted_by' => Auth::user()->full_name]);
        $jenisBarang->delete();

        return $this->messageResponse('Jenis Barang berhasil dihapus');
    }
}
