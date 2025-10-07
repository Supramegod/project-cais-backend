<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\HrisPersonalAccessToken;
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
        
        if ($token && $token instanceof HrisPersonalAccessToken && $token->isExpired()) {
            Log::warning('Token expired attempt', [
                'user_id' => $user->id,
                'username' => $user->username,
                'token_id' => $token->id,
                'expires_at' => $token->expires_at
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Token telah kadaluarsa'
            ], 401);
        }

        return $next($request);
    }
}