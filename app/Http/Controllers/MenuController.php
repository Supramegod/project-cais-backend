<?php

namespace App\Http\Controllers;


use App\Models\Role;
use App\Models\SysmenuRole;
use App\Http\Controllers\Controller;
use App\Models\Sysmenu;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/menu/list",
     *     summary="Get all menus",
     *     tags={"Menu"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", 
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="nama", type="string"),
     *                     @OA\Property(property="kode", type="string"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true),
     *                     @OA\Property(property="url", type="string"),
     *                     @OA\Property(property="icon", type="string", nullable=true),
     *                     @OA\Property(property="created_at", type="string"),
     *                     @OA\Property(property="created_by", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $menus = Sysmenu::active()->get();
            $formattedMenus = $this->formatMenu($menus);

            foreach ($formattedMenus as $value) {
                $value->created_at = Carbon::parse($value->created_at)->isoFormat('D MMMM Y');
            }

            return response()->json([
                'success' => true,
                'data' => $formattedMenus
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }
    /**
 * @OA\Get(
 *     path="/api/menu/all-permissions",
 *     summary="Get all menu role permissions",
 *     tags={"Menu"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Success",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="sysmenu_id", type="integer"),
 *                     @OA\Property(property="role_id", type="integer"),
 *                     @OA\Property(property="menu_name", type="string"),
 *                     @OA\Property(property="menu_code", type="string"),
 *                     @OA\Property(property="role_name", type="string"),
 *                     @OA\Property(property="is_view", type="boolean"),
 *                     @OA\Property(property="is_add", type="boolean"),
 *                     @OA\Property(property="is_edit", type="boolean"),
 *                     @OA\Property(property="is_delete", type="boolean"),
 *                     @OA\Property(property="created_at", type="string"),
 *                     @OA\Property(property="created_by", type="string")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Internal Server Error"
 *     )
 * )
 */
public function getAllPermissions(Request $request)
{
    try {
        $permissions = SysmenuRole::with([
                'menu:id,nama,kode',
                'role:id,name'
            ])
            ->select([
                'id',
                'sysmenu_id',
                'role_id',
                'is_view',
                'is_add',
                'is_edit',
                'is_delete',
                'created_at',
                'created_by'
            ])
            ->get()
            ->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'sysmenu_id' => $permission->sysmenu_id,
                    'role_id' => $permission->role_id,
                    'menu_name' => $permission->menu->nama ?? 'Menu not found',
                    'menu_code' => $permission->menu->kode ?? 'Code not found',
                    'role_name' => $permission->role->name ?? 'Role not found',
                    'is_view' => $permission->is_view,
                    'is_add' => $permission->is_add,
                    'is_edit' => $permission->is_edit,
                    'is_delete' => $permission->is_delete,
                    'created_at' => $permission->created_at,
                    'created_by' => $permission->created_by
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'total' => $permissions->count()
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),   // tampilkan pesan error asli
            'trace' => $e->getTraceAsString() // opsional, buat debug
        ], 500);
    }
}


    /**
     * @OA\Get(
     *     path="/api/menu/view/{id}",
     *     summary="Get menu by ID",
     *     tags={"Menu"},
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
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nama", type="string"),
     *                 @OA\Property(property="kode", type="string"),
     *                 @OA\Property(property="parent_id", type="integer", nullable=true),
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="icon", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function view(Request $request, $id)
    {
        try {
            $menu = Sysmenu::active()->find($id);

            if (!$menu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $menu
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/menu/add",
     *     summary="Create new menu",
     *     tags={"Menu"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama","kode","url"},
     *             @OA\Property(property="nama", type="string", example="Dashboard", maxLength=100),
     *             @OA\Property(property="kode", type="string", example="DASH", maxLength=50),
     *             @OA\Property(property="menu_parent", type="integer", example=null, nullable=true),
     *             @OA\Property(property="url", type="string", example="/dashboard", maxLength=255),
     *             @OA\Property(property="icon", type="string", example="mdi mdi-home", maxLength=100, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data Berhasil Disimpan"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nama", type="string"),
     *                 @OA\Property(property="kode", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     )
     * )
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'kode' => 'required|string|max:50|unique:sysmenu,kode',
            'menu_parent' => 'nullable|exists:sysmenu,id',
            'url' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $menu = Sysmenu::create([
                'nama' => $request->nama,
                'kode' => $request->kode,
                'parent_id' => $request->menu_parent,
                'url' => $request->url,
                'icon' => $request->icon,
                'created_at' => Carbon::now()->toDateTimeString(),
                'created_by' => Auth::user()->full_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data Berhasil Disimpan',
                'data' => $menu
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Data Gagal Disimpan'
            ], 500);
        }
    }


    // Kemudian replace method listRole yang ada dengan ini:
    /**
     * @OA\Get(
     *     path="/api/menu/listRole/{id}",
     *     summary="Get menu roles and permissions",
     *     tags={"Menu"},
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
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="is_view", type="boolean"),
     *                     @OA\Property(property="is_add", type="boolean"),
     *                     @OA\Property(property="is_edit", type="boolean"),
     *                     @OA\Property(property="is_delete", type="boolean")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listRole(Request $request, $id)
    {
        try {
            // Menggunakan connection mysqlhris sesuai dengan query asli
            $roles = Role::on('mysqlhris')
                ->select(['id', 'name'])
                ->where('Is_active', true) // Sesuai dengan field name di model Anda
                ->get()
                ->map(function ($role) use ($id) {
                    // Cari permission untuk role ini di menu tertentu
                    $permission = SysmenuRole::where('sysmenu_id', $id)
                        ->where('role_id', $role->id)
                        ->first();

                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'is_view' => $permission ? (bool) $permission->is_view : false,
                        'is_add' => $permission ? (bool) $permission->is_add : false,
                        'is_edit' => $permission ? (bool) $permission->is_edit : false,
                        'is_delete' => $permission ? (bool) $permission->is_delete : false,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $roles
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/menu/addrole/{id}",
     *     summary="Save menu roles and permissions",
     *     tags={"Menu"},
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
     *                     @OA\Property(property="role_id", type="integer", example=1),
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
     *             @OA\Property(property="message", type="string", example="Data Akses Berhasil Disimpan")
     *         )
     *     )
     * )
     */
    public function addrole(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $sysmenuId = $id;
            $groupedAkses = collect($request->akses)
                ->groupBy('role_id')
                ->map(function ($fields) {
                    return [
                        'is_view' => (bool) ($fields->firstWhere('field', 'is_view')['value'] ?? false),
                        'is_add' => (bool) ($fields->firstWhere('field', 'is_add')['value'] ?? false),
                        'is_edit' => (bool) ($fields->firstWhere('field', 'is_edit')['value'] ?? false),
                        'is_delete' => (bool) ($fields->firstWhere('field', 'is_delete')['value'] ?? false),
                    ];
                });

            foreach ($groupedAkses as $roleId => $permissions) {
                // Cari existing permission
                $existingPermission = SysmenuRole::where('sysmenu_id', $sysmenuId)
                    ->where('role_id', $roleId)
                    ->first();

                if ($existingPermission) {
                    // Update jika ada perubahan
                    $hasChanges = false;
                    foreach ($permissions as $key => $value) {
                        if ($existingPermission->{$key} !== $value) {
                            $hasChanges = true;
                            break;
                        }
                    }

                    if ($hasChanges) {
                        $existingPermission->update(array_merge($permissions, [
                            'updated_at' => Carbon::now(),
                            'updated_by' => Auth::user()->full_name,
                        ]));
                    }
                } else {
                    // Insert baru jika ada minimal satu permission yang true
                    if (collect($permissions)->contains(true)) {
                        SysmenuRole::create(array_merge([
                            'sysmenu_id' => $sysmenuId,
                            'role_id' => $roleId,
                            'created_at' => Carbon::now(),
                            'created_by' => Auth::user()->full_name,
                        ], $permissions));
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Akses Berhasil Disimpan'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Data Akses Gagal Disimpan'
            ], 500);
        }
    }

    // Juga tambahkan method getUserPermissions menggunakan Eloquent:
    /**
     * @OA\Get(
     *     path="/api/menu/permissions",
     *     summary="Get user menu permissions",
     *     tags={"Menu"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="permissions", type="object", 
     *                     @OA\Property(property="menu_code_1", type="object",
     *                         @OA\Property(property="is_view", type="boolean"),
     *                         @OA\Property(property="is_add", type="boolean"),
     *                         @OA\Property(property="is_edit", type="boolean"),
     *                         @OA\Property(property="is_delete", type="boolean")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getUserPermissions()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login first.'
                ], 401);
            }

            $roleId = $user->role_id ?? null;

            if (!$roleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role is not assigned to this user.'
                ], 400);
            }

            // Ambil permissions dari join Sysmenu & SysmenuRole
            $permissions = Sysmenu::join('sysmenu_role', 'sysmenu.id', '=', 'sysmenu_role.sysmenu_id')
                ->where('sysmenu_role.role_id', $roleId)
                ->active() // ambil menu yang belum dihapus
                ->select([
                    'sysmenu.kode',
                    'sysmenu_role.is_view',
                    'sysmenu_role.is_add',
                    'sysmenu_role.is_edit',
                    'sysmenu_role.is_delete'
                ])
                ->get()
                ->keyBy('kode')
                ->map(function ($item) {
                    return [
                        'is_view' => (bool) $item->is_view,
                        'is_add' => (bool) $item->is_add,
                        'is_edit' => (bool) $item->is_edit,
                        'is_delete' => (bool) $item->is_delete,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'permissions' => $permissions
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),  // tampilkan detail error biar gampang debug
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/menu/update/{id}",
     *     summary="Update menu",
     *     tags={"Menu"},
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
     *             required={"nama","kode","url"},
     *             @OA\Property(property="nama", type="string", example="Dashboard Updated", maxLength=100),
     *             @OA\Property(property="kode", type="string", example="DASH_UPD", maxLength=50),
     *             @OA\Property(property="url", type="string", example="/dashboard-new", maxLength=255),
     *             @OA\Property(property="icon", type="string", example="mdi mdi-home-outline", maxLength=100, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data Berhasil Disimpan")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $menu = Sysmenu::active()->find($id);

        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'kode' => 'required|string|max:50|unique:sysmenu,kode,' . $id,
            'url' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $menu->update([
                'nama' => $request->nama,
                'kode' => $request->kode,
                'url' => $request->url,
                'icon' => $request->icon,
                'updated_at' => Carbon::now()->toDateTimeString(),
                'updated_by' => Auth::user()->full_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data Berhasil Disimpan'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Data Gagal Disimpan'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/menu/delete/{id}",
     *     summary="Delete menu",
     *     tags={"Menu"},
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
     *             @OA\Property(property="message", type="string", example="Data Berhasil Dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function delete(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $menu = Sysmenu::active()->find($id);

            if (!$menu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu not found'
                ], 404);
            }

            $idDelete = [$id];
            $childIds = $this->getChildId($id);
            $idDelete = array_merge($idDelete, $childIds);

            Sysmenu::whereIn('id', $idDelete)->update([
                'deleted_at' => Carbon::now()->toDateTimeString(),
                'deleted_by' => Auth::user()->full_name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Berhasil Dihapus'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus menu'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/menu/by-role",
     *     summary="Get menus by user role",
     *     tags={"Menu"},
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
     *                     @OA\Property(property="kode", type="string", example="DASH"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="url", type="string", example="/dashboard"),
     *                     @OA\Property(property="icon", type="string", nullable=true, example="mdi mdi-home"),
     *                     @OA\Property(
     *                         property="children", 
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="nama", type="string", example="User Management"),
     *                             @OA\Property(property="kode", type="string", example="USER_MGMT"),
     *                             @OA\Property(property="parent_id", type="integer", example=1),
     *                             @OA\Property(property="url", type="string", example="/users"),
     *                             @OA\Property(property="icon", type="string", nullable=true, example="mdi mdi-account"),
     *                             @OA\Property(property="created_at", type="string", example="2024-01-01T00:00:00.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", example="2024-01-01T00:00:00.000000Z"),
     *                             @OA\Property(property="deleted_at", type="string", nullable=true, example=null),
     *                             @OA\Property(property="created_by", type="string", example="Admin"),
     *                             @OA\Property(property="updated_by", type="string", nullable=true, example=null),
     *                             @OA\Property(property="deleted_by", type="string", nullable=true, example=null)
     *                         )
     *                     ),
     *                     @OA\Property(property="created_at", type="string", example="2024-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2024-01-01T00:00:00.000000Z"),
     *                     @OA\Property(property="deleted_at", type="string", nullable=true, example=null),
     *                     @OA\Property(property="created_by", type="string", example="Admin"),
     *                     @OA\Property(property="updated_by", type="string", nullable=true, example=null),
     *                     @OA\Property(property="deleted_by", type="string", nullable=true, example=null)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Menus retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Role is not assigned to the user")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Internal Server Error")
     *         )
     *     )
     * )
     */
    public function getMenusByRole()
    {
        try {
            $user = Auth::user();
            $roleId = $user->role_id; // atau $user->role->id jika menggunakan relasi

            if (!$roleId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role is not assigned to the user'
                ], 400);
            }

            // Ambil menu parent (level 1) yang memiliki akses view untuk role ini
            $parentMenus = Sysmenu::with([
                'children' => function ($query) use ($roleId) {
                    // Load children yang juga memiliki akses view untuk role ini
                    $query->whereHas('roles', function ($roleQuery) use ($roleId) {
                        $roleQuery->where('role_id', $roleId)
                            ->where('is_view', 1);
                    })->orderBy('id');
                }
            ])
                ->whereNull('parent_id') // Hanya parent menu
                ->whereHas('roles', function ($query) use ($roleId) {
                    // Parent menu juga harus memiliki akses view
                    $query->where('role_id', $roleId)
                        ->where('is_view', 1);
                })
                ->active() // Menggunakan scope active yang sudah ada
                ->orderBy('id') // atau sesuai kebutuhan order
                ->get();

            // Filter parent menu yang memiliki children atau tidak memiliki children sama sekali
            $filteredMenus = $parentMenus->filter(function ($menu) {
                // Tampilkan menu jika:
                // 1. Memiliki children yang bisa diakses, ATAU
                // 2. Tidak memiliki children sama sekali (menu standalone)
                return $menu->children->isNotEmpty() ||
                    $menu->children()->active()->count() === 0;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $filteredMenus,
                'message' => 'Menus retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error'
            ], 500);
        }
    }

    // Helper methods
    private function getChildId($parentId)
    {
        $childs = Sysmenu::where('parent_id', $parentId)
            ->active()
            ->pluck('id');
        $all = [];

        foreach ($childs as $childId) {
            $all[] = $childId;
            $all = array_merge($all, $this->getChildId($childId));
        }

        return $all;
    }

    private function getChildNames($data, $parentId)
    {
        $childNames = [];

        foreach ($data as $menu) {
            if ($menu->parent_id == $parentId) {
                $childNames[] = $menu->nama;
                $childNames = array_merge($childNames, $this->getChildNames($data, $menu->id));
            }
        }

        return $childNames;
    }

    private function formatMenu($data, $parentId = null, $prefix = '')
    {
        $result = [];

        foreach ($data as $menu) {
            if ($menu->parent_id == $parentId) {
                $menu->nama = $prefix . $menu->nama;
                $result[] = $menu;

                $children = $this->formatMenu($data, $menu->id, $prefix . '- ');
                $result = array_merge($result, $children);
            }
        }

        return $result;
    }
}