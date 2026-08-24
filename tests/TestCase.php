<?php

namespace Tests;

use App\Services\MenuPermissionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sebagian besar route sekarang berada di balik middleware `menu:{modul}`,
     * yang fail-closed: tanpa baris `sysmenu_role` semua request ditolak.
     *
     * Test di repo ini membangun skema SQLite-nya sendiri dan hampir tidak ada
     * yang membuat tabel `sysmenu_role`, jadi tanpa default ini setiap test
     * fitur harus ikut memodelkan otorisasi — padahal bukan itu yang diujinya.
     * Yang menguji gerbangnya sendiri memanggil useRealMenuPermissions().
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->allowAllMenuPermissions();
    }

    protected function allowAllMenuPermissions(): void
    {
        $this->app->singleton(MenuPermissionService::class, fn (): MenuPermissionService => new class extends MenuPermissionService
        {
            public function allows(?int $roleId, int $menuId, string $field): bool
            {
                return true;
            }

            public function allowsForUser(?int $userId, ?int $roleId, int $menuId, string $field): bool
            {
                return true;
            }
        });
    }

    /**
     * Kembalikan service asli supaya middleware benar-benar membaca
     * `sysmenu_role`. Test yang memanggil ini wajib menyediakan tabelnya.
     */
    protected function useRealMenuPermissions(): void
    {
        $this->app->forgetInstance(MenuPermissionService::class);

        $this->app->singleton(MenuPermissionService::class, fn (): MenuPermissionService => new MenuPermissionService);
    }
}
