<?php

use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Role Access Manager Routes (sysmenu "Role Access Manager")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| Gerbang `roles` dipasang PER-ROUTE, bukan di level grup, karena
| `roles/permissions` adalah endpoint sidebar milik user yang sedang login.
| Kalau ikut digerbang, semua orang di luar pemegang Role Access Manager
| kehilangan sidebar-nya.
|
| Endpoint `users/*` ikut modul ini karena keberadaannya memang untuk mengisi
| layar Role Access Manager; ia tidak punya menu sendiri di sidebar.
|
*/

Route::prefix('roles')->controller(RoleController::class)->group(function () {
    Route::get('/list', 'index')->middleware('menu:role_access');
    Route::get('/view/{id}', 'show')->middleware('menu:role_access');
    Route::get('/permissions', 'menuPermissions');
    Route::post('/{id}/update-permissions', 'updatePermissions')->middleware('menu:role_access,is_edit');
});

Route::prefix('users')->controller(UserController::class)
    ->middleware('menu:role_access')
    ->group(function () {
        Route::get('/list', 'index');
        Route::get('/by-role/{roleId}', 'byRole')->whereNumber('roleId');
        Route::get('/by-branch/{branchId}', 'byBranch')->whereNumber('branchId');
        Route::get('/view/{id}', 'show')->whereNumber('id');
    });
