<?php

use App\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

if (!app()->environment('production')) {

    // Halaman welcome (login form)
    Route::get('/', function () {
        return view('welcome');
    })->name('login');

    // Login web
    Route::post('/login-web', [WebAuthController::class, 'login'])
        ->name('login.web');

    Route::get('/login-web', function () {
        return redirect('/')
            ->withErrors([
                'msg' => 'Silakan login melalui form.'
            ]);
    });

    // Logout
    Route::post('/logout-web', [WebAuthController::class, 'logout'])
        ->name('logout.web');

    // Refresh
    Route::get('/refresh-web', [WebAuthController::class, 'refresh'])
        ->name('web.refresh');

} else {

    // PROD -> kosong
    Route::get('/', fn() => abort(404));

    Route::get('/api/documentation', fn() => abort(404));

    Route::get('/docs', fn() => abort(404));
}