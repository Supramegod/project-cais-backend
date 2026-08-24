<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Position\PositionSaveRequest;
use App\Http\Requests\Position\PositionEditRequest;
use App\Http\Requests\Position\PositionRequirementAddRequest;
use App\Http\Requests\Position\PositionRequirementEditRequest;
use App\Models\Position;
use App\Models\RequirementPosisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Position",
 *     description="API endpoints for Position management"
 * )
 */
class PositionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/position/list",
     *     operationId="getPositionList",
     *     tags={"Position"},
     *     summary="Get all positions",
     *     description="Retrieve all active position records. Returns all positions by default, with optional filtering by company or service.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="entitas",
     *         in="query",
     *         description="Optional filter by company ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="layanan",
     *         in="query",
     *         description="Optional filter by service ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success - Returns all active positions (filtered if parameters provided)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Software Developer"),
     *                     @OA\Property(property="description", type="string", example="Develop software applications"),
     *                     @OA\Property(property="company_id", type="integer", example=3),
     *                     @OA\Property(property="layanan_id", type="integer", example=2),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="created_by", type="string", example="John Doe"),
     *                     @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                     @OA\Property(property="company", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Company Name")
     *                     ),
     *                     @OA\Property(property="kebutuhan", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="IT Services")
     *                     )
     *                 )
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
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function list(Request $request)
    {
        $query = Position::with([
            'company.creator',
            'company.updater',
            'kebutuhan:id,nama', // ambil hanya id & nama kebutuhan
            'creator',
            'updater'
        ])->where('is_active', true);

        if ($request->filled('entitas')) {
            $query->where('company_id', $request->entitas);
        }

        if ($request->filled('layanan')) {
            $query->where('layanan_id', $request->layanan);
        }

        $data = $query->orderBy('created_at', 'desc')->get();

        // Mapping supaya kebutuhan return id & nama aja
        $mappedData = $data->map(function ($pos) {
            return [
                'id' => $pos->id,
                'name' => $pos->name,
                'description' => $pos->description,
                'company_id' => $pos->company_id,
                'company_name' => $pos->company ? $pos->company->name : null, // tambahkan ini
                'layanan_id' => $pos->layanan_id,
                'kebutuhan' => $pos->kebutuhan ? [
                    'id' => $pos->kebutuhan->id,
                    'nama' => $pos->kebutuhan->nama
                ] : null,
                'created_at' => $pos->created_at,
                'updated_at' => $pos->updated_at,
            ];
        });

        // Bentuk bespoke (menyertakan `total`) dipertahankan apa adanya.
        return response()->json([
            'success' => true,
            'message' => 'Data retrieved successfully',
            'data' => $mappedData,
            'total' => $mappedData->count()
        ], 200);
    }



    /**
     * @OA\Get(
     *     path="/api/position/view/{id}",
     *     operationId="getPosition",
     *     tags={"Position"},
     *     summary="Get position details",
     *     description="Retrieve a specific position record by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Position ID",
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
     *                 @OA\Property(property="name", type="string", example="Software Developer"),
     *                 @OA\Property(property="description", type="string", example="Develop software applications"),
     *                 @OA\Property(property="company_id", type="integer", example=1),
     *                 @OA\Property(property="layanan_id", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="company", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Company Name")
     *                 ),
     *                 @OA\Property(property="kebutuhan", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="IT Services")
     *                 ),
     *                 @OA\Property(property="requirements", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="requirement", type="string", example="Minimal S1 Computer Science"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Position not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Position not found")
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
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function view($id)
    {
        // Validate ID parameter
        if (!is_numeric($id) || $id <= 0) {
            return $this->errorResponse('Invalid position ID', 400);
        }

        $data = Position::with(['company.creator', 'company.updater', 'kebutuhan', 'requirements', 'creator', 'updater'])
            ->find($id);

        if (!$data) {
            return $this->notFoundResponse('Position not found');
        }

        return $this->successResponse($data, 'Data retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/position/add",
     *     operationId="addPosition",
     *     tags={"Position"},
     *     summary="Create new position",
     *     description="Create a new position record",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"entitas", "layanan", "nama", "deskripsi"},
     *             @OA\Property(property="entitas", type="integer", example=3),
     *             @OA\Property(property="layanan", type="integer", example=2),
     *             @OA\Property(property="nama", type="string", example="Software Developer"),
     *             @OA\Property(property="deskripsi", type="string", example="Develop software applications")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Position created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Position created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Software Developer"),
     *                 @OA\Property(property="description", type="string", example="Develop software applications"),
     *                 @OA\Property(property="company_id", type="integer", example=1),
     *                 @OA\Property(property="layanan_id", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="updated_by", type="string", example="John Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
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
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function save(PositionSaveRequest $request)
    {
        $position = Position::create([
            'company_id' => $request->entitas,
            'name' => $request->nama,
            'description' => $request->deskripsi,
            'layanan_id' => $request->layanan,
            'is_active' => true,
            'created_by' => Auth::id() ?? 0,
            'updated_by' => Auth::id() ?? 0, // Gunakan user ID, bukan name
        ]);

        return $this->successResponse($position, 'Position created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/position/edit/{id}",
     *     operationId="updatePosition",
     *     tags={"Position"},
     *     summary="Update position",
     *     description="Update an existing position record",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Position ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"entitas", "layanan", "nama", "deskripsi"},
     *             @OA\Property(property="entitas", type="integer", example=3),
     *             @OA\Property(property="layanan", type="integer", example=2),
     *             @OA\Property(property="nama", type="string", example="Senior Software Developer"),
     *             @OA\Property(property="deskripsi", type="string", example="Develop and maintain software applications")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Position updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Position updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Senior Software Developer"),
     *                 @OA\Property(property="description", type="string", example="Develop and maintain software applications"),
     *                 @OA\Property(property="company_id", type="integer", example=1),
     *                 @OA\Property(property="layanan_id", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-02T00:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Position not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Position not found")
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
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function edit(PositionEditRequest $request, $id)
    {
        // Validate ID parameter
        if (!is_numeric($id) || $id <= 0) {
            return $this->errorResponse('Invalid position ID', 400);
        }

        $position = Position::find($id);

        if (!$position) {
            return $this->notFoundResponse('Position not found');
        }

        DB::transaction(function () use ($request, $id, $position) {
            $position->update([
                'company_id' => $request->entitas,
                'name' => $request->nama,
                'description' => $request->deskripsi,
                'layanan_id' => $request->layanan,
                'updated_by' => Auth::id() ?? 0,
            ]);

            // Update kebutuhan_id in requirements with proper error handling
            RequirementPosisi::where('position_id', $id)
                ->whereNull('deleted_at')
                ->update(['kebutuhan_id' => $request->layanan]);
        });

        return $this->successResponse($position->fresh(), 'Position updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/position/delete/{id}",
     *     operationId="deletePosition",
     *     tags={"Position"},
     *     summary="Delete position",
     *     description="Soft delete a position record by setting is_active to false",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Position ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Position deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Position deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Position not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Position not found")
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
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function delete(Request $request, $id)
    {
        // Validate ID parameter
        if (!is_numeric($id) || $id <= 0) {
            return $this->errorResponse('Invalid position ID', 400);
        }

        $position = Position::find($id);

        if (!$position) {
            return $this->notFoundResponse('Position not found');
        }

        DB::transaction(function () use ($id, $position) {
            $position->update([
                'is_active' => false,
                'updated_by' => Auth::id() ?? 0,
            ]);

            // Also soft delete related requirements
            RequirementPosisi::where('position_id', $id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        });

        return $this->messageResponse('Position deleted successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/position/requirement/list/{position_id}",
     *     operationId="getPositionRequirements",
     *     tags={"Position"},
     *     summary="Get position requirements",
     *     description="Retrieve all requirements for a specific position",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="position_id",
     *         in="path",
     *         description="Position ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="position_id", type="integer", example=1),
     *                     @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                     @OA\Property(property="requirement", type="string", example="Minimal S1 Computer Science"),
     *                     @OA\Property(property="created_by", type="string", example="John Doe"),
     *                     @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
     *                 )
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
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function requirementList(Request $request, $position_id)
    {
        // Validate position_id parameter
        if (!is_numeric($position_id) || $position_id <= 0) {
            return $this->errorResponse('Invalid position ID', 400);
        }

        // Check if position exists
        $position = Position::find($position_id);
        if (!$position) {
            return $this->notFoundResponse('Position not found');
        }

        $data = RequirementPosisi::where('position_id', $position_id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($data, 'Data retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/position/requirement/add",
     *     operationId="addPositionRequirement",
     *     tags={"Position"},
     *     summary="Add requirement to position",
     *     description="Add a new requirement to a position",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"position_id", "nama", "layanan_id"},
     *             @OA\Property(property="position_id", type="integer", example=1),
     *             @OA\Property(property="nama", type="string", example="Minimal S1 Computer Science"),
     *             @OA\Property(property="layanan_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Requirement added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Requirement added successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="position_id", type="integer", example=1),
     *                 @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                 @OA\Property(property="requirement", type="string", example="Minimal S1 Computer Science"),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
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
     *                 @OA\Property(property="position_id", type="array",
     *                     @OA\Items(type="string", example="The position_id field is required.")
     *                 )
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
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function addRequirement(PositionRequirementAddRequest $request)
    {
        $requirement = RequirementPosisi::create([
            'position_id' => $request->position_id,
            'requirement' => trim($request->nama),
            'kebutuhan_id' => $request->layanan_id,
            'created_by' => Auth::id() ?? 0,
            'updated_by' => Auth::id() ?? 0,
        ]);

        return $this->successResponse($requirement, 'Requirement added successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/position/requirement/edit",
     *     operationId="updatePositionRequirement",
     *     tags={"Position"},
     *     summary="Update requirement",
     *     description="Update an existing requirement",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"id", "requirement"},
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="requirement", type="string", example="Updated requirement description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Requirement updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Requirement updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="position_id", type="integer", example=1),
     *                 @OA\Property(property="kebutuhan_id", type="integer", example=1),
     *                 @OA\Property(property="requirement", type="string", example="Updated requirement description"),
     *                 @OA\Property(property="created_by", type="string", example="John Doe"),
     *                 @OA\Property(property="updated_by", type="string", example="Jane Doe"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-02T00:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Requirement not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Requirement not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="requirement", type="array",
     *                     @OA\Items(type="string", example="The requirement field is required.")
     *                 )
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
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function requirementEdit(PositionRequirementEditRequest $request)
    {
        $requirement = RequirementPosisi::whereNull('deleted_at')->find($request->id);

        if (!$requirement) {
            return $this->notFoundResponse('Requirement not found');
        }

        $requirement->update([
            'requirement' => trim($request->requirement),
            'updated_by' => Auth::user()->name ?? 'System',
        ]);

        return $this->successResponse($requirement->fresh(), 'Requirement updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/position/requirement/delete/{id}",
     *     operationId="deletePositionRequirement",
     *     tags={"Position"},
     *     summary="Delete requirement",
     *     description="Soft delete a requirement record by setting deleted_at timestamp",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Requirement ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Requirement deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Requirement deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Requirement not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Requirement not found")
     *          )
     *      ),
     *     @OA\Response(
     *           response=401,
     *           description="Unauthorized",
     *           @OA\JsonContent(
     *               @OA\Property(property="success", type="boolean", example=false),
     *               @OA\Property(property="message", type="string", example="Unauthorized")
     *          )
     *      ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Internal server error"),
     *              @OA\Property(property="error", type="string", example="Error details")
     *          )
     *      )
     *   )
     */
    public function requirementDelete(Request $request, $id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return $this->errorResponse('Invalid requirement ID', 400);
        }

        // Ambil data yang tidak terhapus, trait SoftDeletes akan otomatis menambahkan whereNull('deleted_at')
        $requirement = RequirementPosisi::find($id);

        if (!$requirement) {
            return $this->notFoundResponse('Requirement not found');
        }

        // Set kolom deleted_by sebelum melakukan soft delete
        $requirement->deleted_by = Auth::user()->full_name ?? 'System';
        $requirement->save(); // Simpan perubahan pada kolom deleted_by

        // Laravel akan otomatis mengisi deleted_at saat metode delete() dipanggil
        $requirement->delete();

        return $this->messageResponse('Requirement deleted successfully');
    }



}