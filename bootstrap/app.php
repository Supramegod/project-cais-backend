<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi(); // Ini yang sudah kamu tambah tadi
    
        // Tambahkan ini untuk memastikan web session terbaca di rute API
        $middleware->group('api', [
            \App\Http\Middleware\ApiResponseMiddleware::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'token.expiry' => \App\Http\Middleware\CheckTokenExpiry::class,
            'menu' => \App\Http\Middleware\CheckMenuPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Pemetaan exception → envelope JSON tersentralisasi untuk request API.
        // Hanya berlaku untuk request yang mengharapkan JSON (rute /api/*),
        // sehingga rute web tetap memakai handler default.
        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // biarkan handler default menangani
            }

            // Validasi → samakan dengan bentuk BaseRequest: { message: { field: [..] } }
            if ($e instanceof ValidationException) {
                return response()->json(['message' => $e->errors()], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses'], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
            }

            // HttpException lain (403/405/dll) — pertahankan status & pesannya
            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Error',
                ], $e->getStatusCode());
            }

            // Fallback 500 — sembunyikan detail di produksi
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan server',
            ], 500);
        });
    })->create();

// NOTE: Sanctum::usePersonalAccessTokenModel(HrisPersonalAccessToken::class) is
// registered in AppServiceProvider::boot() / SanctumServiceProvider — a call here
// (after `return`) would be unreachable dead code, so it is intentionally omitted.