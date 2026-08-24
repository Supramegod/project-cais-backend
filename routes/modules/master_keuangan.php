<?php

use App\Http\Controllers\ManagementFeeController;
use App\Http\Controllers\SalaryRuleController;
use App\Http\Controllers\TopController;
use App\Http\Controllers\TunjanganController;
use App\Http\Controllers\UmkController;
use App\Http\Controllers\UmpController;
use App\Http\Controllers\UpahController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Master Keuangan Routes (sysmenu "Master Keuangan")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:master_keuangan` di level grup menegakkan is_view untuk seluruh route.
| Route yang mengubah data menambahkan field permission-nya sendiri di atas itu.
|
*/

Route::prefix('management-fee')->controller(ManagementFeeController::class)
    ->middleware('menu:master_keuangan')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/list-all', 'listAll');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:master_keuangan,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_keuangan,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_keuangan,is_delete');
    });

// TOP (Terms of Payment)
Route::prefix('top')->controller(TopController::class)
    ->middleware('menu:master_keuangan')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/list-all', 'listAll');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:master_keuangan,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_keuangan,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_keuangan,is_delete');
    });

Route::prefix('salary-rule')->controller(SalaryRuleController::class)
    ->middleware('menu:master_keuangan')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/list-all', 'listAll');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:master_keuangan,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_keuangan,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_keuangan,is_delete');
    });

// Tunjangan Posisi
Route::prefix('tunjangan')->controller(TunjanganController::class)
    ->middleware('menu:master_keuangan')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:master_keuangan,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_keuangan,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_keuangan,is_delete');
    });

// UMP (Upah Minimum Provinsi)
Route::prefix('ump')->controller(UmpController::class)
    ->middleware('menu:master_keuangan')
    ->group(function () {
        Route::get('/list', 'index');
        Route::get('/list-all', 'listAll');
        Route::get('/view/{id}', 'view');
        Route::get('/province/{provinceId}', 'listUmp');
        Route::post('/add', 'add')->middleware('menu:master_keuangan,is_add');
    });

// UMK (Upah Minimum Kabupaten/Kota)
Route::prefix('umk')->controller(UmkController::class)
    ->middleware('menu:master_keuangan')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/city/{cityId}', 'listUmk');
        Route::post('/add', 'add')->middleware('menu:master_keuangan,is_add');
    });

// Upah (Unified wage management: UMP, UMK & UMSK)
Route::prefix('upah')->controller(UpahController::class)
    ->middleware('menu:master_keuangan')
    ->group(function () {
        Route::get('/provinsi', 'listProvinsi');
        Route::get('/provinsi/{provinceId}', 'getProvinceDetail');
        Route::get('/kota/{cityId}', 'detailKota');
        Route::get('/umsp/{id}', 'showUmsp');
        Route::get('/umsk/{id}', 'showUmsk');
        Route::post('/umsp', 'storeUmsp')->middleware('menu:master_keuangan,is_add');
        Route::post('/ump', 'storeUmp')->middleware('menu:master_keuangan,is_add');
        Route::post('/umk', 'storeUmk')->middleware('menu:master_keuangan,is_add');
        Route::post('/umsk', 'storeUmsk')->middleware('menu:master_keuangan,is_add');
    });
