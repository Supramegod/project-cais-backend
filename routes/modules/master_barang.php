<?php

use App\Http\Controllers\BarangController;
use App\Http\Controllers\ChemicalController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\JenisBarangController;
use App\Http\Controllers\KaporlapController;
use App\Http\Controllers\OhcController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Master Barang Routes (sysmenu "Master Barang")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:master_barang` di level grup menegakkan is_view untuk seluruh route.
| Route yang mengubah data menambahkan field permission-nya sendiri di atas itu.
|
*/

Route::prefix('jenis-barang')->controller(JenisBarangController::class)
    ->middleware('menu:master_barang')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/list-detail/{id}', 'listdetail');
        Route::post('/add', 'add')->middleware('menu:master_barang,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_barang,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_barang,is_delete');
    });

Route::prefix('supplier')->controller(SupplierController::class)
    ->middleware('menu:master_barang')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:master_barang,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_barang,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_barang,is_delete');
    });

Route::prefix('barang')->controller(BarangController::class)
    ->middleware('menu:master_barang')
    ->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:master_barang,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_barang,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_barang,is_delete');

        // Default Quantity Management
        Route::get('/default-qty/{id}', 'getDefaultQty');
        Route::get('/{barangId}/default-qty/{layananId}', 'getDefaultQtyByLayanan');
        Route::post('/default-qty/save', 'saveDefaultQty')->middleware('menu:master_barang,is_add');
        Route::post('/default-qty/bulk-save', 'bulkSaveDefaultQty')->middleware('menu:master_barang,is_add');
        Route::delete('/default-qty/delete/{id}', 'deleteDefaultQty')->middleware('menu:master_barang,is_delete');
    });

// Kaporlap (hanya index)
Route::prefix('kaporlap')->controller(KaporlapController::class)
    ->middleware('menu:master_barang')
    ->group(function () {
        Route::get('/list', 'list');
    });

// Devices (hanya index)
Route::prefix('devices')->controller(DevicesController::class)
    ->middleware('menu:master_barang')
    ->group(function () {
        Route::get('/list', 'list');
    });

// OHC (hanya index)
Route::prefix('ohc')->controller(OhcController::class)
    ->middleware('menu:master_barang')
    ->group(function () {
        Route::get('/list', 'list');
    });

// Chemical (hanya index)
Route::prefix('chemical')->controller(ChemicalController::class)
    ->middleware('menu:master_barang')
    ->group(function () {
        Route::get('/list', 'list');
    });
