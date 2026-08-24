<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionV2Controller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leads & Customer Routes (sysmenu "Leads & Customer")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:leads_customer` di level grup menegakkan is_view untuk seluruh route.
| Route yang mengubah data menambahkan field permission-nya sendiri di atas itu.
|
*/

// Submission Routes (Sales > Submission)
Route::prefix('submission')->controller(SubmissionController::class)
    ->middleware('menu:leads_customer')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/convert', 'convert')->middleware('menu:leads_customer,is_edit');
        Route::post('/delete', 'delete')->middleware('menu:leads_customer,is_delete');
    });

// Submission V2 (Google Sheet)
Route::prefix('submission-v2')->controller(SubmissionV2Controller::class)
    ->middleware('menu:leads_customer')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/convert', 'convert')->middleware('menu:leads_customer,is_edit');
        Route::post('/delete', 'delete')->middleware('menu:leads_customer,is_delete');
        Route::post('/sync', 'sync')->middleware('menu:leads_customer,is_edit');
    });

Route::prefix('leads')->controller(LeadsController::class)
    ->middleware('menu:leads_customer')
    ->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'add')->middleware('menu:leads_customer,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:leads_customer,is_edit');
        Route::put('/assign-sales/{id}', 'assignSales')->middleware('menu:leads_customer,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:leads_customer,is_delete');
        Route::delete('/remove-sales/{id}', 'removeSales')->middleware('menu:leads_customer,is_delete');
        Route::post('/restore/{id}', 'restore')->middleware('menu:leads_customer,is_edit');

        // Additional endpoints
        Route::get('/available-sales/{id}', 'availableSales');
        Route::get('/sales-kebutuhan/{id}', 'getSalesKebutuhan');
        Route::get('/deleted', 'listTerhapus');
        Route::get('/child/{id}', 'childLeads');
        Route::post('/child/{id}', 'saveChildLeads')->middleware('menu:leads_customer,is_add');
        Route::get('/belum-aktif', 'leadsBelumAktif');
        Route::get('/available-quotation', 'availableQuotation');
        Route::post('/activate/{id}', 'activateLead')->middleware('menu:leads_customer,is_edit');
        Route::post('/import', 'import')->middleware('menu:leads_customer,is_add');
        Route::get('/export', 'exportExcel');
        Route::get('/template-import', 'templateImport');
        Route::post('/generate-null-kode', 'generateNullKode')->middleware('menu:leads_customer,is_edit');
        Route::get('/spk/{id}', 'getSpkByLead');
        Route::get('/pks/{id}', 'getPksByLead');
        Route::get('/customeractivity/{id}', 'getCustomerActivityByLead');
        Route::get('/quotation/{id}', 'getQuotationByLead');
    });

Route::prefix('customer')->controller(CustomerController::class)
    ->middleware('menu:leads_customer')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::get('/available', 'availableCustomer')->name('customer.available');
    });
