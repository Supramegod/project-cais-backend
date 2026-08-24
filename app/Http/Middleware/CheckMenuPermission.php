<?php

namespace App\Http\Middleware;

use App\Services\MenuPermissionService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan permission `sysmenu_role` di backend.
 *
 * Dipakai sebagai `menu:{modul}` (default `is_view`) atau
 * `menu:{modul},{is_add|is_edit|is_delete}`. Modul dipetakan ke `sysmenu.id`
 * lewat config/menu_permissions.php.
 */
class CheckMenuPermission
{
    public function __construct(private MenuPermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $module, string $field = 'is_view'): Response
    {
        if (! in_array($field, MenuPermissionService::FIELDS, true)) {
            throw new RuntimeException("Field permission tidak dikenal: {$field}");
        }

        $menuId = (int) config("menu_permissions.{$module}");

        if ($menuId <= 0) {
            throw new RuntimeException("Modul '{$module}' belum dipetakan di config/menu_permissions.php");
        }

        $user = $request->user();
        $roleId = $user?->cais_role_id;

        $allowed = $this->permissions->allowsForUser(
            $user?->id !== null ? (int) $user->id : null,
            $roleId !== null ? (int) $roleId : null,
            $menuId,
            $field
        );

        if (! $allowed) {
            Log::warning('Menu permission denied', [
                'user_id' => $user?->id,
                'role_id' => $roleId,
                'module' => $module,
                'sysmenu_id' => $menuId,
                'field' => $field,
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            throw new AuthorizationException('Anda tidak memiliki akses');
        }

        return $next($request);
    }
}
