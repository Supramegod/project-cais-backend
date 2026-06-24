<?php

namespace App\Http\Controllers;


use App\Models\Role;
use App\Models\SysmenuRole;
use App\Models\SysmenuGroup;
use App\Http\Controllers\Controller;
use App\Models\Sysmenu;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Menu",
 *     description="API untuk manajemen Menu"
 * )
 */
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
            // Ambil semua menu aktif, urutkan berdasarkan ID biar stabil
            $menus = Sysmenu::active()
                ->orderBy('parent_id')
                ->orderBy('id', 'asc')
                ->get();

            // Kelompokkan berdasarkan parent_id
            $grouped = $menus->groupBy('parent_id');

            // Fungsi rekursif buat struktur tree (parent + child)
            $buildTree = function ($parentId) use (&$buildTree, $grouped) {
                return ($grouped[$parentId] ?? collect())->map(function ($menu) use (&$buildTree) {
                    return [
                        'id'         => $menu->id,
                        'nama'       => $menu->nama,
                        'url'        => $menu->url,
                        'icon'       => $menu->icon,
                        'created_at' => $menu->created_at
                            ? Carbon::parse($menu->created_at)->isoFormat('D MMMM Y')
                            : null,
                        'children'   => $buildTree($menu->id),
                    ];
                })->values();
            };

            // Menu utama = parent_id NULL
            $formattedMenus = $buildTree(null);

            return response()->json([
                'success' => true,
                'data'    => $formattedMenus
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error'   => $e->getMessage()
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
                'data'    => $menu
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
     *             required={"nama","url"},
     *             @OA\Property(property="nama", type="string", example="Dashboard", maxLength=100),
     *             @OA\Property(property="parent_id", type="integer", example=null, nullable=true),
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
     *                 @OA\Property(property="nama", type="string")
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
            'nama'      => 'required|string|max:100',
            'parent_id' => 'nullable|exists:sysmenu,id',
            'url'       => 'required|string|max:255',
            'icon'      => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $menu = Sysmenu::create([
                'nama'       => $request->nama,
                'parent_id'  => $request->parent_id,
                'url'        => $request->url,
                'icon'       => $request->icon,
                'created_at' => Carbon::now()->toDateTimeString(),
                'created_by' => Auth::user()->full_name,
                'created_by_user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Data Berhasil Disimpan',
                'data'    => $menu
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Gagal Disimpan'
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
     *             required={"nama","url"},
     *             @OA\Property(property="nama", type="string", example="Dashboard Updated", maxLength=100),
     *             @OA\Property(property="url", type="string", example="/dashboard-new", maxLength=255),
     *             @OA\Property(property="icon", type="string", example="mdi mdi-home-outline", maxLength=100, nullable=true),
     *             @OA\Property(property="status", type="string", enum={"alpha", "beta"}, example="alpha", nullable=true)
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
            'nama'   => 'required|string|max:100',
            'url'    => 'required|string|max:255',
            'icon'   => 'nullable|string|max:100',
            'status' => 'nullable|in:alpha,beta',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $menu->update([
                'nama'       => $request->nama,
                'url'        => $request->url,
                'icon'       => $request->icon,
                'status'     => $request->filled('status') ? $request->status : null,
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

            $idDelete  = [$id];
            $childIds  = $this->getChildId($id);
            $idDelete  = array_merge($idDelete, $childIds);

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

    // =========================================================================
    // GROUP ENDPOINTS
    // =========================================================================

    /**
     * @OA\Get(
     *     path="/api/menu/group/list",
     *     summary="Get all menu groups beserta daftar menu di dalamnya",
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
     *                     @OA\Property(property="total_menu", type="integer"),
     *                     @OA\Property(property="menus", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="nama", type="string"),
     *                             @OA\Property(property="url", type="string"),
     *                             @OA\Property(property="icon", type="string", nullable=true)
     *                         )
     *                     )
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
    public function listGroup(Request $request)
    {
        try {
            $groups = SysmenuGroup::with(['sysmenus' => function ($query) {
                // Hanya ambil menu aktif (belum di-soft-delete)
                $query->whereNull('deleted_at')
                    ->select('id', 'group_id', 'nama', 'url', 'icon')
                    ->orderBy('id');
            }])
            ->orderBy('nama')
            ->get();

            $data = $groups->map(function ($group) {
                return [
                    'id'          => $group->id,
                    'nama'        => $group->nama,
                    'total_menu'  => $group->sysmenus->count(),
                    'menus'       => $group->sysmenus->map(function ($menu) {
                        return [
                            'id'   => $menu->id,
                            'nama' => $menu->nama,
                            'url'  => $menu->url,
                            'icon' => $menu->icon,
                        ];
                    })->values(),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/menu/group/add",
     *     summary="Tambah group menu baru",
     *     tags={"Menu"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama"},
     *             @OA\Property(property="nama", type="string", example="Master Data", maxLength=100)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grup Berhasil Dibuat"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Master Data")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function addGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100|unique:sysmenu_group,nama',
        ], [
            'nama.unique' => 'Nama grup sudah digunakan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $group = SysmenuGroup::create([
                'nama' => $request->nama,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grup Berhasil Dibuat',
                'data'    => [
                    'id'   => $group->id,
                    'nama' => $group->nama,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal Membuat Grup'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/menu/group/assign",
     *     summary="Assign satu atau beberapa menu ke dalam sebuah group",
     *     description="Kirim menu_ids berisi satu ID untuk single, atau lebih dari satu ID untuk multiple assign. Menu yang sudah ada di group lain akan dipindahkan ke group baru.",
     *     tags={"Menu"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"group_id","menu_ids"},
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="menu_ids",
     *                 type="array",
     *                 minItems=1,
     *                 description="Array ID menu, isi satu elemen untuk single assign",
     *                 @OA\Items(type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="3 menu berhasil ditambahkan ke grup"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="group_nama", type="string", example="Master Data"),
     *                 @OA\Property(property="assigned_count", type="integer", example=3),
     *                 @OA\Property(property="not_found_ids", type="array",
     *                     description="ID menu yang tidak ditemukan / sudah terhapus",
     *                     @OA\Items(type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group tidak ditemukan"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function assignMenuToGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id'    => 'required|integer|exists:sysmenu_group,id',
            'menu_ids'    => 'required|array|min:1',
            'menu_ids.*'  => 'required|integer|exists:sysmenu,id',
        ], [
            'group_id.exists'   => 'Grup tidak ditemukan.',
            'menu_ids.required' => 'Minimal satu ID menu harus diisi.',
            'menu_ids.*.exists' => 'Salah satu ID menu tidak ditemukan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $group = SysmenuGroup::find($request->group_id);

            // Cari menu aktif yang sesuai dengan ID yang dikirim
            $activeMenuIds = Sysmenu::active()
                ->whereIn('id', $request->menu_ids)
                ->pluck('id')
                ->toArray();

            // Deteksi ID yang tidak aktif / tidak ditemukan
            $notFoundIds = array_values(
                array_diff($request->menu_ids, $activeMenuIds)
            );

            $assignedCount = 0;

            if (!empty($activeMenuIds)) {
                $assignedCount = Sysmenu::whereIn('id', $activeMenuIds)
                    ->update([
                        'group_id'   => $group->id,
                        'updated_at' => Carbon::now()->toDateTimeString(),
                        'updated_by' => Auth::user()->full_name,
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$assignedCount} menu berhasil ditambahkan ke grup \"{$group->nama}\"",
                'data'    => [
                    'group_id'       => $group->id,
                    'group_nama'     => $group->nama,
                    'assigned_count' => $assignedCount,
                    'not_found_ids'  => $notFoundIds,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan menu ke grup'
            ], 500);
        }
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function getChildId($parentId)
    {
        $childs = Sysmenu::where('parent_id', $parentId)
            ->active()
            ->pluck('id');
        $all = [];

        foreach ($childs as $childId) {
            $all[] = $childId;
            $all   = array_merge($all, $this->getChildId($childId));
        }

        return $all;
    }
}