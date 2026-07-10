<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimSales\TimSalesRequest;
use App\Http\Requests\TimSales\TimSalesAddMemberRequest;
use App\Http\Requests\TimSales\TimSalesSetLeaderRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return $this->successResponse($data, 'Data tim sales berhasil diambil');
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
        $timSales = TimSales::with([
            'branch',
            'details' => function ($q) {
                $q->whereNull('deleted_at');
            }
        ])->find($id);

        if (!$timSales) {
            return $this->notFoundResponse('Tim sales tidak ditemukan');
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

        return $this->successResponse($data, 'Detail tim sales berhasil diambil');
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
    public function store(TimSalesRequest $request)
    {
        // Get branch info
        $branch = Branch::find($request->branch_id);
        if (!$branch) {
            return $this->notFoundResponse('Branch tidak ditemukan');
        }

        $timSales = TimSales::create([
            'nama' => $request->nama,
            'branch_id' => $request->branch_id,
            'branch' => $branch->name,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id()
        ]);

        // Load relationship for response
        $timSales->load('branch');

        return $this->successResponse($timSales, 'Tim sales berhasil dibuat', 201);
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
    public function update(TimSalesRequest $request, $id)
    {
        $timSales = TimSales::find($id);
        if (!$timSales) {
            return $this->notFoundResponse('Tim sales tidak ditemukan');
        }

        // Get branch info
        $branch = Branch::find($request->branch_id);
        if (!$branch) {
            return $this->notFoundResponse('Branch tidak ditemukan');
        }

        $timSales->update([
            'nama' => $request->nama,
            'branch_id' => $request->branch_id,
            'branch' => $branch->name,
            'updated_by' => Auth::user()->full_name
        ]);

        return $this->successResponse($timSales->fresh(['branch']), 'Tim sales berhasil diupdate');
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
        $timSales = TimSales::find($id);
        if (!$timSales) {
            return $this->notFoundResponse('Tim sales tidak ditemukan');
        }

        DB::transaction(function () use ($timSales) {
            // Update the 'deleted_by' column and then soft delete the TimSales record.
            $timSales->update([
                'deleted_by' => Auth::user()->full_name
            ]);
            $timSales->delete(); // This performs the soft delete

            // Soft delete all related 'details' records too.
            $timSales->details()->update([
                'deleted_by' => Auth::user()->full_name
            ]);
            $timSales->details()->delete();
        });

        return $this->messageResponse('Tim sales berhasil dihapus secara soft delete.');
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
     *                         @OA\Property(property="cais_role_id", type="integer", example=29)
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
        $timSales = TimSales::find($id);
        if (!$timSales) {
            return $this->notFoundResponse('Tim sales tidak ditemukan');
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
                    'cais_role_id' => $member->user->cais_role_id
                ] : null
            ];
        });

        return $this->successResponse($data, 'Anggota tim sales berhasil diambil');
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
    public function addMember(TimSalesAddMemberRequest $request, $id)
    {
        $timSales = TimSales::find($id);
        if (!$timSales) {
            return $this->notFoundResponse('Tim sales tidak ditemukan');
        }

        // Check if user already exists in this team
        $existingMember = $timSales->details()
            ->where('user_id', $request->user_id)
            ->first();

        if ($existingMember) {
            return $this->errorResponse('User sudah menjadi anggota tim ini', 409);
        }

        // Get user info
        $user = User::where('id', $request->user_id)
            ->where('is_active', 1)
            ->whereIn('cais_role_id', [29, 31, 32, 33])
            ->where('branch_id', $timSales->branch_id)
            ->first();

        if (!$user) {
            return $this->notFoundResponse('User tidak ditemukan atau tidak valid untuk branch ini');
        }

        $member = TimSalesDetail::create([
            'tim_sales_id' => $id,
            'nama' => $user->full_name,
            'user_id' => $request->user_id,
            'username' => $user->username,
            'created_by' => Auth::user()->full_name,
            'created_by_user_id' => Auth::id()
        ]);

        return $this->successResponse($member->fresh(['user']), 'Anggota berhasil ditambahkan', 201);
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
        $member = TimSalesDetail::where('id', $memberId)
            ->where('tim_sales_id', $id)
            ->first();

        if (!$member) {
            return $this->notFoundResponse('Anggota tidak ditemukan');
        }

        $member->update([
            'deleted_by' => Auth::user()->full_name
        ]);
        $member->delete();

        return $this->messageResponse('Anggota berhasil dihapus');
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
    public function setLeader(TimSalesSetLeaderRequest $request, $id)
    {
        $timSales = TimSales::find($id);
        if (!$timSales) {
            return $this->notFoundResponse('Tim sales tidak ditemukan');
        }

        $member = $timSales->details()
            ->where('id', $request->member_id)
            ->first();

        if (!$member) {
            return $this->notFoundResponse('Anggota tidak ditemukan');
        }

        DB::transaction(function () use ($timSales, $member) {
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
        });

        return $this->successResponse($member->fresh(), 'Leader berhasil diset');
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
     *                     @OA\Property(property="cais_role_id", type="integer", example=29)
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
        $timSales = TimSales::find($id);
        if (!$timSales) {
            return $this->notFoundResponse('Tim sales tidak ditemukan');
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
            ->whereIn('cais_role_id', [29, 31, 32, 33])
            ->where('branch_id', $timSales->branch_id)
            ->whereNotIn('id', $existingUserIds)
            ->select('id', 'full_name', 'username', 'email', 'cais_role_id')
            ->get();

        return $this->successResponse($availableUsers, 'Daftar user yang tersedia berhasil diambil');
    }




}