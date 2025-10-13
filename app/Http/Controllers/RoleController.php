<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Sysmenu;
use App\Models\SysmenuRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
/**
 * @OA\Tag(
 *     name="Roles",
 *     description="API Endpoints for Role Management"
 * )
 */
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/roles/list",
     *     tags={"Roles"},
     *     summary="Get all active roles",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Admin"),
     *                     @OA\Property(property="Is_active", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $roles = Role::where('is_active', '!=', 0)->get();
            return $this->successResponse($roles);
        } catch (\Exception $e) {
            $this->logError('Fetch roles error', $e);
            return $this->errorResponse();
        }
    }

    /**
     * @OA\Get(
     *     path="/api/roles/view/{id}",
     *     tags={"Roles"},
     *     summary="Get role by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Admin"),
     *                 @OA\Property(property="Is_active", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return $this->notFoundResponse('Role not found');
            }

            return $this->successResponse($role);
        } catch (\Exception $e) {
            $this->logError('Fetch role error', $e, ['role_id' => $id]);
            return $this->errorResponse();
        }
    }

    /**
     * @OA\Get(
     *     path="/api/roles/permissions",
     *     tags={"Roles"},
     *     summary="Get menu permissions for the authenticated user's role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="Dashboard"),
     *                     @OA\Property(property="is_view", type="boolean", example=true),
     *                     @OA\Property(property="is_add", type="boolean", example=false),
     *                     @OA\Property(property="is_edit", type="boolean", example=true),
     *                     @OA\Property(property="is_delete", type="boolean", example=false)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function menuPermissions(): JsonResponse
    {
        try {
            // Ambil user yang sedang login
            $user = Auth::user();

            // Pastikan user memiliki role_id
            if (!$user || !$user->role_id) {
                return $this->forbiddenResponse('User role not found');
            }


            $permissions = Sysmenu::join('sysmenu_role', 'sysmenu_role.sysmenu_id', '=', 'sysmenu.id')
                ->where('sysmenu_role.role_id', $user->role_id)
                ->select(
                    'sysmenu.id',
                    'sysmenu.nama',
                    'sysmenu_role.is_view',
                    'sysmenu_role.is_add',
                    'sysmenu_role.is_edit',
                    'sysmenu_role.is_delete'
                )
                ->get();

            return $this->successResponse($permissions);
        } catch (\Exception $e) {
            $this->logError('Fetch menu permissions error', $e, ['user_id' => Auth::id()]);
            return $this->errorResponse();
        }
    }

    /**
     * @OA\Post(
     *     path="/api/roles/{id}/update-permissions",
     *     tags={"Roles"},
     *     summary="Update menu permissions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"akses"},
     *             @OA\Property(
     *                 property="akses",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="sysmenu_id", type="integer", example=1),
     *                     @OA\Property(property="field", type="string", example="is_view"),
     *                     @OA\Property(property="value", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Permissions updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update permissions")
     *         )
     *     )
     * )
     */
    public function updatePermissions(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $permissions = $request->input('akses', []);

            foreach ($permissions as $permission) {
                $this->updateOrCreatePermission($id, $permission);
            }

            DB::commit();
            return $this->successResponse(null, 'Permissions updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Update permissions error', $e, [
                'role_id' => $id,
                'permissions' => $request->input('akses')
            ]);
            return $this->errorResponse('Failed to update permissions');
        }
    }

    private function updateOrCreatePermission($roleId, $permission): void
    {
        $record = SysmenuRole::where('role_id', $roleId)
            ->where('sysmenu_id', $permission['sysmenu_id'])
            ->first();

        $data = [
            $permission['field'] => $permission['value'],
            'updated_by' => Auth::id()
        ];

        if ($record) {
            $record->update($data);
        } else {
            SysmenuRole::create(array_merge($data, [
                'role_id' => $roleId,
                'sysmenu_id' => $permission['sysmenu_id'],
                'created_by' => Auth::id()
            ]));
        }
    }

    private function successResponse($data = null, string $message = null): JsonResponse
    {
        $response = ['success' => true];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response);
    }

    private function errorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 500);
    }
    private function forbiddenResponse(string $message = 'Access denied'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }


    private function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 404);
    }

    private function logError(string $message, \Exception $e, array $context = []): void
    {
        Log::error("$message: {$e->getMessage()}", array_merge([
            'user_id' => Auth::id(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], $context));
    }
}