<?php

namespace App\Services;

use App\Models\Sysmenu;
use App\Models\SysmenuRole;
use Illuminate\Support\Facades\Cache;

/**
 * Sumber kebenaran authorization berbasis tabel `sysmenu_role`.
 *
 * Sebelumnya permission ini hanya dikirim ke frontend (MenuController) dan
 * dikelola lewat RoleController, tanpa pernah ditegakkan di backend. Service
 * ini menjadi satu-satunya titik baca permission supaya cache-nya konsisten.
 */
class MenuPermissionService
{
    /** @var list<string> */
    public const FIELDS = ['is_view', 'is_add', 'is_edit', 'is_delete'];

    private const CACHE_TTL_SECONDS = 300;

    private const MENU_TREE_CACHE_KEY = 'menu-perm:tree';

    /**
     * Fail-closed: role tanpa baris `sysmenu_role` untuk menu tersebut ditolak.
     */
    public function allows(?int $roleId, int $menuId, string $field): bool
    {
        return $this->allowsForUser(null, $roleId, $menuId, $field);
    }

    /**
     * Baris user-level meng-override baris role-level per menu: kalau user punya
     * baris untuk menu tersebut, nilainya dipakai apa adanya (termasuk mencabut
     * akses). Menu tanpa baris user tetap mengikuti role.
     *
     * Menu anak juga menuntut `is_view` pada seluruh menu leluhurnya, supaya
     * route tidak terbuka untuk cabang yang sudah disembunyikan dari sidebar.
     */
    public function allowsForUser(?int $userId, ?int $roleId, int $menuId, string $field): bool
    {
        if ($roleId === null) {
            return false;
        }

        if (! $this->resolve($userId, $roleId, $menuId, $field)) {
            return false;
        }

        foreach ($this->ancestorsOf($menuId) as $ancestorId) {
            if (! $this->resolve($userId, $roleId, $ancestorId, 'is_view')) {
                return false;
            }
        }

        return true;
    }

    private function resolve(?int $userId, int $roleId, int $menuId, string $field): bool
    {
        if ($userId !== null) {
            $override = $this->overridesForUser($roleId, $userId)[$menuId] ?? null;

            if ($override !== null) {
                return (bool) $override[$field];
            }
        }

        return (bool) ($this->forRole($roleId)[$menuId][$field] ?? false);
    }

    /**
     * Leluhur menu, dari parent terdekat ke akar. Aman terhadap parent_id yang
     * membentuk siklus.
     *
     * @return list<int>
     */
    public function ancestorsOf(int $menuId): array
    {
        $parents = $this->menuParents();
        $ancestors = [];
        $seen = [$menuId => true];
        $current = $parents[$menuId] ?? null;

        while ($current !== null && array_key_exists($current, $parents) && ! isset($seen[$current])) {
            $seen[$current] = true;
            $ancestors[] = $current;
            $current = $parents[$current] ?? null;
        }

        return $ancestors;
    }

    /**
     * @return array<int, int|null>
     */
    private function menuParents(): array
    {
        return Cache::remember(
            self::MENU_TREE_CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => Sysmenu::query()
                ->pluck('parent_id', 'id')
                ->map(fn ($parentId): ?int => $parentId === null ? null : (int) $parentId)
                ->all()
        );
    }

    public function forgetMenuTree(): void
    {
        Cache::forget(self::MENU_TREE_CACHE_KEY);
    }

    /**
     * @return array<int, array<string, bool>>
     */
    public function forRole(int $roleId): array
    {
        return Cache::remember(
            self::cacheKey($roleId),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->loadFromDatabase($roleId)
        );
    }

    /**
     * @return array<int, array<string, bool>>
     */
    public function overridesForUser(int $roleId, int $userId): array
    {
        return Cache::remember(
            self::userCacheKey($roleId, $userId),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->loadFromDatabase($roleId, $userId)
        );
    }

    public function forget(int $roleId): void
    {
        Cache::forget(self::cacheKey($roleId));
    }

    public function forgetUser(int $roleId, int $userId): void
    {
        Cache::forget(self::userCacheKey($roleId, $userId));
    }

    /**
     * @return array<int, array<string, bool>>
     */
    private function loadFromDatabase(int $roleId, ?int $userId = null): array
    {
        return SysmenuRole::query()
            ->forRole($roleId)
            ->when(
                $userId === null,
                fn ($query) => $query->roleLevel(),
                fn ($query) => $query->forUser($userId)
            )
            ->get(array_merge(['sysmenu_id'], self::FIELDS))
            ->keyBy('sysmenu_id')
            ->map(function (SysmenuRole $permission): array {
                $flags = [];

                foreach (self::FIELDS as $field) {
                    $flags[$field] = (bool) $permission->{$field};
                }

                return $flags;
            })
            ->all();
    }

    private static function cacheKey(int $roleId): string
    {
        return "menu-perm:role:{$roleId}";
    }

    private static function userCacheKey(int $roleId, int $userId): string
    {
        return "menu-perm:role:{$roleId}:user:{$userId}";
    }
}
