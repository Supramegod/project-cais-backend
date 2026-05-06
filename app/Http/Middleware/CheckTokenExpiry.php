<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class CheckTokenExpiry
{
    public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
    }

    $token = $user->currentAccessToken();

    /** * Cek apakah token adalah instance dari PersonalAccessToken (Token Database)
     * TransientToken (Session) tidak punya expires_at dan tidak perlu dicek kadaluarsanya
     * karena sudah diatur oleh session lifetime Laravel.
     */
    if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
        if ($token->expires_at && now()->greaterThan($token->expires_at)) {
            Log::warning('Access token expired attempt', [
                'user_id' => $user->id,
                'username' => $user->username,
                'token_id' => $token->id,
                'expires_at' => $token->expires_at
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Access token telah kadaluarsa, silakan gunakan refresh token'
            ], 401);
        }
    }

    return $next($request);
}
}