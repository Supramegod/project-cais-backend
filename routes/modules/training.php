<?php

use App\Http\Controllers\TrainingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Training Routes (sysmenu "Training")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:training` di level grup menegakkan is_view untuk seluruh route. Route
| yang mengubah data menambahkan field permission-nya sendiri di atas itu.
|
| Menu "Training Gada" (sysmenu 53) belum punya endpoint di backend ini, jadi
| tidak ada modul terpisah untuknya.
|
*/

Route::prefix('training')->controller(TrainingController::class)
    ->middleware('menu:training')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:training,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:training,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:training,is_delete');
    });
