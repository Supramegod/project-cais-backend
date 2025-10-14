<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\TimSales;
use App\Models\TimSalesDetail;
use App\Models\Branch;
use App\Models\User;

/**
 * @OA\Tag(
 *     name="Tim Sales",
 *     description="API Endpoints untuk mengelola Tim Sales"
 * )
 */
class TimSalesController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tim-sales/list",
     *     tags={"Tim Sales"},
     *     summary="Mendapatkan daftar tim sales",
     *     description="Mengambil semua data tim sales dengan jumlah anggota",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil data tim sales",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Data tim sales berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="Tim Sales Jakarta"),
     *                         @OA\Property(property="branch", type="string", example="Jakarta Pusat"),
     *                         @OA\Property(property="branch_id", type="integer", example=2),
     *                         @OA\Property(property="jumlah_anggota", type="integer", example=5),
     *                         @OA\Property(property="created_at", type="string", format="datetime"),
     *                         @OA\Property(property="created_by", type="string", example="Admin")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error"
     *     )
     * )
     */
    public function list(Request $request)
    {
        try {
            $search = $request->get('search');

            $query = TimSales::with([
                'branch',
                'details' => function ($q) {
                    $q->whereNull('deleted_at');
                }
            ]);

            if ($search) {
                $query->where('nama', 'like', '%' . $search . '%');
            }

            // ambil data tanpa paginate
            $data = $query->get()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'branch' => $item->branch,
                    'branch_id' => $item->branch_id,
                    'jumlah_anggota' => $item->details->count(),
                    'created_at' => $item->created_at,
                    'created_by' => $item->created_by,
                    'updated_at' => $item->updated_at,
                    'updated_by' => $item->updated_by,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data tim sales berhasil diambil',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/tim-sales/show/{id}",
     *     tags={"Tim Sales"},
     *     summary="Mendapatkan detail tim sales",
     *     description="Mengambil data tim sales berdasarkan ID dengan detail anggota",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID tim sales",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil detail tim sales",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Detail tim sales berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Tim Sales Jakarta"),
     *                 @OA\Property(property="branch", type="string", example="Jakarta Pusat"),
     *                 @OA\Property(property="branch_id", type="integer", example=2),
     *                 @OA\Property(
     *                     property="anggota",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama", type="string", example="John Doe"),
     *                         @OA\Property(property="user_id", type="integer", example=123),
     *                         @OA\Property(property="username", type="string", example="john.doe"),
     *                         @OA\Property(property="is_leader", type="integer", example=1)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tim sales tidak ditemukan"
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $timSales = TimSales::with([
                'branch',
                'details' => function ($q) {
                    $q->whereNull('deleted_at');
                }
            ])->find($id);

            if (!$timSales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim sales tidak ditemukan'
                ], 404);
            }

            $data = [
                'id' => $timSales->id,
                'nama' => $timSales->nama,
                'branch' => $timSales->branch,
                'branch_id' => $timSales->branch_id,
                'created_at' => $timSales->created_at,
                'created_by' => $timSales->created_by,
                'updated_at' => $timSales->updated_at,
                'updated_by' => $timSales->updated_by,
                'anggota' => $timSales->details->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'nama' => $detail->nama,
                        'user_id' => $detail->user_id,
                        'username' => $detail->username,
                        'is_leader' => $detail->is_leader,
                        'created_at' => $detail->created_at
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'message' => 'Detail tim sales berhasil diambil',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/tim-sales/store",
     *     tags={"Tim Sales"},
     *     summary="Membuat tim sales baru",
     *     description="Menambah data tim sales baru",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama", "branch_id"},
     *             @OA\Property(property="nama", type="string", example="Tim Sales Bandung"),
     *             @OA\Property(property="branch_id", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tim sales berhasil dibuat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tim sales berhasil dibuat"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Tim Sales Bandung"),
     *                 @OA\Property(property="branch_id", type="integer", example=3),
     *                 @OA\Property(property="created_at", type="string", format="datetime"),
     *                 @OA\Property(property="created_by", type="string", example="Admin")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'branch_id' => 'required|integer|exists:m_branch,id'
            ], [
                'required' => ':attribute harus diisi',
                'string' => ':attribute harus berupa teks',
                'max' => ':attribute maksimal :max karakter',
                'integer' => ':attribute harus berupa angka',
                'exists' => 'Branch tidak ditemukan'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get branch info
            $branch = Branch::find($request->branch_id);
            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch tidak ditemukan'
                ], 404);
            }

            $timSales = TimSales::create([
                'nama' => $request->nama,
                'branch_id' => $request->branch_id,
                'branch' => $branch->name,
                'created_by' => Auth::user()->full_name
            ]);

            // Load relationship for response
            $timSales->load('branch');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tim sales berhasil dibuat',
                'data' => $timSales
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/tim-sales/update/{id}",
     *     tags={"Tim Sales"},
     *     summary="Update tim sales",
     *     description="Mengupdate data tim sales",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID tim sales",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama", "branch_id"},
     *             @OA\Property(property="nama", type="string", example="Tim Sales Surabaya"),
     *             @OA\Property(property="branch_id", type="integer", example=4)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tim sales berhasil diupdate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tim sales berhasil diupdate"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="Tim Sales Surabaya"),
     *                 @OA\Property(property="branch_id", type="integer", example=4)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tim sales tidak ditemukan"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $timSales = TimSales::find($id);
            if (!$timSales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim sales tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'branch_id' => 'required|integer|exists:m_branch,id'
            ], [
                'required' => ':attribute harus diisi',
                'string' => ':attribute harus berupa teks',
                'max' => ':attribute maksimal :max karakter',
                'integer' => ':attribute harus berupa angka',
                'exists' => 'Branch tidak ditemukan'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get branch info
            $branch = Branch::find($request->branch_id);
            if (!$branch) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch tidak ditemukan'
                ], 404);
            }

            $timSales->update([
                'nama' => $request->nama,
                'branch_id' => $request->branch_id,
                'branch' => $branch->name,
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tim sales berhasil diupdate',
                'data' => $timSales->fresh(['branch'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/tim-sales/destroy/{id}",
     *     tags={"Tim Sales"},
     *     summary="Hapus tim sales",
     *     description="Menghapus tim sales (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID tim sales",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tim sales berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tim sales berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tim sales tidak ditemukan"
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $timSales = TimSales::find($id);
            if (!$timSales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim sales tidak ditemukan'
                ], 404);
            }

            // Update the 'deleted_by' column and then soft delete the TimSales record.
            $timSales->update([
                'deleted_by' => Auth::user()->full_name
            ]);
            $timSales->delete(); // This performs the soft delete

            // If 'details' (members) also need to be soft deleted, do it here.
            // Update the 'deleted_by' column for all related details.
            $timSales->details()->update([
                'deleted_by' => Auth::user()->full_name
            ]);

            // Soft delete all related 'details' records.
            // Using `TimSales::details()->delete()` directly performs a soft delete
            // on all related records, assuming the 'details' model is also configured for it.
            $timSales->details()->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tim sales berhasil dihapus secara soft delete.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/tim-sales/getMembers/{id}",
     *     tags={"Tim Sales"},
     *     summary="Mendapatkan anggota tim sales",
     *     description="Mengambil daftar anggota dari tim sales tertentu",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID tim sales",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil anggota tim sales",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Anggota tim sales berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama", type="string", example="John Doe"),
     *                     @OA\Property(property="user_id", type="integer", example=123),
     *                     @OA\Property(property="username", type="string", example="john.doe"),
     *                     @OA\Property(property="is_leader", type="integer", example=1),
     *                     @OA\Property(
     *                         property="user_detail",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="full_name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                         @OA\Property(property="role_id", type="integer", example=29)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tim sales tidak ditemukan"
     *     )
     * )
     */
    public function getMembers($id)
    {
        try {
            $timSales = TimSales::find($id);
            if (!$timSales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim sales tidak ditemukan'
                ], 404);
            }

            $members = $timSales->details()->with('user')->get();

            $data = $members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'nama' => $member->nama,
                    'user_id' => $member->user_id,
                    'username' => $member->username,
                    'is_leader' => $member->is_leader,
                    'created_at' => $member->created_at,
                    'user_detail' => $member->user ? [
                        'full_name' => $member->user->full_name,
                        'email' => $member->user->email,
                        'role_id' => $member->user->role_id
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Anggota tim sales berhasil diambil',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/tim-sales/addMember/{id}",
     *     tags={"Tim Sales"},
     *     summary="Tambah anggota tim sales",
     *     description="Menambah anggota ke tim sales",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID tim sales",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="integer", example=123)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Anggota berhasil ditambahkan",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Anggota berhasil ditambahkan"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="John Doe"),
     *                 @OA\Property(property="user_id", type="integer", example=123),
     *                 @OA\Property(property="username", type="string", example="john.doe")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tim sales atau user tidak ditemukan"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="User sudah menjadi anggota tim"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     )
     * )
     */
    public function addMember(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:mysqlhris.m_user,id'
            ], [
                'required' => ':attribute harus diisi',
                'integer' => ':attribute harus berupa angka',
                'exists' => 'User tidak ditemukan'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $timSales = TimSales::find($id);
            if (!$timSales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim sales tidak ditemukan'
                ], 404);
            }

            // Check if user already exists in this team
            $existingMember = $timSales->details()
                ->where('user_id', $request->user_id)
                ->first();

            if ($existingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'User sudah menjadi anggota tim ini'
                ], 409);
            }

            // Get user info
            $user = User::where('id', $request->user_id)
                ->where('is_active', 1)
                ->whereIn('role_id', [29, 31, 32, 33])
                ->where('branch_id', $timSales->branch_id)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan atau tidak valid untuk branch ini'
                ], 404);
            }

            $member = TimSalesDetail::create([
                'tim_sales_id' => $id,
                'nama' => $user->full_name,
                'user_id' => $request->user_id,
                'username' => $user->username,
                'created_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Anggota berhasil ditambahkan',
                'data' => $member->fresh(['user'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambah anggota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/tim-sales/removeMember/{id}/{memberId}",
     *     tags={"Tim Sales"},
     *     summary="Hapus anggota tim sales",
     *     description="Menghapus anggota dari tim sales",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID tim sales",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="memberId",
     *         in="path",
     *         description="ID anggota",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Anggota berhasil dihapus",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Anggota berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Anggota tidak ditemukan"
     *     )
     * )
     */
    public function removeMember($id, $memberId)
    {
        try {
            DB::beginTransaction();

            $member = TimSalesDetail::where('id', $memberId)
                ->where('tim_sales_id', $id)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anggota tidak ditemukan'
                ], 404);
            }

            $member->update([
                'deleted_by' => Auth::user()->full_name
            ]);
            $member->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Anggota berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus anggota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/tim-sales/setLeader/{id}",
     *     tags={"Tim Sales"},
     *     summary="Set leader tim sales",
     *     description="Mengatur leader dari tim sales",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID tim sales",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"member_id"},
     *             @OA\Property(property="member_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leader berhasil diset",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leader berhasil diset"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama", type="string", example="John Doe"),
     *                 @OA\Property(property="is_leader", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tim sales atau anggota tidak ditemukan"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     )
     * )
     */
    public function setLeader(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'member_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $timSales = TimSales::find($id);
            if (!$timSales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim sales tidak ditemukan'
                ], 404);
            }

            $member = $timSales->details()
                ->where('id', $request->member_id)
                ->first();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anggota tidak ditemukan'
                ], 404);
            }

            // Cek leader lama
            $oldLeader = $timSales->details()->where('is_leader', 1)->first();
            if ($oldLeader && $oldLeader->id !== $member->id) {
                $oldLeader->update([
                    'is_leader' => 0,
                    'updated_by' => Auth::user()->full_name
                ]);
            }

            // Set leader baru
            $member->update([
                'is_leader' => 1,
                'updated_by' => Auth::user()->full_name
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leader berhasil diset',
                'data' => $member->fresh()
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengatur leader',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/branches",
     *     tags={"Tim Sales"},
     *     summary="Mendapatkan daftar branch",
     *     description="Mengambil daftar branch yang aktif",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar branch",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar branch berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Jakarta Pusat"),
     *                     @OA\Property(property="description", type="string", example="JKT"),
     *                     @OA\Property(property="is_active", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getBranches()
    {
        try {
            $branches = Branch::where('is_active', 1)
                ->select('id', 'name', 'description', 'is_active')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar branch berhasil diambil',
                'data' => $branches
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Tim Sales"},
     *     summary="Mendapatkan daftar user sales",
     *     description="Mengambil daftar user dengan role sales berdasarkan branch",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         description="ID branch",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar user",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar user berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="full_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="john.doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="role_id", type="integer", example=29),
     *                     @OA\Property(property="branch_id", type="integer", example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validasi gagal"
     *     )
     * )
     */
    public function getUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $users = User::where('is_active', 1)
                ->whereIn('role_id', [29, 31, 32, 33])
                ->where('branch_id', $request->branch_id)
                ->select('id', 'full_name', 'username', 'email', 'role_id', 'branch_id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar user berhasil diambil',
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/tim-sales/getAvailableUsers/{id}",
     *     tags={"Tim Sales"},
     *     summary="Mendapatkan user yang tersedia untuk ditambah ke tim",
     *     description="Mengambil daftar user yang belum menjadi anggota tim sales manapun di branch yang sama dengan tim ini",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID tim sales (digunakan untuk menentukan branch)",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mengambil daftar user yang tersedia",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar user yang tersedia berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="full_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="john.doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="role_id", type="integer", example=29)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tim sales tidak ditemukan"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Terjadi kesalahan saat mengambil data"
     *     )
     * )
     */
    public function getAvailableUsers($id)
    {
        try {
            $timSales = TimSales::find($id);
            if (!$timSales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tim sales tidak ditemukan'
                ], 404);
            }

            // Ambil semua user_id yang sudah jadi anggota tim di branch yang sama
            $existingUserIds = TimSales::where('branch_id', $timSales->branch_id)
                ->with('details') // pastikan relasi details ada di model TimSales
                ->get()
                ->pluck('details.*.user_id') // ambil semua user_id dari details
                ->flatten()
                ->unique()
                ->toArray();

            // Ambil user yang aktif, role sesuai, branch sama, dan
            $availableUsers = User::where('is_active', 1)
                ->whereIn('role_id', [29, 31, 32, 33])
                ->where('branch_id', $timSales->branch_id)
                ->whereNotIn('id', $existingUserIds)
                ->select('id', 'full_name', 'username', 'email', 'role_id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar user yang tersedia berhasil diambil',
                'data' => $availableUsers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data',
                'error' => $e->getMessage()
            ], 500);
        }
    }




}