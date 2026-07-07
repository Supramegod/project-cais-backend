<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManagementFeeRequest;
use App\Models\ManagementFee;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Management Fee",
 *     description="Endpoints untuk manajemen data management fee"
 * )
 */
class ManagementFeeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/management-fee/list",
     *     summary="Get all management fees ",
     *     tags={"Management Fee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function list()
    {
        $data = ManagementFee::all();

        return $this->successResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/management-fee/add",
     *     summary="Create a new management fee",
     *     tags={"Management Fee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=201, description="Management fee created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function add(ManagementFeeRequest $request)
    {
        $managementFee = ManagementFee::create([
            'nama' => $request->nama,
            'created_by' => Auth::user()->full_name ?? 'System',
        ]);

        return $this->successResponse($managementFee, 'Management Fee berhasil dibuat', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/management-fee/view/{id}",
     *     summary="Get management fee by ID",
     *     tags={"Management Fee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Management fee not found")
     * )
     */
    public function view($id)
    {
        $managementFee = ManagementFee::find($id);

        if (! $managementFee) {
            return $this->notFoundResponse('Management Fee tidak ditemukan');
        }

        return $this->successResponse($managementFee);
    }

    /**
     * @OA\Put(
     *     path="/api/management-fee/update/{id}",
     *     summary="Update management fee",
     *     tags={"Management Fee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Management fee updated successfully"),
     *     @OA\Response(response=404, description="Management fee not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(ManagementFeeRequest $request, $id)
    {
        $managementFee = ManagementFee::find($id);

        if (! $managementFee) {
            return $this->notFoundResponse('Management Fee tidak ditemukan');
        }

        $managementFee->update([
            'nama' => $request->nama,
            'updated_by' => Auth::user()->full_name ?? 'System',
        ]);

        return $this->successResponse($managementFee, 'Management Fee berhasil diupdate');
    }

    /**
     * @OA\Delete(
     *     path="/api/management-fee/delete/{id}",
     *     summary="Delete management fee",
     *     tags={"Management Fee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Management fee deleted successfully"),
     *     @OA\Response(response=404, description="Management fee not found")
     * )
     */
    public function delete($id)
    {
        $managementFee = ManagementFee::find($id);

        if (! $managementFee) {
            return $this->notFoundResponse('Management Fee tidak ditemukan');
        }

        $managementFee->update([
            'deleted_by' => Auth::user()->full_name ?? 'System',
        ]);
        $managementFee->delete();

        return $this->messageResponse('Management Fee berhasil dihapus');
    }

    /**
     * @OA\Get(
     *     path="/api/management-fee/list-all",
     *     summary="Get all management fees without pagination (optional)",
     *     tags={"Management Fee"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function listAll()
    {
        $data = ManagementFee::all(['id', 'nama']);

        return $this->successResponse($data);
    }
}
