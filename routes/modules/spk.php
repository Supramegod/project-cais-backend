<?php

use App\Http\Controllers\SpkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPK Routes
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:spk` di level grup menegakkan is_view untuk seluruh route. Route yang
| mengubah data menambahkan field permission-nya sendiri di atas itu.
|
*/

Route::prefix('spk')->controller(SpkController::class)
    ->middleware('menu:spk')
    ->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/list-terhapus', 'listTerhapus');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:spk,is_add');
        Route::put('/delete-site/{id}', 'deleteSite')->middleware('menu:spk,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:spk,is_delete');

        // Cetak SPK
        Route::get('/cetak/{id}', 'cetakSpk');

        // File Upload
        Route::post('/upload/{id}', 'uploadSpk')->middleware('menu:spk,is_edit');

        // Ajukan Ulang Quotation
        Route::post('/ajukan-ulang/{spkId}', 'ajukanUlangQuotation')->middleware('menu:spk,is_edit');

        // Available Resources
        Route::get('/available-quotation', 'availableQuotation');
        Route::get('/available-leads', 'availableLeads');
        Route::get('/available-sites/{leadsId}', 'getSiteAvailableList');

        // Site Management
        Route::get('/site-list/{id}', 'getSiteList');
        Route::get('/spk/deleted-sites/{spkId}', 'getDeletedSpkSites');

        // Submit Checklist
        Route::post('/{id}/submit-checklist', 'submitChecklist')->middleware('menu:spk,is_edit');
    });
