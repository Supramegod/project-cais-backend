<?php

use App\Http\Controllers\DashboardApprovalController;
use App\Http\Controllers\DashboardPksController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Routes (sysmenu "Dashboard")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:dashboard` di level grup menegakkan is_view untuk seluruh route. Route
| yang mengubah data menambahkan field permission-nya sendiri di atas itu.
|
*/

Route::prefix('dashboard-approval')->controller(DashboardApprovalController::class)
    ->middleware('menu:dashboard')
    ->group(function () {
        Route::get('/list', 'getListDashboardApprovalData');
        Route::get('/notifications', 'getNotifications');
        Route::put('/notifications/{id}/read', 'markAsRead')->middleware('menu:dashboard,is_edit');
        Route::put('/notifications/read-all', 'markAllAsRead')->middleware('menu:dashboard,is_edit');
        Route::get('/notifications/unread-count', 'getUnreadCount');
    });

// Dashboard PKS - monitoring kontrak
Route::prefix('dashboard-pks')->controller(DashboardPksController::class)
    ->middleware('menu:dashboard')
    ->group(function () {
        Route::get('/summary', 'summary');
        Route::get('/expiring', 'expiring');
        Route::get('/list', 'list');
    });
