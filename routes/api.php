<?php

use App\Http\Controllers\TimSalesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JenisPerusahaanController;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::get('/branches', [TimSalesController::class, 'getBranches']);
    Route::get('/users', [TimSalesController::class, 'getUsers']);


    Route::prefix('jenis-perusahaan')->group(function () {
        Route::get('/list', [JenisPerusahaanController::class, 'list']);
        Route::post('/save', [JenisPerusahaanController::class, 'save']);
        Route::get('/view/{id}', [JenisPerusahaanController::class, 'view']);
        Route::put('/update/{id}', [JenisPerusahaanController::class, 'update']);
        Route::delete('/delete/{id}', [JenisPerusahaanController::class, 'delete']);
    });
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

});

