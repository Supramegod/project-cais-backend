<?php

namespace App\Services;

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

    /**
     * Fail-closed: role tanpa baris `sysmenu_role` untuk menu tersebut ditolak.
     */
    public function allows(?int $roleId, int $menuId, string $field): bool
    {
        if ($roleId === null) {
            return false;
        }

        return (bool) ($this->forRole($roleId)[$menuId][$field] ?? false);
    }

    public function allowsForUser(?int $userId, ?int $roleId, int $menuId, string $field): bool
    {
        if ($this->allows($roleId, $menuId, $field)) {
            return true;
        }

        if ($userId === null || $roleId === null) {
            return false;
        }

        return (bool) ($this->overridesForUser($roleId, $userId)[$menuId][$field] ?? false);
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
