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
        
        if ($token && $token->expires_at && now()->gt($token->expires_at)) {
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

        return $next($request);
    }
}