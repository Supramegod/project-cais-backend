<?php

use App\Http\Controllers\PksController;
use App\Http\Controllers\PksFulfillmentController;
use App\Http\Controllers\PksItemFulfillmentDashboardController;
use App\Http\Controllers\PksWizardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PKS Routes (sysmenu "PKS")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:pks` di level grup menegakkan is_view untuk seluruh route. Route yang
| mengubah data menambahkan field permission-nya sendiri di atas itu.
|
| Wizard dan fulfillment ikut modul yang sama karena keduanya layar turunan
| dari menu PKS, bukan menu tersendiri di sidebar.
|
*/

Route::prefix('pks')->controller(PksController::class)
    ->middleware('menu:pks')
    ->group(function () {
        // Basic CRUD
        Route::get('/list', 'index');
        Route::get('/view/{id}', 'show');
        Route::post('/add/{tipe}', 'store')->middleware('menu:pks,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:pks,is_edit');
        Route::delete('/delete/{id}', 'destroy')->middleware('menu:pks,is_delete');

        // Approval & Activation
        Route::post('/{id}/approve', 'approve')->middleware('menu:pks,is_edit');
        Route::post('/{id}/activate', 'activate')->middleware('menu:pks,is_edit');

        // Template Data
        Route::get('/{id}/perjanjian', 'getPerjanjianTemplateData');

        // Available Resources
        Route::get('/available-leads', 'getAvailableLeads');
        Route::get('/available-sites/{leadsId}/{tipe}', 'getAvailableSites');
        Route::post('/{id}/submit-checklist', 'submitChecklist')->middleware('menu:pks,is_edit');
        Route::post('/upload/{id}', 'uploadPks')->middleware('menu:pks,is_edit');

        // ==================== PERJANJIAN (edit, history, compare) ====================
        Route::put('/perjanjian/{id}', 'updatePerjanjian')->middleware('menu:pks,is_edit');               // Update konten perjanjian
        Route::get('/perjanjian/{id}/history', 'getPerjanjianHistory');   // Lihat daftar riwayat perubahan
        Route::post('/perjanjian/compare', 'comparePerjanjian');
        Route::post('/{pks_id}/perjanjian', 'storePasal')->middleware('menu:pks,is_add');
        Route::delete('/perjanjian/{id}', 'destroyPasal')->middleware('menu:pks,is_delete');         // Bandingkan dua versi
    });

Route::prefix('pks-wizard')->controller(PksWizardController::class)
    ->middleware('menu:pks')
    ->group(function () {
        Route::post('/source/quotations/{leadsId}', 'getAvailableQuotations');
        Route::get('/source/spk/{leadsId}', 'getAvailableSpk');
        Route::post('/initialize/{tipe}', 'initialize')->middleware('menu:pks,is_add');
        Route::get('/{pksId}/step/{step}', 'getStep');
        Route::post('/{pksId}/step/{step}', 'updateStep')->middleware('menu:pks,is_edit');
        Route::post('/{pksId}/preview-pasal', 'generatePasalPreview')->middleware('menu:pks,is_edit');
        Route::put('/{pksId}/preview-pasal/{pasalKey}', 'updatePasalPreview')->middleware('menu:pks,is_edit');
        Route::post('/{pksId}/finalize', 'finalize')->middleware('menu:pks,is_edit');
        Route::delete('/{pksId}', 'cancel')->middleware('menu:pks,is_delete');
    });

// Dashboard item fulfillment — daftar PKS + 4 angka agregat. Didaftarkan di
// luar grup di bawah (controller berbeda) dan sebelum rute /{pks}/... supaya
// tidak tertangkap route binding PKS.
Route::get('pks-fulfillment/item-dashboard', [PksItemFulfillmentDashboardController::class, 'itemDashboard'])
    ->middleware('menu:pks');

Route::prefix('pks-fulfillment')->controller(PksFulfillmentController::class)
    ->middleware('menu:pks')
    ->group(function () {
        // Dashboard rekap seluruh PKS aktif
        Route::get('/dashboard', 'dashboard');

        // Ringkasan pemenuhan per PKS (detail)
        Route::get('/{pks}/summary', 'getFulfillmentSummary');

        // Pemenuhan HC per PKS (read-only, dari HRIS)
        Route::get('/{pks}/hc', 'getHcFulfillment');

        // Item Fulfillment — dua tahap: request barang lalu penerimaan barang.
        Route::get('/{pks}/items', 'getRequestedItems');
        // Tahap 1: request barang. Menaikkan qty_request, bukan qty_terpenuhi.
        Route::post('/item-fulfillment', 'storeFulfillment')->middleware('menu:pks,is_add');
        // Versi bulk — body {items:[...]} atau bare array, all-or-nothing
        Route::post('/item-fulfillment/bulk', 'storeBulkFulfillment')->middleware('menu:pks,is_add');
        // Tahap 2: penerimaan barang di site. Di sinilah qty_terpenuhi naik.
        Route::post('/item-fulfillment/receive', 'receiveFulfillment')->middleware('menu:pks,is_edit');
        // Barang yang sudah dikirim dan menunggu diterima — isi form penerimaan
        Route::get('/{pks}/item-request', 'getItemRequests');
        Route::patch('/item-fulfillment/{fulfillment}', 'editFulfillment')->middleware('menu:pks,is_edit');
        Route::get('/item-fulfillment/{fulfillment}/log', 'getFulfillmentLog');

        // Isi satu batch pengiriman — didaftarkan sebelum rute /{pks}/... supaya
        // tidak tertangkap route binding PKS.
        Route::get('/fulfillment-log/batch/{batchId}', 'getBatchDetail');

        // Log fulfillment per PKS (item + visit), dikelompokkan per batch,
        // filter opsional ?jenis=item|visit
        Route::get('/{pks}/fulfillment-log', 'getPksLog');

        // Visit Scheduling
        Route::get('/{pks}/visit-schedule', 'getVisitSchedule');
        Route::post('/visit-schedule', 'storeManualSchedule')->middleware('menu:pks,is_add');
        Route::patch('/visit-schedule/{schedule}/reschedule', 'reschedule')->middleware('menu:pks,is_edit');

        // Visit Fulfillment
        Route::get('/{pks}/visit-target', 'getVisitTarget');
        Route::post('/visit-record', 'storeVisitRecord')->middleware('menu:pks,is_add');
        Route::get('/{pks}/visit-record', 'getVisitHistory');
        Route::get('/visit-photo/{foto}', 'getPhotoUrl'); // fresh signed URL
    });
