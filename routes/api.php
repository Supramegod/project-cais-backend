<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\JenisPerusahaanController;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    Route::prefix('jenis-perusahaan')->group(function () {
        Route::get('/list', [JenisPerusahaanController::class, 'list']);        
        Route::post('/save', [JenisPerusahaanController::class, 'save']);   
        Route::get('/view/{id}', [JenisPerusahaanController::class, 'view']);  
        Route::put('/update/{id}', [JenisPerusahaanController::class, 'update']); 
        Route::delete('/delete/{id}', [JenisPerusahaanController::class, 'delete']); 
    });
});

