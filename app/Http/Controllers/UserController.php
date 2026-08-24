<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserListRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints read-only untuk lookup user"
 * )
 */
class UserController extends Controller
{
    private const DEFAULT_PER_PAGE = 25;

    /**
     * @OA\Get(
     *     path="/api/users/list",
     *     tags={"Users"},
     *     summary="Daftar user dengan filter dan paginasi",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="role_id", in="query", required=false, description="Filter cais_role_id", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", required=false, description="1 = aktif (default), 0 = nonaktif, all = semua", @OA\Schema(type="string", enum={"0","1","all"}, default="1")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Cari di full_name, username, email", @OA\Schema(type="string", maxLength=100)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, default=25)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar user berhasil diambil"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function index(UserListRequest $request): JsonResponse
    {
        return $this->paginatedLookup($request);
    }

    /**
     * @OA\Get(
     *     path="/api/users/by-role/{role_id}",
     *     tags={"Users"},
     *     summary="Daftar user pada satu role, berpaginasi",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="role_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", required=false, description="1 = aktif (default), 0 = nonaktif, all = semua", @OA\Schema(type="string", enum={"0","1","all"}, default="1")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=100)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, default=25)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar user berhasil diambil"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function byRole(UserListRequest $request, int $roleId): JsonResponse
    {
        return $this->paginatedLookup($request, ['role_id' => $roleId]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/by-branch/{branch_id}",
     *     tags={"Users"},
     *     summary="Daftar user pada satu branch, berpaginasi",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="branch_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="role_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", required=false, description="1 = aktif (default), 0 = nonaktif, all = semua", @OA\Schema(type="string", enum={"0","1","all"}, default="1")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=100)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, default=25)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar user berhasil diambil"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function byBranch(UserListRequest $request, int $branchId): JsonResponse
    {
        return $this->paginatedLookup($request, ['branch_id' => $branchId]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/view/{id}",
     *     tags={"Users"},
     *     summary="Detail satu user",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
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
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $user = User::query()
            ->with(['role:id,name', 'branch:id,name'])
            ->find($id);

        if (! $user) {
            return $this->notFoundResponse('User not found');
        }

        return $this->successResponse($this->formatUser($user));
    }

    /**
     * Semua endpoint daftar user lewat sini supaya tidak ada satu pun yang bisa
     * menarik tabel penuh. `m_user` berisi ratusan ribu baris dan satu role saja
     * (Karyawan) menampung hampir seluruhnya, jadi `->get()` tanpa batas di sini
     * berarti kehabisan memori, bukan sekadar lambat.
     *
     * @param  array{role_id?: int, branch_id?: int}  $overrides  Nilai dari route param; menang atas query string.
     */
    private function paginatedLookup(UserListRequest $request, array $overrides = []): JsonResponse
    {
        $perPage = $request->integer('per_page') ?: self::DEFAULT_PER_PAGE;

        $users = $this->baseQuery($request, $overrides)->paginate($perPage);

        $users->getCollection()->transform(fn (User $user): array => $this->formatUser($user));

        return $this->paginatedResponse($users, 'Daftar user berhasil diambil');
    }

    /**
     * @param  array{role_id?: int, branch_id?: int}  $overrides  Nilai dari route param; menang atas query string.
     */
    private function baseQuery(UserListRequest $request, array $overrides = []): Builder
    {
        $query = User::query()->with(['role:id,name', 'branch:id,name']);

        $isActive = (string) $request->input('is_active', '1');

        if ($isActive !== UserListRequest::IS_ACTIVE_ALL) {
            $query->where('is_active', (int) $isActive);
        }

        $roleId = $overrides['role_id'] ?? ($request->filled('role_id') ? $request->integer('role_id') : null);

        if ($roleId !== null) {
            $query->where('cais_role_id', $roleId);
        }

        $branchId = $overrides['branch_id'] ?? ($request->filled('branch_id') ? $request->integer('branch_id') : null);

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('search')) {
            $pattern = $this->likePattern($request->input('search'));

            $query->where(function ($inner) use ($pattern) {
                $inner->whereRaw("full_name LIKE ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("username LIKE ? ESCAPE '\\'", [$pattern])
                    ->orWhereRaw("email LIKE ? ESCAPE '\\'", [$pattern]);
            });
        }

        $query->select(['id', 'full_name', 'username', 'email', 'cais_role_id', 'branch_id', 'is_active'])
            ->orderBy('full_name');

        return $query;
    }

    /**
     * Wildcard `%` dan `_` di-escape supaya pencarian "100%" atau username ber-underscore
     * tidak melebar. Klausa ESCAPE ditulis eksplisit karena SQLite tidak punya escape
     * character default seperti MySQL.
     */
    private function likePattern(?string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim((string) $search));

        return '%'.$escaped.'%';
    }

    /**
     * @return array{
     *     id: int,
     *     full_name: string|null,
     *     username: string|null,
     *     email: string|null,
     *     is_active: bool,
     *     role: array{id: int|null, name: string|null},
     *     branch: array{id: int|null, name: string|null}
     * }
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'role' => [
                'id' => $user->cais_role_id !== null ? (int) $user->cais_role_id : null,
                'name' => $user->role?->name,
            ],
            'branch' => [
                'id' => $user->branch_id !== null ? (int) $user->branch_id : null,
                'name' => $user->branch?->name,
            ],
        ];
    }
}
