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
                        'id' => $menu->id,
                        'nama' => $menu->nama,

                        'url' => $menu->url,
                        'icon' => $menu->icon,
                        'created_at' => $menu->created_at
                            ? Carbon::parse($menu->created_at)->isoFormat('D MMMM Y')
                            : null,
                        'children' => $buildTree($menu->id), // Ambil child-nya
                    ];
                })->values();
            };

            // Menu utama = parent_id NULL
            $formattedMenus = $buildTree(null);

            return response()->json([
                'success' => true,
                'data' => $formattedMenus
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
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
     *             required={"nama","url"},
     *             @OA\Property(property="nama", type="string", example="Dashboard", maxLength=100),
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

    // private function getChildNames($data, $parentId)
    // {
    //     $childNames = [];

    //     foreach ($data as $menu) {
    //         if ($menu->parent_id == $parentId) {
    //             $childNames[] = $menu->nama;
    //             $childNames = array_merge($childNames, $this->getChildNames($data, $menu->id));
    //         }
    //     }

    //     return $childNames;
    // }

    // private function formatMenu($data, $parentId = null, $prefix = '')
    // {
    //     $result = [];

    //     foreach ($data as $menu) {
    //         if ($menu->parent_id == $parentId) {
    //             $menu->nama = $prefix . $menu->nama;
    //             $result[] = $menu;

    //             $children = $this->formatMenu($data, $menu->id, $prefix . '- ');
    //             $result = array_merge($result, $children);
    //         }
    //     }

    //     return $result;
    // }
}