<?php

use App\Http\Controllers\BentukUsahaController;
use App\Http\Controllers\CompanyGroupController;
use App\Http\Controllers\JenisPerusahaanController;
use App\Http\Controllers\KebutuhanController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\TimSalesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Master Data Routes (sysmenu "Master Data")
|--------------------------------------------------------------------------
|
| Di-require dari routes/api.php di dalam grup ['auth:sanctum,web',
| 'token.expiry'], jadi middleware autentikasi terwarisi otomatis.
|
| `menu:master_data` di level grup menegakkan is_view untuk seluruh route.
| Route yang mengubah data menambahkan field permission-nya sendiri di atas itu.
|
*/

Route::prefix('jenis-perusahaan')->controller(JenisPerusahaanController::class)
    ->middleware('menu:master_data')
    ->group(function () {
        Route::get('/list', 'list');
        Route::post('/save', 'save')->middleware('menu:master_data,is_add');
        Route::get('/view/{id}', 'view');
        Route::put('/update/{id}', 'update')->middleware('menu:master_data,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_data,is_delete');
    });

Route::prefix('bentuk-usaha')->controller(BentukUsahaController::class)
    ->middleware('menu:master_data')
    ->group(function () {
        Route::get('/list', 'list');
        Route::post('/save', 'save')->middleware('menu:master_data,is_add');
        Route::get('/view/{id}', 'view');
        Route::put('/update/{id}', 'update')->middleware('menu:master_data,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_data,is_delete');
    });

Route::prefix('tim-sales')->controller(TimSalesController::class)
    ->middleware('menu:master_data')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/show/{id}', 'show');
        Route::post('/store', 'store')->middleware('menu:master_data,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_data,is_edit');
        Route::delete('/destroy/{id}', 'destroy')->middleware('menu:master_data,is_delete');
        Route::get('/getMembers/{id}', 'getMembers');
        Route::post('/addMember/{id}', 'addMember')->middleware('menu:master_data,is_add');
        Route::delete('/removeMember/{id}/{memberId}', 'removeMember')->middleware('menu:master_data,is_delete');
        Route::put('/setLeader/{id}', 'setLeader')->middleware('menu:master_data,is_edit');
        Route::get('/getAvailableUsers/{id}', 'getAvailableUsers');
        Route::post('/bulkAddMembers/{id}', 'bulkAddMembers')->middleware('menu:master_data,is_add');
        Route::get('/getStatistics', 'getStatistics');
    });

Route::prefix('kebutuhan')->controller(KebutuhanController::class)
    ->middleware('menu:master_data')
    ->group(function () {
        Route::get('/list', 'list');
        // detail
        Route::get('/list-detail/{id}', 'listDetail');
        // Detail Tunjangan
        Route::get('/list-detail-tunjangan/{id}', 'listDetailTunjangan');
        Route::post('/add-detail-tunjangan', 'addDetailTunjangan')->middleware('menu:master_data,is_add');
        Route::delete('/delete-detail-tunjangan/{id}', 'deleteDetailTunjangan')->middleware('menu:master_data,is_delete');
        // Detail Requirement
        Route::get('/list-detail-requirement/{id}', 'listDetailRequirement');
        Route::post('/add-detail-requirement', 'addDetailRequirement')->middleware('menu:master_data,is_add');
        Route::delete('/delete-detail-requirement/{id}', 'deleteDetailRequirement')->middleware('menu:master_data,is_delete');
    });

Route::prefix('position')->controller(PositionController::class)
    ->middleware('menu:master_data')
    ->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'save')->middleware('menu:master_data,is_add');
        Route::put('/edit/{id}', 'edit')->middleware('menu:master_data,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_data,is_delete');

        // Position Requirements
        Route::get('/requirement/list/{position_id}', 'requirementList');
        Route::post('/requirement/add', 'addRequirement')->middleware('menu:master_data,is_add');
        Route::put('/requirement/edit', 'requirementEdit')->middleware('menu:master_data,is_edit');
        Route::delete('/requirement/delete/{id}', 'requirementDelete')->middleware('menu:master_data,is_delete');
    });

Route::prefix('company-group')->controller(CompanyGroupController::class)
    ->middleware('menu:master_data')
    ->group(function () {
        // Basic CRUD
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/create', 'create')->middleware('menu:master_data,is_add');
        Route::put('/update/{id}', 'update')->middleware('menu:master_data,is_edit');
        Route::delete('/delete/{id}', 'delete')->middleware('menu:master_data,is_delete');

        // Company Management
        Route::get('/available-companies/{groupId}', 'getAvailableCompanies');
        Route::get('/companies/{groupId}', 'getCompaniesInGroup');
        Route::post('/bulk-assign', 'bulkAssign')->middleware('menu:master_data,is_add');
        Route::delete('/remove-company/{groupId}/{companyId}', 'removeCompany')->middleware('menu:master_data,is_delete');
        Route::delete('/bulk-remove-companies', 'bulkRemoveCompanies')->middleware('menu:master_data,is_delete');

        // Statistics & Recommendations
        Route::get('/statistics', 'getStatistics');
        Route::get('/recommendations', 'getRecommendations');
    });
