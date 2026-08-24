<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Supplier",
 *     description="API Endpoints untuk Management Supplier"
 * )
 */
class SupplierController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/supplier/list",
     *     summary="Get list of all suppliers",
     *     tags={"Supplier"},
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
     *                     @OA\Property(property="nama_supplier", type="string", example="PT. Contoh Supplier"),
     *                     @OA\Property(property="alamat", type="string", example="Jl. Contoh Alamat No. 123"),
     *                     @OA\Property(property="kontak", type="string", example="08123456789"),
     *                     @OA\Property(property="pic", type="string", example="John Doe"),
     *                     @OA\Property(property="npwp", type="string", example="01.234.567.8-910.000"),
     *                     @OA\Property(property="kategori_barang", type="string", example="Chemical"),
     *                     @OA\Property(property="created_by", type="string", example="Admin"),
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
    public function list()
    {
        $data = Supplier::getAllSuppliers();

        return $this->successResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/supplier/view/{id}",
     *     summary="Get supplier detail by ID",
     *     tags={"Supplier"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Supplier ID",
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
     *         description="Supplier not found"
     *     )
     * )
     */
    public function view($id)
    {
        $data = Supplier::select(
            'id',
            'nama_supplier',
            'alamat',
            'kontak',
            'pic',
            'npwp',
            'kategori_barang',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by'
        )->find($id);

        if (! $data) {
            return $this->notFoundResponse('Data supplier tidak ditemukan');
        }

        return $this->successResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/supplier/add",
     *     summary="Create a new supplier",
     *     tags={"Supplier"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama", "pic", "alamat", "kontak"},
     *             @OA\Property(property="nama", type="string", example="PT. Contoh Supplier", description="Nama supplier"),
     *             @OA\Property(property="pic", type="string", example="John Doe", description="Person In Charge"),
     *             @OA\Property(property="alamat", type="string", example="Jl. Contoh Alamat No. 123", description="Alamat supplier"),
     *             @OA\Property(property="kontak", type="string", example="08123456789", description="Kontak supplier"),
     *             @OA\Property(property="npwp", type="string", example="01.234.567.8-910.000", description="NPWP supplier"),
     *             @OA\Property(property="kategori_barang", type="string", example="Chemical", description="Kategori barang yang disupply")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Supplier created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier berhasil dibuat"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function add(SupplierRequest $request)
    {
        $supplier = Supplier::create([
            'nama_supplier' => $request->nama,
            'pic' => $request->pic,
            'alamat' => $request->alamat,
            'kontak' => $request->kontak,
            'npwp' => $request->npwp ?? null,
            'kategori_barang' => $request->kategori_barang ?? null,
            'created_by' => Auth::user()->full_name ?? 'System',
            'created_by_user_id' => Auth::id()
        ]);

        return $this->successResponse($supplier, 'Supplier berhasil dibuat', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/supplier/update/{id}",
     *     summary="Update an existing supplier",
     *     tags={"Supplier"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Supplier ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama", "pic", "alamat", "kontak"},
     *             @OA\Property(property="nama", type="string", example="PT. Supplier Updated", description="Nama supplier"),
     *             @OA\Property(property="pic", type="string", example="Jane Doe", description="Person In Charge"),
     *             @OA\Property(property="alamat", type="string", example="Jl. Alamat Baru No. 456", description="Alamat supplier"),
     *             @OA\Property(property="kontak", type="string", example="087654321", description="Kontak supplier"),
     *             @OA\Property(property="npwp", type="string", example="02.345.678.9-101.000", description="NPWP supplier"),
     *             @OA\Property(property="kategori_barang", type="string", example="Equipment", description="Kategori barang yang disupply")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supplier updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Supplier not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(SupplierRequest $request, $id)
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->notFoundResponse('Supplier tidak ditemukan');
        }

        $supplier->update([
            'nama_supplier' => $request->nama,
            'pic' => $request->pic,
            'alamat' => $request->alamat,
            'kontak' => $request->kontak,
            'npwp' => $request->npwp ?? null,
            'kategori_barang' => $request->kategori_barang ?? null,
            'updated_by' => Auth::user()->full_name ?? 'System'
        ]);

        return $this->successResponse($supplier, 'Supplier berhasil diupdate');
    }

    /**
     * @OA\Delete(
     *     path="/api/supplier/delete/{id}",
     *     summary="Delete supplier",
     *     tags={"Supplier"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Supplier ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supplier deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Supplier not found"
     *     )
     * )
     */
    public function delete($id)
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->notFoundResponse('Supplier tidak ditemukan');
        }

        $supplier->update([
            'deleted_by' => Auth::user()->full_name ?? 'System'
        ]);
        $supplier->delete();

        return $this->messageResponse('Supplier berhasil dihapus');
    }

}