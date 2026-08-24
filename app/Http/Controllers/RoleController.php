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
    /** @var array<int, list<int>>|null */
    private ?array $childrenByParent = null;

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
     *         description="Kalau diisi, permission tiap menu dihitung untuk user tersebut: baris user meng-override baris role per menu, dan menu tanpa baris user tetap mengikuti role. Response ikut membawa `has_override` dan objek `override`. User wajib anggota role ini, kalau bukan akan 422.",
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
     *                         @OA\Property(property="has_override", type="boolean", example=true, description="Hanya saat `user_id` diisi. True kalau user punya baris khusus untuk menu ini, artinya nilai efektif di atas berasal dari baris user, bukan dari role."),
     *                         @OA\Property(property="override", type="object", description="Hanya saat `user_id` diisi. Nilai mentah baris user; abaikan kalau `has_override` false.",
     *                             @OA\Property(property="is_view", type="boolean", example=true),
     *                             @OA\Property(property="is_add", type="boolean", example=false),
     *                             @OA\Property(property="is_edit", type="boolean", example=false),
     *                             @OA\Property(property="is_delete", type="boolean", example=false)
     *                         ),
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

        // Fail-closed: menu tanpa permission record sudah bernilai 0 dari query.
        $filteredMenus = $menus->filter(function ($menu) {
            return $menu->is_view == 1;
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

        $roleId = (int) $id;
        $resyncedUserIds = [];

        DB::transaction(function () use ($permissions, $roleId, $userId, &$resyncedUserIds) {
            foreach ($permissions as $permission) {
                $menuId = (int) $permission['sysmenu_id'];
                $childMenuIds = $this->getAllChildMenuIds($menuId);

                $this->updateOrCreatePermission($roleId, $permission, $userId);

                // Jika yang diupdate adalah menu parent, update juga semua child menus
                $this->cascadePermissionToChildren($roleId, $permission, $childMenuIds, $userId);

                if ($userId === null) {
                    $resyncedUserIds = array_merge(
                        $resyncedUserIds,
                        $this->resyncUserOverrides($roleId, $permission, array_merge([$menuId], $childMenuIds))
                    );
                }
            }
        });

        // Cache permission dibaca setiap request oleh CheckMenuPermission, jadi
        // harus dibuang begitu datanya berubah.
        if ($userId === null) {
            $this->menuPermissions->forget($roleId);

            foreach (array_unique($resyncedUserIds) as $resyncedUserId) {
                $this->menuPermissions->forgetUser($roleId, $resyncedUserId);
            }
        } else {
            $this->menuPermissions->forgetUser($roleId, $userId);
        }

        return $this->successResponse(null, 'Permissions updated successfully');
    }

    /**
     * Update permission untuk menu parent dan cascade ke semua child menus
     * untuk semua field: is_view, is_add, is_edit, is_delete
     *
     * @param  array{sysmenu_id: int|string, field: string, value: bool}  $parentPermission
     * @param  list<int>  $childMenuIds
     */
    private function cascadePermissionToChildren(int $roleId, array $parentPermission, array $childMenuIds, ?int $userId = null): void
    {
        $field = $parentPermission['field'];
        $value = $parentPermission['value'];

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
            $baseline = $this->baselineFlags($roleId, array_values($newMenuIds), $userId);

            foreach ($newMenuIds as $menuId) {
                $flags = $baseline[$menuId];
                $flags[$field] = $value;

                $batchData[] = array_merge($flags, [
                    'role_id' => $roleId,
                    'user_id' => $userId,
                    'sysmenu_id' => $menuId,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => $currentTime,
                    'updated_at' => $currentTime,
                ]);
            }

            if (! empty($batchData)) {
                SysmenuRole::insert($batchData);
            }
        }
    }

    /**
     * Role adalah default: begitu permission role-level berubah, baris override
     * user untuk menu yang sama ikut disamakan supaya tidak ada user yang
     * tertinggal memakai nilai lama.
     *
     * @param  array{sysmenu_id: int|string, field: string, value: bool}  $permission
     * @param  list<int>  $menuIds
     * @return list<int> user_id yang barisnya ikut berubah
     */
    private function resyncUserOverrides(int $roleId, array $permission, array $menuIds): array
    {
        $query = SysmenuRole::where('role_id', $roleId)
            ->whereIn('sysmenu_id', $menuIds)
            ->whereNotNull('user_id');

        $userIds = (clone $query)->distinct()->pluck('user_id')->all();

        if (empty($userIds)) {
            return [];
        }

        $query->update([
            $permission['field'] => $permission['value'],
            'updated_by' => Auth::id(),
        ]);

        return array_map('intval', $userIds);
    }

    /**
     * Semua descendant menu, ditelusuri dari peta parent yang dibaca sekali per
     * request. Aman terhadap parent_id yang membentuk siklus.
     *
     * @return list<int>
     */
    private function getAllChildMenuIds(int $parentId): array
    {
        $childrenByParent = $this->childrenByParent();
        $descendants = [];
        $seen = [$parentId => true];
        $queue = [$parentId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                if (isset($seen[$childId])) {
                    continue;
                }

                $seen[$childId] = true;
                $descendants[] = $childId;
                $queue[] = $childId;
            }
        }

        return $descendants;
    }

    /**
     * @return array<int, list<int>>
     */
    private function childrenByParent(): array
    {
        if ($this->childrenByParent !== null) {
            return $this->childrenByParent;
        }

        $map = [];

        foreach (Sysmenu::query()->whereNotNull('parent_id')->pluck('parent_id', 'id') as $id => $parentId) {
            $map[(int) $parentId][] = (int) $id;
        }

        return $this->childrenByParent = $map;
    }

    /**
     * @param  array{sysmenu_id: int|string, field: string, value: bool}  $permission
     */
    private function updateOrCreatePermission(int $roleId, array $permission, ?int $userId = null): void
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

            return;
        }

        $flags = $this->baselineFlags($roleId, [$permission['sysmenu_id']], $userId)[$permission['sysmenu_id']];
        $flags[$permission['field']] = $permission['value'];

        SysmenuRole::create(array_merge($data, $flags, [
            'role_id' => $roleId,
            'user_id' => $userId,
            'sysmenu_id' => $permission['sysmenu_id'],
            'created_by' => Auth::id(),
        ]));
    }

    /**
     * Nilai awal untuk baris yang baru dibuat.
     *
     * Baris user-level meng-override role-level per menu, jadi baris user baru
     * harus mewarisi nilai role saat ini. Kalau tidak, mengubah satu field
     * (misal is_add) diam-diam mencabut field lain yang sebelumnya diberikan role.
     *
     * @param  list<int>  $menuIds
     * @return array<int, array<string, bool>>
     */
    private function baselineFlags(int $roleId, array $menuIds, ?int $userId): array
    {
        $empty = array_fill_keys(MenuPermissionService::FIELDS, false);
        $baseline = array_fill_keys($menuIds, $empty);

        if ($userId === null) {
            return $baseline;
        }

        $roleLevel = SysmenuRole::where('role_id', $roleId)
            ->whereIn('sysmenu_id', $menuIds)
            ->roleLevel()
            ->get(array_merge(['sysmenu_id'], MenuPermissionService::FIELDS));

        foreach ($roleLevel as $row) {
            $flags = [];

            foreach (MenuPermissionService::FIELDS as $field) {
                $flags[$field] = (bool) $row->{$field};
            }

            $baseline[$row->sysmenu_id] = $flags;
        }

        return $baseline;
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

                    $node['has_override'] = (bool) $menu->has_override;
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
