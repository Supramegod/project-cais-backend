<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\BarangRequest;
use App\Http\Requests\BarangDefaultQtyRequest;
use App\Http\Requests\BarangDefaultQtyBulkRequest;
use App\Models\Barang;
use App\Models\JenisBarang;
use App\Models\Kebutuhan;
use App\Models\BarangDefaultQty;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Data berhasil diambil"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function list()
    {
        $data = Barang::with('jenisBarang')
            ->whereNull('deleted_at')
            ->get();

        return $this->successResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/barang/view/{id}",
     *     summary="Get detail barang",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Data barang berhasil diambil"),
     *     @OA\Response(response=404, description="Data tidak ditemukan")
     * )
     */
    public function view($id)
    {
        $data = Barang::with(['jenisBarang', 'defaultQty'])
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (! $data) {
            return $this->notFoundResponse();
        }

        return $this->successResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/barang/add",
     *     summary="Tambah barang baru",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Barang berhasil ditambahkan"),
     *     @OA\Response(response=404, description="Jenis barang tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function add(BarangRequest $request)
    {
        $jenisBarang = JenisBarang::find($request->jenis_barang_id);
        if (! $jenisBarang) {
            return $this->notFoundResponse('Jenis barang tidak ditemukan');
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
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);

        return $this->messageResponse('Barang berhasil disimpan');
    }

    /**
     * @OA\Put(
     *     path="/api/barang/update/{id}",
     *     summary="Update data barang",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Barang berhasil diupdate"),
     *     @OA\Response(response=404, description="Data tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function update(BarangRequest $request, $id)
    {
        $barang = Barang::whereNull('deleted_at')->find($id);
        if (! $barang) {
            return $this->notFoundResponse();
        }

        $jenisBarang = JenisBarang::find($request->jenis_barang_id);
        if (! $jenisBarang) {
            return $this->notFoundResponse('Jenis barang tidak ditemukan');
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
            'updated_by' => Auth::user()->full_name,
        ]);

        return $this->messageResponse('Barang berhasil diupdate');
    }

    /**
     * @OA\Delete(
     *     path="/api/barang/delete/{id}",
     *     summary="Hapus data barang",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Barang berhasil dihapus"),
     *     @OA\Response(response=404, description="Data tidak ditemukan")
     * )
     */
    public function delete($id)
    {
        $barang = Barang::whereNull('deleted_at')->find($id);
        if (! $barang) {
            return $this->notFoundResponse();
        }

        $barang->update([
            'deleted_at' => Carbon::now(),
            'deleted_by' => Auth::user()->full_name,
        ]);

        return $this->messageResponse('Barang berhasil dihapus');
    }

    /*
    |--------------------------------------------------------------------------
    | Default Quantity Management Endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * @OA\Get(
     *     path="/api/barang/default-qty/{id}",
     *     summary="Get data default quantity barang",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Data berhasil diambil")
     * )
     */
    public function getDefaultQty($id)
    {
        $data = BarangDefaultQty::where('barang_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('layanan')
            ->get();

        return $this->successResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/barang/default-qty/save",
     *     summary="Simpan atau update default quantity",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Data berhasil disimpan"),
     *     @OA\Response(response=404, description="Layanan tidak ditemukan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function saveDefaultQty(BarangDefaultQtyRequest $request)
    {
        // Cek apakah layanan/kebutuhan ada
        $layanan = Kebutuhan::find($request->layanan_id);
        if (! $layanan) {
            return $this->notFoundResponse('Layanan tidak ditemukan');
        }

        // Cek apakah sudah ada data untuk barang_id dan layanan
        $existing = BarangDefaultQty::where('barang_id', $request->barang_id)
            ->where('layanan_id', $request->layanan_id)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $existing->update([
                'qty_default' => $request->qty_default,
                'updated_by' => Auth::user()->full_name,
            ]);

            return $this->successResponse(
                ['id' => $existing->id, 'action' => 'updated'],
                'Data default qty berhasil diupdate'
            );
        }

        $newDefaultQty = BarangDefaultQty::create([
            'barang_id' => $request->barang_id,
            'layanan_id' => $request->layanan_id,
            'qty_default' => $request->qty_default,
            'layanan' => $layanan->nama,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);

        return $this->successResponse(
            ['id' => $newDefaultQty->id, 'action' => 'created'],
            'Data default qty berhasil disimpan'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/barang/default-qty/delete/{id}",
     *     summary="Hapus default quantity",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Data berhasil dihapus"),
     *     @OA\Response(response=404, description="Data tidak ditemukan")
     * )
     */
    public function deleteDefaultQty($id)
    {
        $defaultQty = BarangDefaultQty::whereNull('deleted_at')->find($id);

        if (! $defaultQty) {
            return $this->notFoundResponse();
        }

        $defaultQty->update([
            'deleted_at' => Carbon::now(),
            'deleted_by' => Auth::user()->full_name,
        ]);

        return $this->messageResponse('Data default qty berhasil dihapus');
    }

    /**
     * @OA\Get(
     *     path="/api/barang/{barangId}/default-qty/{layananId}",
     *     summary="Get default quantity by barang and layanan",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="barangId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="layananId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Data berhasil diambil"),
     *     @OA\Response(response=404, description="Data tidak ditemukan")
     * )
     */
    public function getDefaultQtyByLayanan($barangId, $layananId)
    {
        $data = BarangDefaultQty::where('barang_id', $barangId)
            ->where('layanan_id', $layananId)
            ->whereNull('deleted_at')
            ->first();

        if (! $data) {
            return $this->notFoundResponse();
        }

        return $this->successResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/barang/default-qty/bulk-save",
     *     summary="Bulk save default quantities",
     *     tags={"Barang"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Data berhasil disimpan"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function bulkSaveDefaultQty(BarangDefaultQtyBulkRequest $request)
    {
        $result = DB::transaction(function () use ($request) {
            $created = 0;
            $updated = 0;

            foreach ($request->quantities as $qty) {
                // Cek layanan exist
                $layanan = Kebutuhan::find($qty['layanan_id']);
                if (! $layanan) {
                    continue; // Skip jika layanan tidak ditemukan
                }

                // Cek existing record
                $existing = BarangDefaultQty::where('barang_id', $request->barang_id)
                    ->where('layanan_id', $qty['layanan_id'])
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    $existing->update([
                        'qty_default' => $qty['qty_default'],
                        'updated_by' => Auth::user()->full_name,
                    ]);
                    $updated++;
                } else {
                    BarangDefaultQty::create([
                        'barang_id' => $request->barang_id,
                        'layanan_id' => $qty['layanan_id'],
                        'qty_default' => $qty['qty_default'],
                        'layanan' => $layanan->nama,
                        'created_by' => Auth::user()->full_name,
                        'created_by_user_id' => Auth::id(),
                    ]);
                    $created++;
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });

        $total = $result['created'] + $result['updated'];

        return $this->successResponse(
            [
                'created' => $result['created'],
                'updated' => $result['updated'],
                'total' => $total,
            ],
            "Berhasil menyimpan {$total} default qty"
        );
    }
}
