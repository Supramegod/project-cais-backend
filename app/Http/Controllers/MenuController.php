<?php

namespace App\Http\Controllers;

use App\Http\Requests\Menu\MenuAssignRequest;
use App\Http\Requests\Menu\MenuGroupStoreRequest;
use App\Http\Requests\Menu\MenuStoreRequest;
use App\Http\Requests\Menu\MenuUpdateRequest;
use App\Models\Sysmenu;
use App\Models\SysmenuGroup;
use App\Services\MenuPermissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Menu",
 *     description="API untuk manajemen Menu"
 * )
 */
class MenuController extends Controller
{
    public function __construct(private MenuPermissionService $menuPermissions) {}

    /**
     * @OA\Get(
     *     path="/api/menu/list",
     *     summary="Get all menus",
     *     tags={"Menu"},
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
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function list(Request $request)
    {
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
                    'id' => $menu->id,
                    'nama' => $menu->nama,
                    'url' => $menu->url,
                    'icon' => $menu->icon,
                    'created_at' => $menu->created_at
                        ? Carbon::parse($menu->created_at)->isoFormat('D MMMM Y')
                        : null,
                    'children' => $buildTree($menu->id),
                ];
            })->values();
        };

        // Menu utama = parent_id NULL
        $formattedMenus = $buildTree(null);

        return $this->successResponse($formattedMenus);
    }

    /**
     * @OA\Get(
     *     path="/api/menu/view/{id}",
     *     summary="Get menu by ID",
     *     tags={"Menu"},
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
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
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
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function view(Request $request, $id)
    {
        $menu = Sysmenu::active()->find($id);

        if (! $menu) {
            return $this->notFoundResponse('Menu not found');
        }

        return $this->successResponse($menu);
    }

    /**
     * @OA\Post(
     *     path="/api/menu/add",
     *     summary="Create new menu",
     *     tags={"Menu"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"nama","url"},
     *
     *             @OA\Property(property="nama", type="string", example="Dashboard", maxLength=100),
     *             @OA\Property(property="parent_id", type="integer", example=null, nullable=true),
     *             @OA\Property(property="url", type="string", example="/dashboard", maxLength=255),
     *             @OA\Property(property="icon", type="string", example="mdi mdi-home", maxLength=100, nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data Berhasil Disimpan"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nama", type="string")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     )
     * )
     */
    public function add(MenuStoreRequest $request)
    {
        $menu = Sysmenu::create([
            'nama' => $request->nama,
            'parent_id' => $request->parent_id,
            'url' => $request->url,
            'icon' => $request->icon,
            'created_at' => Carbon::now()->toDateTimeString(),
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id(),
        ]);

        // Peta parent dipakai untuk menegakkan is_view leluhur, jadi harus
        // dibuang begitu struktur menu berubah.
        $this->menuPermissions->forgetMenuTree();

        return $this->successResponse($menu, 'Data Berhasil Disimpan', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/menu/update/{id}",
     *     summary="Update menu",
     *     tags={"Menu"},
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
     *             required={"nama","url"},
     *
     *             @OA\Property(property="nama", type="string", example="Dashboard Updated", maxLength=100),
     *             @OA\Property(property="url", type="string", example="/dashboard-new", maxLength=255),
     *             @OA\Property(property="icon", type="string", example="mdi mdi-home-outline", maxLength=100, nullable=true),
     *             @OA\Property(property="status", type="string", enum={"alpha", "beta"}, example="alpha", nullable=true)
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
     *             @OA\Property(property="message", type="string", example="Data Berhasil Disimpan")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function update(MenuUpdateRequest $request, $id)
    {
        $menu = Sysmenu::active()->find($id);

        if (! $menu) {
            return $this->notFoundResponse('Menu not found');
        }

        $menu->update([
            'nama' => $request->nama,
            'url' => $request->url,
            'icon' => $request->icon,
            'status' => $request->filled('status') ? $request->status : null,
            'updated_at' => Carbon::now()->toDateTimeString(),
            'updated_by' => Auth::user()->full_name,
        ]);

        return $this->messageResponse('Data Berhasil Disimpan');
    }

    /**
     * @OA\Delete(
     *     path="/api/menu/delete/{id}",
     *     summary="Delete menu",
     *     tags={"Menu"},
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
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data Berhasil Dihapus")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     )
     * )
     */
    public function delete(Request $request, $id)
    {
        $menu = Sysmenu::active()->find($id);

        if (! $menu) {
            return $this->notFoundResponse('Menu not found');
        }

        $idDelete = array_merge([$id], $this->getChildId($id));

        Sysmenu::whereIn('id', $idDelete)->update([
            'deleted_at' => Carbon::now()->toDateTimeString(),
            'deleted_by' => Auth::user()->full_name,
        ]);

        $this->menuPermissions->forgetMenuTree();

        return $this->messageResponse('Data Berhasil Dihapus');
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
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="nama", type="string"),
     *                     @OA\Property(property="total_menu", type="integer"),
     *                     @OA\Property(property="menus", type="array",
     *
     *                         @OA\Items(
     *
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
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error"
     *     )
     * )
     */
    public function listGroup(Request $request)
    {
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
                'id' => $group->id,
                'nama' => $group->nama,
                'total_menu' => $group->sysmenus->count(),
                'menus' => $group->sysmenus->map(function ($menu) {
                    return [
                        'id' => $menu->id,
                        'nama' => $menu->nama,
                        'url' => $menu->url,
                        'icon' => $menu->icon,
                    ];
                })->values(),
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * @OA\Post(
     *     path="/api/menu/group/add",
     *     summary="Tambah group menu baru",
     *     tags={"Menu"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"nama"},
     *
     *             @OA\Property(property="nama", type="string", example="Master Data", maxLength=100)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grup Berhasil Dibuat"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Master Data")
     *             )
     *         )
     *     ),
     *
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
    public function addGroup(MenuGroupStoreRequest $request)
    {
        $group = SysmenuGroup::create([
            'nama' => $request->nama,
        ]);

        return $this->successResponse([
            'id' => $group->id,
            'nama' => $group->nama,
        ], 'Grup Berhasil Dibuat', 201);
    }

    /**
     * @OA\Post(
     *     path="/api/menu/group/assign",
     *     summary="Assign satu atau beberapa menu ke dalam sebuah group",
     *     description="Kirim menu_ids berisi satu ID untuk single, atau lebih dari satu ID untuk multiple assign. Menu yang sudah ada di group lain akan dipindahkan ke group baru.",
     *     tags={"Menu"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"group_id","menu_ids"},
     *
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="menu_ids",
     *                 type="array",
     *                 minItems=1,
     *                 description="Array ID menu, isi satu elemen untuk single assign",
     *
     *                 @OA\Items(type="integer", example=3)
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
     *             @OA\Property(property="message", type="string", example="3 menu berhasil ditambahkan ke grup"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="group_nama", type="string", example="Master Data"),
     *                 @OA\Property(property="assigned_count", type="integer", example=3),
     *                 @OA\Property(property="not_found_ids", type="array",
     *                     description="ID menu yang tidak ditemukan / sudah terhapus",
     *
     *                     @OA\Items(type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *
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
    public function assignMenuToGroup(MenuAssignRequest $request)
    {
        $result = DB::transaction(function () use ($request) {
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

            if (! empty($activeMenuIds)) {
                $assignedCount = Sysmenu::whereIn('id', $activeMenuIds)
                    ->update([
                        'group_id' => $group->id,
                        'updated_at' => Carbon::now()->toDateTimeString(),
                        'updated_by' => Auth::user()->full_name,
                    ]);
            }

            return [
                'group' => $group,
                'assigned_count' => $assignedCount,
                'not_found_ids' => $notFoundIds,
            ];
        });

        $group = $result['group'];

        return $this->successResponse([
            'group_id' => $group->id,
            'group_nama' => $group->nama,
            'assigned_count' => $result['assigned_count'],
            'not_found_ids' => $result['not_found_ids'],
        ], "{$result['assigned_count']} menu berhasil ditambahkan ke grup \"{$group->nama}\"");
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
            $all = array_merge($all, $this->getChildId($childId));
        }

        return $all;
    }
}
