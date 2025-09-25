<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * @OA\Tag(
 *     name="Training",
 *     description="API endpoints for Training management"
 * )
 */
class TrainingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/training/list",
     *     tags={"Training"},
     *     summary="Get all training data",
     *     description="Retrieve all training records with pagination",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by training name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="Training Laravel"),
     *                         @OA\Property(property="jenis", type="string", example="Programming"),
     *                         @OA\Property(property="jp", type="integer", example=8),
     *                         @OA\Property(property="menit", type="integer", example=60),
     *                         @OA\Property(property="total", type="integer", example=480),
     *                         @OA\Property(property="created_by", type="string", example="John Doe"),
     *                         @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                         @OA\Property(property="deleted_by", type="string", example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", example=null)
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="last_page", type="integer", example=10)
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
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error"),
     *             @OA\Property(property="error", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');

            $query = Training::with(['creator', 'updater'])->get();
            if ($search) {
                $query->where('nama', 'LIKE', "%{$search}%")
                    ->orWhere('jenis', 'LIKE', "%{$search}%");
            }

            return response()->json([
                'success' => true,
                'message' => 'Data retrieved successfully',
                'data' => $query
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/training/view/{id}",
     *     tags={"Training"},
     *     summary="Get training by ID",
     *     description="Retrieve a specific training record by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Training ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Training Laravel"),
     *                 @OA\Property(property="jenis", type="string", example="Programming"),
     *                 @OA\Property(property="jp", type="integer", example=8),
     *                 @OA\Property(property="menit", type="integer", example=60),
     *                 @OA\Property(property="total", type="integer", example=480),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                 @OA\Property(property="deleted_by", type="string", example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time", example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Training not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Training not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error"),
     *             @OA\Property(property="error", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function view(int $id): JsonResponse
    {
        try {
            $training = Training::find($id);

            if (!$training) {
                return response()->json([
                    'success' => false,
                    'message' => 'Training not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data retrieved successfully',
                'data' => $training
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/training/add",
     *     tags={"Training"},
     *     summary="Create new training",
     *     description="Create a new training record",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama", "jenis", "jp", "menit"},
     *             @OA\Property(property="nama", type="string", example="Training Laravel"),
     *             @OA\Property(property="jenis", type="string", example="Programming"),
     *             @OA\Property(property="jp", type="integer", example=8),
     *             @OA\Property(property="menit", type="integer", example=60)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Training created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Training created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Training Laravel"),
     *                 @OA\Property(property="jenis", type="string", example="Programming"),
     *                 @OA\Property(property="jp", type="integer", example=8),
     *                 @OA\Property(property="menit", type="integer", example=60),
     *                 @OA\Property(property="total", type="integer", example=480),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="updated_by", type="string", example=null),
     *                 @OA\Property(property="deleted_by", type="string", example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time", example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="nama", type="array",
     *                     @OA\Items(type="string", example="The nama field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error"),
     *             @OA\Property(property="error", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function add(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'jenis' => 'required|string|max:255',
                'jp' => 'required|integer|min:1',
                'menit' => 'required|integer|min:1',
            ], [
                'required' => ':attribute harus diisi',
                'string' => ':attribute harus berupa teks',
                'integer' => ':attribute harus berupa angka',
                'min' => ':attribute minimal :min',
                'max' => ':attribute maksimal :max'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $training = Training::create([
                'nama' => $request->nama,
                'jenis' => $request->jenis,
                'jp' => $request->jp,
                'menit' => $request->menit,
                'total' => $request->jp * $request->menit,
                'created_by' => Auth::user()->full_name ?? Auth::user()->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Training created successfully',
                'data' => $training
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/training/update/{id}",
     *     tags={"Training"},
     *     summary="Update training",
     *     description="Update an existing training record",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Training ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama", "jenis", "jp", "menit"},
     *             @OA\Property(property="nama", type="string", example="Updated Training Laravel"),
     *             @OA\Property(property="jenis", type="string", example="Advanced Programming"),
     *             @OA\Property(property="jp", type="integer", example=12),
     *             @OA\Property(property="menit", type="integer", example=45)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Training updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Training updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Updated Training Laravel"),
     *                 @OA\Property(property="jenis", type="string", example="Advanced Programming"),
     *                 @OA\Property(property="jp", type="integer", example=12),
     *                 @OA\Property(property="menit", type="integer", example=45),
     *                 @OA\Property(property="total", type="integer", example=540),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                 @OA\Property(property="deleted_by", type="string", example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-02T00:00:00Z"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time", example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Training not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Training not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="nama", type="array",
     *                     @OA\Items(type="string", example="The nama field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error"),
     *             @OA\Property(property="error", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $training = Training::find($id);

            if (!$training) {
                return response()->json([
                    'success' => false,
                    'message' => 'Training not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'jenis' => 'required|string|max:255',
                'jp' => 'required|integer|min:1',
                'menit' => 'required|integer|min:1',
            ], [
                'required' => ':attribute harus diisi',
                'string' => ':attribute harus berupa teks',
                'integer' => ':attribute harus berupa angka',
                'min' => ':attribute minimal :min',
                'max' => ':attribute maksimal :max'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $training->update([
                'nama' => $request->nama,
                'jenis' => $request->jenis,
                'jp' => $request->jp,
                'menit' => $request->menit,
                'total' => $request->jp * $request->menit,
                'updated_by' => Auth::user()->full_name ?? Auth::user()->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Training updated successfully',
                'data' => $training->fresh()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/training/delete/{id}",
     *     tags={"Training"},
     *     summary="Delete training",
     *     description="Soft delete a training record",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Training ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Training deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Training deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Training not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Training not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error"),
     *             @OA\Property(property="error", type="string", example="Error message details")
     *         )
     *     )
     * )
     */
    public function delete(int $id): JsonResponse
    {
        try {
            $training = Training::find($id);

            if (!$training) {
                return response()->json([
                    'success' => false,
                    'message' => 'Training not found'
                ], 404);
            }

            $training->update([
                'deleted_by' => Auth::user()->full_name ?? Auth::user()->name
            ]);

            $training->delete();

            return response()->json([
                'success' => true,
                'message' => 'Training deleted successfully'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}