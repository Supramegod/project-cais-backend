<?php

use App\Http\Controllers\CustomerActivityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Activity Routes (sysmenu "Customer Activity")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:customer_activity` di level grup menegakkan is_view untuk seluruh
| route. Route yang mengubah data menambahkan field permission-nya sendiri.
|
| Catatan: prefix `sales-activity` TIDAK ikut ke sini. Controller-nya berbeda
| dan belum punya baris di `sysmenu`, jadi tidak ada acuan permission untuknya.
|
*/

Route::prefix('customer-activities')->controller(CustomerActivityController::class)
    ->middleware('menu:customer_activity')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/send-email', 'sendEmail')->middleware('menu:customer_activity,is_edit');
        Route::post('/add', 'add')->middleware('menu:customer_activity,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:customer_activity,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:customer_activity,is_delete');
        Route::get('/leads/{leadsId}/track', 'trackActivity');
        Route::get('/available', 'availableLeads');
    });
