<?php

use App\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

// Halaman welcome (login form)
Route::get('/', function () {
    return view('welcome');
})->name('login');

// Proses login web
Route::post('/login-web', [WebAuthController::class, 'login'])->name('login.web');

// Logout web
Route::post('/logout-web', [WebAuthController::class, 'logout'])->name('logout.web');

// Refresh session via cookie (endpoint untuk AJAX)
Route::get('/refresh-web', [WebAuthController::class, 'refresh'])->name('web.refresh');