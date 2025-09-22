<?php

use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TimSalesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JenisPerusahaanController;
use App\Http\Controllers\KebutuhanController;
use App\Http\Controllers\PositionController; // Tambahkan ini

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Branches & Users
    Route::get('/branches', [TimSalesController::class, 'getBranches']);
    Route::get('/users', [TimSalesController::class, 'getUsers']);

    // Jenis Perusahaan
    Route::prefix('jenis-perusahaan')->controller(JenisPerusahaanController::class)->group(function () {
        Route::get('/list', 'list');
        Route::post('/save', 'save');
        Route::get('/view/{id}', 'view');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // Tim Sales
    Route::prefix('tim-sales')->controller(TimSalesController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/show/{id}', 'show');
        Route::post('/store', 'store');
        Route::put('/update/{id}', 'update');
        Route::delete('/destroy/{id}', 'destroy');
        Route::get('/getMembers/{id}', 'getMembers');
        Route::post('/addMember/{id}', 'addMember');
        Route::delete('/removeMember/{id}/{memberId}', 'removeMember');
        Route::put('/setLeader/{id}', 'setLeader');
        Route::get('/getAvailableUsers/{id}', 'getAvailableUsers');
        Route::post('/bulkAddMembers/{id}', 'bulkAddMembers');
        Route::get('/getStatistics', 'getStatistics');
    });

    // Kebutuhan
    Route::prefix('kebutuhan')->controller(KebutuhanController::class)->group(function () {
        Route::get('/list', 'list');
        // detail
        Route::get('/list-detail/{id}', 'listDetail');
        //Detail Tunjangan
        Route::get('/list-detail-tunjangan/{id}', 'listDetailTunjangan');
        Route::post('/add-detail-tunjangan', 'addDetailTunjangan');
        Route::delete('/delete-detail-tunjangan/{id}', 'deleteDetailTunjangan');
        //Detail Requirement
        Route::get('/list-detail-requirement/{id}', 'listDetailRequirement');
        Route::post('/add-detail-requirement', 'addDetailRequirement');
        Route::delete('/delete-detail-requirement/{id}', 'deleteDetailRequirement');
    });
    
    // Training
    Route::prefix('training')->controller(TrainingController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view'); 
        Route::post('/add', 'add');
        Route::put('/update/{id}', 'update');
        Route::delete('/delete/{id}', 'delete');
    });

    // Position - Tambahkan route ini
    Route::prefix('position')->controller(PositionController::class)->group(function () {
        Route::get('/list', 'list');
        Route::get('/view/{id}', 'view');
        Route::post('/add', 'save');
        Route::put('/edit/{id}', 'edit');
        Route::delete('/delete/{id}', 'delete');
        
        // Position Requirements
        Route::get('/requirement/list/{position_id}', 'requirementList');
        Route::post('/requirement/add', 'addRequirement');
        Route::put('/requirement/edit', 'requirementEdit');
        Route::delete('/requirement/delete/{id}', 'requirementDelete');
    });
});