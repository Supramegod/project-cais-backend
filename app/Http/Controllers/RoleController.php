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
            $roles = Role::active()->get();
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
     *     summary="Get role by ID with all menu permissions",
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
     *                 @OA\Property(property="Is_active", type="integer", example=1),
     *                 @OA\Property(property="menus", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Dashboard"),
     *                         @OA\Property(property="url", type="string", example="/dashboard"),
     *                         @OA\Property(property="parent_id", type="integer", example=null),
     *                         @OA\Property(property="is_view", type="boolean", example=true),
     *                         @OA\Property(property="is_add", type="boolean", example=true),
     *                         @OA\Property(property="is_edit", type="boolean", example=true),
     *                         @OA\Property(property="is_delete", type="boolean", example=false),
     *                         @OA\Property(property="children", type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="id", type="integer", example=2),
     *                                 @OA\Property(property="name", type="string", example="Sub Menu"),
     *                                 @OA\Property(property="is_view", type="boolean", example=false)
     *                             )
     *                         )
     *                     )
     *                 )
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
            // Ambil role dari connection mysqlhris
            $role = Role::find($id);

            if (!$role) {
                return $this->notFoundResponse('Role not found');
            }

            // Ambil semua menu
            $allMenus = Sysmenu::orderBy('id')->get();

            // Ambil permission role untuk menu ini
            $rolePermissions = SysmenuRole::where('role_id', $id)->get()->keyBy('sysmenu_id');

            // Build hierarchical menu structured
            $menuTree = $this->buildMenuTree($allMenus, $rolePermissions);

            // Format response
            $roleData = [
                'id' => $role->id,
                'name' => $role->name,
                'Is_active' => $role->Is_active,
                'menus' => $menuTree
            ];

            return $this->successResponse($roleData);
        } catch (\Exception $e) {
            $this->logError('Fetch role error', $e, ['role_id' => $id]);
            return $this->errorResponse();
        }
    }


    /**
     * @OA\Get(
     *     path="/api/roles/permissions",
     *     tags={"Roles"},
     *     summary="Get menu permissions grouped by menu group for authenticated user",
     *     description="Returns hierarchical menu structure with permissions grouped by menu groups",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="group_id", type="integer", example=1),
     *                     @OA\Property(property="group_name", type="string", example="Main Menu"),
     *                     @OA\Property(property="menus", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="nama", type="string", example="Dashboard"),
     *                             @OA\Property(property="icon", type="string", example="fa-home"),
     *                             @OA\Property(property="url", type="string", example="/dashboard"),
     *                             @OA\Property(property="permissions", type="object",
     *                                 @OA\Property(property="view", type="boolean", example=true),
     *                                 @OA\Property(property="add", type="boolean", example=false),
     *                                 @OA\Property(property="edit", type="boolean", example=true),
     *                                 @OA\Property(property="delete", type="boolean", example=false)
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User role not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function menuPermissions(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->role_id) {
                return $this->forbiddenResponse('User role not found');
            }

            // Ambil semua menu aktif dengan LEFT JOIN ke permissions
            $menus = Sysmenu::active()
                ->withPermissions($user->role_id) // Sekarang menggunakan LEFT JOIN
                ->withGroupInfo()
                ->selectMenuFields() // Sudah handle COALESCE untuk null permissions
                ->ordered()
                ->get();

            // Filter: hanya ambil menu yang memiliki is_view = 1 atau belum ada permission record
            $filteredMenus = $menus->filter(function ($menu) {
                return $menu->is_view == 1 || is_null($menu->is_view);
            });

            // Jika tidak ada menu yang memenuhi kriteria, return empty response
            if ($filteredMenus->isEmpty()) {
                return $this->successResponse([
                    'ungrouped' => [],
                    'grouped' => []
                ]);
            }

            // Bangun tree + pewarisan group
            $menuTree = $this->buildMenuTreeWithGroup($filteredMenus);

            $groupedMenus = [];
            $ungroupedMenus = [];

            foreach ($menuTree as $menu) {
                if (empty($menu['group_id'])) {
                    $ungroupedMenus[] = $menu;
                } else {
                    $groupId = $menu['group_id'];
                    $groupName = $menu['group_name'] ?? 'Tanpa Nama';

                    if (!isset($groupedMenus[$groupId])) {
                        $groupedMenus[$groupId] = [
                            'group_id' => $groupId,
                            'group_name' => $groupName,
                            'menus' => [],
                        ];
                    }

                    $groupedMenus[$groupId]['menus'][] = $menu;
                }
            }

            $response = [
                'ungrouped' => $ungroupedMenus,
                'grouped' => array_values($groupedMenus),
            ];

            return $this->successResponse($response);

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
    // ============================ HELPER METHODS ============================
    /**
     * Build hierarchical menu tree with permissions
     */
    private function buildMenuTree($menus, $rolePermissions, $parentId = null)
    {
        $branch = [];

        foreach ($menus as $menu) {
            if ($menu->parent_id == $parentId) {
                // Get permission for this menu
                $permission = $rolePermissions->get($menu->id);

                $item = [
                    'id' => $menu->id,
                    'name' => $menu->nama,
                    'parent_id' => $menu->parent_id,
                    'is_view' => $permission ? $permission->is_view : false,
                    'is_add' => $permission ? $permission->is_add : false,
                    'is_edit' => $permission ? $permission->is_edit : false,
                    'is_delete' => $permission ? $permission->is_delete : false,
                ];

                // Recursively get children
                $children = $this->buildMenuTree($menus, $rolePermissions, $menu->id);
                if (!empty($children)) {
                    $item['children'] = $children;
                }

                $branch[] = $item;
            }
        }

        return $branch;
    }

    private function buildMenuTreeWithGroup($menus, $parentId = null, $parentGroupId = null, $parentGroupName = null)
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ($menu->parent_id == $parentId) {
                // Gunakan group dari menu ini, atau warisi dari parent
                $currentGroupId = $menu->group_id ?? $parentGroupId;
                $currentGroupName = $menu->group_name ?? $parentGroupName;

                $children = $this->buildMenuTreeWithGroup($menus, $menu->id, $currentGroupId, $currentGroupName);

                $tree[] = [
                    'id' => $menu->id,
                    'nama' => $menu->nama,
                    'icon' => $menu->icon,
                    'url' => $menu->url,
                    'parent_id' => $menu->parent_id,
                    'group_id' => $currentGroupId,
                    'group_name' => $currentGroupName,
                    'permissions' => [
                        'view' => (bool) $menu->is_view,
                        'add' => (bool) $menu->is_add,
                        'edit' => (bool) $menu->is_edit,
                        'delete' => (bool) $menu->is_delete,
                    ],
                    'children' => $children,
                ];
            }
        }

        return $tree;
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