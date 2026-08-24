<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\TunjanganRequest;
use App\Models\TunjanganPosisi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Tunjangan Posisi",
 *     description="API endpoints for managing position allowances"
 * )
 */
class TunjanganController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tunjangan/list",
     *     summary="Get list of tunjangan posisi",
     *     description="Retrieve list of position allowances with related data",
     *     operationId="getTunjanganList",
     *     tags={"Tunjangan Posisi"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by tunjangan name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function list(Request $request)
    {
        $search = $request->get('search');

        $query = TunjanganPosisi::with(['kebutuhan:id,nama', 'position:id,name'])
            ->select('id', 'kebutuhan_id', 'position_id', 'nama', 'nominal', 'created_at', 'created_by')
            ->active();

        if ($search) {
            $query->where('nama', 'like', '%' . $search . '%');
        }

        $data = $query->get()->transform(function ($item) {
            return [
                'id' => $item->id,
                'nama' => $item->nama,
                'nominal' => $item->nominal,
                'nama_kebutuhan' => $item->kebutuhan->nama ?? null,
                'nama_jabatan' => $item->position->name ?? null,
                'created_at' => $item->created_at,
                'created_by' => $item->created_by,
            ];
        });

        return $this->successResponse($data, 'Data retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/tunjangan/view/{id}",
     *     summary="Get specific tunjangan posisi",
     *     operationId="getTunjangan",
     *     tags={"Tunjangan Posisi"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Data not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function view($id)
    {
        $data = TunjanganPosisi::with(['kebutuhan:id,nama', 'position:id,name'])
            ->active()
            ->find($id);

        if (! $data) {
            return $this->notFoundResponse();
        }

        return $this->successResponse($data, 'Data retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/tunjangan/add",
     *     summary="Create new tunjangan posisi",
     *     operationId="createTunjangan",
     *     tags={"Tunjangan Posisi"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"nama", "nominal", "kebutuhan_id", "position_id"},
     *             @OA\Property(property="nama", type="string", example="Tunjangan Jabatan"),
     *             @OA\Property(property="nominal", type="number", format="float", example=1000000),
     *             @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *             @OA\Property(property="position_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Data created successfully"),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function add(TunjanganRequest $request)
    {
        $data = TunjanganPosisi::create([
            'kebutuhan_id' => $request->kebutuhan_id,
            'position_id' => $request->position_id,
            'nama' => $request->nama,
            'nominal' => $request->nominal,
        ]);

        $data->load(['kebutuhan:id,nama', 'position:id,name']);

        return $this->createdResponse($data, 'Tunjangan: ' . $request->nama . ' berhasil disimpan');
    }

    /**
     * @OA\Put(
     *     path="/api/tunjangan/update/{id}",
     *     summary="Update tunjangan posisi",
     *     operationId="updateTunjangan",
     *     tags={"Tunjangan Posisi"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"nama", "nominal", "kebutuhan_id", "position_id"},
     *             @OA\Property(property="nama", type="string", example="Tunjangan Jabatan"),
     *             @OA\Property(property="nominal", type="number", format="float", example=1000000),
     *             @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *             @OA\Property(property="position_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Data updated successfully"),
     *     @OA\Response(response=404, description="Data not found"),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(TunjanganRequest $request, $id): JsonResponse
    {
        $data = TunjanganPosisi::active()->find($id);

        if (! $data) {
            return $this->notFoundResponse();
        }

        $data->update([
            'kebutuhan_id' => $request->kebutuhan_id,
            'position_id' => $request->position_id,
            'nama' => $request->nama,
            'nominal' => $request->nominal,
        ]);

        $data->load(['kebutuhan:id,nama', 'position:id,name']);

        return $this->successResponse($data, 'Tunjangan: ' . $request->nama . ' berhasil diupdate');
    }

    /**
     * @OA\Delete(
     *     path="/api/tunjangan/delete/{id}",
     *     summary="Delete tunjangan posisi",
     *     operationId="deleteTunjangan",
     *     tags={"Tunjangan Posisi"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Data deleted successfully"),
     *     @OA\Response(response=404, description="Data not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function delete($id): JsonResponse
    {
        $data = TunjanganPosisi::active()->find($id);

        if (! $data) {
            return $this->notFoundResponse();
        }

        $data->delete();

        return $this->messageResponse('Berhasil menghapus data');
    }
}
