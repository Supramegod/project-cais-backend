<?php

use App\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Menu Manager Routes (sysmenu "Menu Manager")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:menu_manager` di level grup menegakkan is_view untuk seluruh route.
| Route yang mengubah data menambahkan field permission-nya sendiri di atas itu.
|
| Modulnya dipisah dari Role Access Manager walaupun keduanya anak "Apps
| Manager", karena di sidebar memang dua layar berbeda dan seorang admin bisa
| saja hanya boleh memegang salah satunya.
|
*/

Route::prefix('menu')->controller(MenuController::class)
    ->middleware('menu:menu_manager')
    ->group(function () {
        Route::get('/list', 'list');
        Route::post('/add', 'add')->middleware('menu:menu_manager,is_add');
        Route::get('/view/{id}', 'view');
        Route::put('/update/{id}', 'update')->middleware('menu:menu_manager,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:menu_manager,is_delete');
        // Group routes
        Route::get('/group/list', 'listGroup');
        Route::post('/group/add', 'addGroup')->middleware('menu:menu_manager,is_add');
        Route::post('/group/assign', 'assignMenuToGroup')->middleware('menu:menu_manager,is_edit');
    });
