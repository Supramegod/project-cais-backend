<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationStepController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Quotation Routes (sysmenu "Quotation")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:quotation` di level grup menegakkan is_view untuk seluruh route. Route
| yang mengubah data menambahkan field permission-nya sendiri di atas itu.
|
| Admin Panel ikut modul ini karena isinya menulis langsung ke data quotation.
| Kalau dibiarkan tanpa gerbang, ia jadi jalan pintas yang melewati /quotations.
| Dua route /admin-panel/consultations yang publik tetap di routes/api.php.
|
*/

Route::prefix('quotations')->controller(QuotationController::class)
    ->middleware('menu:quotation')
    ->group(function () {
        Route::get('/list', 'index');
        Route::post('/add/{tipe_quotation}', 'store')->middleware('menu:quotation,is_add');
        Route::get('/view/{id}', 'show');
        Route::delete('/delete/{id}', 'destroy')->middleware('menu:quotation,is_delete');
        Route::post('/{sourceId}/copy/{targetId}', 'copy')->middleware('menu:quotation,is_add');
        Route::post('/{id}/resubmit', 'resubmit')->middleware('menu:quotation,is_edit');
        Route::post('/{id}/submit-approval', 'submitForApproval')->middleware('menu:quotation,is_edit');
        Route::post('/{id}/reset-approval', 'resetApproval')->middleware('menu:quotation,is_edit');
        Route::get('/{id}/calculate', 'calculate');
        Route::get('/{id}/export-pdf', 'exportPdf');
        Route::get('/{id}/status', 'getStatus');
        Route::get('/available-leads/{tipe_quotation}', 'availableLeads');
        Route::get('/reference/{leads_id}', 'getReferenceQuotations');
        Route::get('/hc-high-cost', 'getSitesWithHighHcAndCost');
    });

// Quotation Step Management
Route::prefix('quotations-step')->controller(QuotationStepController::class)
    ->middleware('menu:quotation')
    ->group(function () {
        Route::get('/{id}/step/{step}', 'getStep');
        Route::post('/{id}/step/{step}', 'updateStep')->middleware('menu:quotation,is_edit');
    });

// Admin Panel Routes - untuk update step quotation secara khusus
Route::prefix('admin-panel')->controller(AdminPanelController::class)
    ->middleware('menu:quotation')
    ->group(function () {
        // Get step data
        Route::get('/quotations/{quotation}/step-data/{step}', 'getStepData');

        // Update steps - menggunakan POST (jika ingin konsisten)
        Route::post('/quotations/{quotation}/hc', 'updateStep3')->middleware('menu:quotation,is_edit');
        Route::post('/quotations/{quotation}/kaporlap', 'updateStep7')->middleware('menu:quotation,is_edit');
        Route::post('/quotations/{quotation}/devices', 'updateStep8')->middleware('menu:quotation,is_edit');
        Route::post('/quotations/{quotation}/chemical', 'updateStep9')->middleware('menu:quotation,is_edit');
        Route::post('/quotations/{quotation}/ohc', 'updateStep10')->middleware('menu:quotation,is_edit');
        Route::post('/quotations/{quotation}/harga-jual', 'updateStep11')->middleware('menu:quotation,is_edit');
    });
