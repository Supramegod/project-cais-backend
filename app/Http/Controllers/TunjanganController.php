<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TunjanganPosisi;
use App\Models\Kebutuhan;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
     *     description="Retrieve paginated list of position allowances with related data",
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
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="nama", type="string"),
     *                         @OA\Property(property="nominal", type="number", format="float"),
     *                         @OA\Property(property="nama_kebutuhan", type="string"),
     *                         @OA\Property(property="nama_jabatan", type="string"),
     *                         @OA\Property(property="created_at", type="string"),
     *                         @OA\Property(property="created_by", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function list(Request $request)
{
    try {
        $search = $request->get('search');

        $query = TunjanganPosisi::with(['kebutuhan:id,nama', 'position:id,name'])
            ->select('id', 'kebutuhan_id', 'position_id', 'nama', 'nominal', 'created_at', 'created_by')
            ->active();

        if ($search) {
            $query->where('nama', 'like', '%' . $search . '%');
        }

        // ambil semua data (tanpa paginate)
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

        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully',
            'data' => $data
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve data',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /**
     * @OA\Get(
     *     path="/api/tunjangan/view/{id}",
     *     summary="Get specific tunjangan posisi",
     *     description="Retrieve specific position allowance by ID",
     *     operationId="getTunjangan",
     *     tags={"Tunjangan Posisi"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tunjangan ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="kebutuhan_id", type="integer"),
     *                 @OA\Property(property="position_id", type="integer"),
     *                 @OA\Property(property="nama", type="string"),
     *                 @OA\Property(property="nominal", type="number", format="float"),
     *                 @OA\Property(property="kebutuhan", type="object"),
     *                 @OA\Property(property="position", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Data not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function view($id)
    {
        try {
            $data = TunjanganPosisi::with(['kebutuhan:id,nama', 'position:id,name'])
                ->active()
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Data retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/tunjangan/add",
     *     summary="Create new tunjangan posisi",
     *     description="Create a new position allowance",
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
     *     @OA\Response(
     *         response=201,
     *         description="Data created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tunjangan created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function add(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'nominal' => 'required|numeric|min:0',
                'kebutuhan_id' => 'required|exists:m_kebutuhan,id',
                'position_id' => 'required|exists:mysqlhris.m_position,id'
            ], [
                'required' => 'Field :attribute harus diisi',
                'numeric' => 'Field :attribute harus berupa angka',
                'exists' => 'Data :attribute tidak valid',
                'min' => 'Field :attribute minimal :min'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = TunjanganPosisi::create([
                'kebutuhan_id' => $request->kebutuhan_id,
                'position_id' => $request->position_id,
                'nama' => $request->nama,
                'nominal' => $request->nominal
            ]);

            $data->load(['kebutuhan:id,nama', 'position:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Tunjangan: ' . $request->nama . ' berhasil disimpan',
                'data' => $data
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/tunjangan/update/{id}",
     *     summary="Update tunjangan posisi",
     *     description="Update existing position allowance",
     *     operationId="updateTunjangan",
     *     tags={"Tunjangan Posisi"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tunjangan ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
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
     *     @OA\Response(
     *         response=200,
     *         description="Data updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tunjangan updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Data not found"),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $data = TunjanganPosisi::active()->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'nominal' => 'required|numeric|min:0',
                'kebutuhan_id' => 'required|exists:m_kebutuhan,id',
                'position_id' => 'required|exists:mysqlhris.m_position,id'
            ], [
                'required' => 'Field :attribute harus diisi',
                'numeric' => 'Field :attribute harus berupa angka',
                'exists' => 'Data :attribute tidak valid',
                'min' => 'Field :attribute minimal :min'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data->update([
                'kebutuhan_id' => $request->kebutuhan_id,
                'position_id' => $request->position_id,
                'nama' => $request->nama,
                'nominal' => $request->nominal
            ]);

            $data->load(['kebutuhan:id,nama', 'position:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Tunjangan: ' . $request->nama . ' berhasil diupdate',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update data',
                'error' => $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/tunjangan/delete/{id}",
     *     summary="Delete tunjangan posisi",
     *     description="Soft delete position allowance",
     *     operationId="deleteTunjangan",
     *     tags={"Tunjangan Posisi"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tunjangan ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Data deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Berhasil menghapus data")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Data not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function delete($id): JsonResponse
    {
        try {
            $data = TunjanganPosisi::active()->findOrFail($id);
            $data->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus data'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete data',
                'error' => $e->getMessage()
            ], $e->getCode() == 404 ? 404 : 500);
        }
    }


}