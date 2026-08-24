<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\RoleShowRequest;
use App\Http\Requests\Role\RoleUpdatePermissionsRequest;
use App\Models\Role;
use App\Models\Sysmenu;
use App\Models\SysmenuRole;
use App\Services\MenuPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="API Endpoints for Role Management"
 * )
 */
class RoleController extends Controller
{
    public function __construct(private MenuPermissionService $menuPermissions) {}

    /**
     * @OA\Get(
     *     path="/api/roles/list",
     *     tags={"Roles"},
     *     summary="Get all active roles",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(
     *
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
        return $this->successResponse(Role::active()->get());
    }

    /**
     * @OA\Get(
     *     path="/api/roles/view/{id}",
     *     tags={"Roles"},
     *     summary="Get role by ID with all menu permissions",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         required=false,
     *         description="Kalau diisi, tiap menu ikut membawa objek `override` berisi nilai khusus user tersebut. User wajib anggota role ini, kalau bukan akan 422.",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Admin"),
     *                 @OA\Property(property="Is_active", type="integer", example=1),
     *                 @OA\Property(property="menus", type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Dashboard"),
     *                         @OA\Property(property="url", type="string", example="/dashboard"),
     *                         @OA\Property(property="parent_id", type="integer", example=null),
     *                         @OA\Property(property="is_view", type="boolean", example=true),
     *                         @OA\Property(property="is_add", type="boolean", example=true),
     *                         @OA\Property(property="is_edit", type="boolean", example=true),
     *                         @OA\Property(property="is_delete", type="boolean", example=false),
     *                         @OA\Property(property="children", type="array",
     *
     *                             @OA\Items(
     *
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
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Role not found")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="user_id tidak dikenal atau bukan anggota role ini")
     * )
     */
    public function show(RoleShowRequest $request, $id): JsonResponse
    {
        // Ambil role dari connection mysqlhris
        $role = Role::find($id);

        if (! $role) {
            return $this->notFoundResponse('Role not found');
        }

        $userId = $request->integer('user_id') ?: null;

        // Ambil semua menu dengan permissions untuk role ini
        $menus = Sysmenu::active()
            ->withPermissions($id, $userId)
            ->withGroupInfo()
            ->selectMenuFields($userId !== null)
            ->ordered()
            ->get();

        // Bangun hierarchical menu structure
        $menuTree = $this->buildMenuTreeWithPermissions($menus, null, $userId !== null);

        // Format response
        $roleData = [
            'id' => $role->id,
            'name' => $role->name,
            'Is_active' => $role->Is_active,
            'menus' => $menuTree,
        ];

        return $this->successResponse($roleData);
    }

    /**
     * @OA\Get(
     *     path="/api/roles/permissions",
     *     tags={"Roles"},
     *     summary="Get menu permissions grouped by menu group for authenticated user",
     *     description="Returns hierarchical menu structure with permissions grouped by menu groups",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="group_id", type="integer", example=1),
     *                     @OA\Property(property="group_name", type="string", example="Main Menu"),
     *                     @OA\Property(property="menus", type="array",
     *
     *                         @OA\Items(
     *
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
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User role not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function menuPermissions(): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->cais_role_id) {
            return $this->errorResponse('User role not found', 403);
        }

        // Ambil semua menu aktif dengan LEFT JOIN ke permissions
        $menus = Sysmenu::active()
            ->withPermissions($user->cais_role_id, (int) $user->id)
            ->withGroupInfo()
            ->selectMenuFields(true)
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
                'grouped' => [],
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

                if (! isset($groupedMenus[$groupId])) {
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
    }

    /**
     * @OA\Post(
     *     path="/api/roles/{id}/update-permissions",
     *     tags={"Roles"},
     *     summary="Update menu permissions (parent updates will cascade to children)",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"akses"},
     *
     *             @OA\Property(
     *                 property="akses",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="sysmenu_id", type="integer", example=1),
     *                     @OA\Property(property="field", type="string", example="is_view"),
     *                     @OA\Property(property="value", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Permissions updated successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update permissions")
     *         )
     *     )
     * )
     */
    public function updatePermissions(RoleUpdatePermissionsRequest $request, $id): JsonResponse
    {
        $permissions = $request->input('akses', []);
        $userId = $request->input('user_id') !== null ? (int) $request->input('user_id') : null;

        DB::transaction(function () use ($permissions, $id, $userId) {
            foreach ($permissions as $permission) {
                $this->updateOrCreatePermission($id, $permission, $userId);

                // Jika yang diupdate adalah menu parent, update juga semua child menus
                $this->cascadePermissionToChildren($id, $permission, $userId);
            }
        });

        // Cache permission dibaca setiap request oleh CheckMenuPermission, jadi
        // harus dibuang begitu datanya berubah.
        if ($userId === null) {
            $this->menuPermissions->forget((int) $id);
        } else {
            $this->menuPermissions->forgetUser((int) $id, $userId);
        }

        return $this->successResponse(null, 'Permissions updated successfully');
    }

    /**
     * Update permission untuk menu parent dan cascade ke semua child menus
     * untuk semua field: is_view, is_add, is_edit, is_delete
     */
    private function cascadePermissionToChildren($roleId, $parentPermission, ?int $userId = null): void
    {
        $parentMenuId = $parentPermission['sysmenu_id'];
        $field = $parentPermission['field'];
        $value = $parentPermission['value'];

        $childMenuIds = $this->getAllChildMenuIds($parentMenuId);

        if (! empty($childMenuIds)) {
            // Batch update existing records
            SysmenuRole::where('role_id', $roleId)
                ->whereIn('sysmenu_id', $childMenuIds)
                ->when(
                    $userId === null,
                    fn ($query) => $query->roleLevel(),
                    fn ($query) => $query->forUser($userId)
                )
                ->update([
                    $field => $value,
                    'updated_by' => Auth::id(),
                ]);

            // Find which child menus don't have permission records yet
            $existingRecords = SysmenuRole::where('role_id', $roleId)
                ->whereIn('sysmenu_id', $childMenuIds)
                ->when(
                    $userId === null,
                    fn ($query) => $query->roleLevel(),
                    fn ($query) => $query->forUser($userId)
                )
                ->pluck('sysmenu_id')
                ->toArray();

            $newMenuIds = array_diff($childMenuIds, $existingRecords);

            // Create new records for menus without permissions
            $batchData = [];
            $currentTime = now();

            foreach ($newMenuIds as $menuId) {
                $batchData[] = [
                    'role_id' => $roleId,
                    'user_id' => $userId,
                    'sysmenu_id' => $menuId,
                    $field => $value,
                    // Set default values untuk field lainnya
                    'is_view' => $field === 'is_view' ? $value : false,
                    'is_add' => $field === 'is_add' ? $value : false,
                    'is_edit' => $field === 'is_edit' ? $value : false,
                    'is_delete' => $field === 'is_delete' ? $value : false,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime,
                ];
            }

            if (! empty($batchData)) {
                SysmenuRole::insert($batchData);
            }
        }
    }

    /**
     * Dapatkan semua child menu IDs secara recursive
     */
    private function getAllChildMenuIds($parentId): array
    {
        $childIds = [];

        // Dapatkan direct children
        $directChildren = Sysmenu::where('parent_id', $parentId)->get();

        foreach ($directChildren as $child) {
            $childIds[] = $child->id;

            // Dapatkan grandchildren recursively
            $grandChildren = $this->getAllChildMenuIds($child->id);
            $childIds = array_merge($childIds, $grandChildren);
        }

        return $childIds;
    }

    private function updateOrCreatePermission($roleId, $permission, ?int $userId = null): void
    {
        $record = SysmenuRole::where('role_id', $roleId)
            ->where('sysmenu_id', $permission['sysmenu_id'])
            ->when(
                $userId === null,
                fn ($query) => $query->roleLevel(),
                fn ($query) => $query->forUser($userId)
            )
            ->first();

        $data = [
            $permission['field'] => $permission['value'],
            'updated_by' => Auth::id(),
        ];

        if ($record) {
            $record->update($data);
        } else {
            SysmenuRole::create(array_merge($data, [
                'role_id' => $roleId,
                'user_id' => $userId,
                'sysmenu_id' => $permission['sysmenu_id'],
                'created_by' => Auth::id(),
                // Set default values untuk field lainnya
                'is_view' => $permission['field'] === 'is_view' ? $permission['value'] : false,
                'is_add' => $permission['field'] === 'is_add' ? $permission['value'] : false,
                'is_edit' => $permission['field'] === 'is_edit' ? $permission['value'] : false,
                'is_delete' => $permission['field'] === 'is_delete' ? $permission['value'] : false,
            ]));
        }
    }

    // ============================ HELPER METHODS ============================
    /**
     * Build hierarchical menu tree with permissions untuk method show()
     */
    private function buildMenuTreeWithPermissions($menus, $parentId = null, bool $withOverride = false)
    {
        $tree = [];

        foreach ($menus as $menu) {
            if ($menu->parent_id == $parentId) {
                $children = $this->buildMenuTreeWithPermissions($menus, $menu->id, $withOverride);

                $node = [
                    'id' => $menu->id,
                    'nama' => $menu->nama,
                    'url' => $menu->url,
                    'status' => $menu->status,
                    'parent_id' => $menu->parent_id,
                    'is_view' => (bool) $menu->is_view,
                    'is_add' => (bool) $menu->is_add,
                    'is_edit' => (bool) $menu->is_edit,
                    'is_delete' => (bool) $menu->is_delete,
                    'children' => $children,
                ];

                if ($withOverride) {
                    $override = [];

                    foreach (MenuPermissionService::FIELDS as $field) {
                        $override[$field] = (bool) $menu->{'override_'.$field};
                    }

                    $node['override'] = $override;
                }

                $tree[] = $node;
            }
        }

        return $tree;
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
                    'status' => $menu->status,
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
}
