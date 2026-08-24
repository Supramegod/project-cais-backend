<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Mengunci keputusan "route mana digerbang oleh menu mana".
 *
 * Tests\TestCase memasang MenuPermissionService permisif secara default supaya
 * test fitur tidak perlu memodelkan otorisasi. Efek sampingnya, hilangnya
 * middleware `menu:` tidak akan membuat satu pun test itu merah. Kelas ini
 * menutup celah tersebut dengan memeriksa tabel route secara langsung.
 */
class MenuPermissionRouteGuardTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function gatedRouteProvider(): array
    {
        return [
            'dashboard' => ['GET', 'api/dashboard-pks/summary', 'dashboard'],
            'leads & customer' => ['GET', 'api/leads/list', 'leads_customer'],
            'customer activity' => ['GET', 'api/customer-activities/list', 'customer_activity'],
            'quotation' => ['GET', 'api/quotations/list', 'quotation'],
            'quotation via admin panel' => ['GET', 'api/admin-panel/quotations/{quotation}/step-data/{step}', 'quotation'],
            'spk' => ['GET', 'api/spk/list', 'spk'],
            'pks' => ['GET', 'api/pks/list', 'pks'],
            'pks fulfillment' => ['GET', 'api/pks-fulfillment/dashboard', 'pks'],
            'training' => ['GET', 'api/training/list', 'training'],
            'master data' => ['GET', 'api/position/list', 'master_data'],
            'master barang' => ['GET', 'api/barang/list', 'master_barang'],
            'master keuangan' => ['GET', 'api/top/list', 'master_keuangan'],
            'role access' => ['GET', 'api/roles/list', 'role_access'],
            'menu manager' => ['GET', 'api/menu/list', 'menu_manager'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function writeRouteProvider(): array
    {
        return [
            'update permissions butuh is_edit' => ['POST', 'api/roles/{id}/update-permissions', 'role_access,is_edit'],
            'tambah leads butuh is_add' => ['POST', 'api/leads/add', 'leads_customer,is_add'],
            'hapus barang butuh is_delete' => ['DELETE', 'api/barang/delete/{id}', 'master_barang,is_delete'],
            'ubah quotation step butuh is_edit' => ['POST', 'api/quotations-step/{id}/step/{step}', 'quotation,is_edit'],
        ];
    }

    /**
     * Kalau salah satu ini ikut digerbang, kerusakannya luas: `options` mematikan
     * dropdown di semua form, dan `roles/permissions` mengosongkan sidebar setiap
     * user yang bukan pemegang Role Access Manager.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function ungatedRouteProvider(): array
    {
        return [
            'sidebar milik user sendiri' => ['GET', 'api/roles/permissions'],
            'dropdown lintas layar' => ['GET', 'api/options/branches'],
            'konfigurasi email pribadi' => ['GET', 'api/user/list'],
            'helper site lintas modul' => ['GET', 'api/site/list'],
            'profil sendiri' => ['GET', 'api/auth/user'],
        ];
    }

    #[DataProvider('gatedRouteProvider')]
    public function test_route_modul_digerbang_menu_yang_benar(string $method, string $uri, string $module): void
    {
        $this->assertContains(
            'menu:'.$module,
            $this->middlewareFor($method, $uri),
            "{$method} {$uri} seharusnya digerbang menu:{$module}."
        );
    }

    #[DataProvider('writeRouteProvider')]
    public function test_route_pengubah_data_menuntut_field_permission(string $method, string $uri, string $gate): void
    {
        $this->assertContains(
            'menu:'.$gate,
            $this->middlewareFor($method, $uri),
            "{$method} {$uri} seharusnya menuntut menu:{$gate}."
        );
    }

    #[DataProvider('ungatedRouteProvider')]
    public function test_route_tertentu_sengaja_tidak_digerbang(string $method, string $uri): void
    {
        $menuMiddleware = array_filter(
            $this->middlewareFor($method, $uri),
            fn (string $middleware): bool => str_starts_with($middleware, 'menu:')
        );

        $this->assertSame(
            [],
            array_values($menuMiddleware),
            "{$method} {$uri} sengaja dibiarkan tanpa gerbang menu — lihat komentar di routes/api.php."
        );
    }

    public function test_setiap_modul_di_config_dipakai_minimal_satu_route(): void
    {
        $used = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'menu:')) {
                    $used[] = explode(',', substr($middleware, 5))[0];
                }
            }
        }

        $unused = array_diff(array_keys(config('menu_permissions')), array_unique($used));

        $this->assertSame(
            [],
            array_values($unused),
            'Modul di config/menu_permissions.php tidak dipakai route mana pun: '.implode(', ', $unused)
        );
    }

    /**
     * @return list<string>
     */
    private function middlewareFor(string $method, string $uri): array
    {
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return array_values(array_filter($route->gatherMiddleware(), 'is_string'));
            }
        }

        $this->fail("Route {$method} {$uri} tidak terdaftar.");
    }
}
