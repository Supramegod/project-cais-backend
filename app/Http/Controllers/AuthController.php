<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RefreshTokens;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Cais Shelter API",
 *     description="Dokumentasi API Authentication dengan Laravel Sanctum (Plain Token)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token"
 * )
 *
 * @OA\Tag(
 *     name="Authentication",
 *     description="API Endpoints untuk Authentication"
 * )
 */


class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Authentication"},
     *     summary="Login user dengan username",
     *     description="Melakukan autentikasi user dan mengembalikan access token dan refresh token menggunakan Laravel Sanctum",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username"},
     *                  @OA\Property(property="username", type="string", example="superadmin2", description="Username user"),
     *                   @OA\Property(property="password", type="string", example="salesshelter", description="Password user")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login berhasil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login berhasil"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="username", type="string", example="3578142602980002"),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="cais_role_id", type="integer", example=1),
     *                     @OA\Property(property="branch_id", type="integer", example=1)
     *                 ),
     *                 @OA\Property(property="access_token", type="string", example="1|abcdefghijklmnopqrstuvwxyz123456789", description="Sanctum access token berlaku 2 jam"),
     *                 @OA\Property(property="refresh_token", type="string", example="abcdefghijklmnopqrstuvwxyz123456789", description="Refresh token berlaku 7 hari"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="access_token_expires_at", type="string", format="date-time", example="2024-01-01T12:00:00+07:00"),
     *                 @OA\Property(property="refresh_token_expires_at", type="string", format="date-time", example="2024-01-08T12:00:00+07:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Kredensial tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Kredensial yang diberikan tidak valid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Data tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak valid"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan pada server")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);


            $inputUsername = $request->username;

            // 2. Cari pengguna
            $user = User::checkLogin($request->username, $request->password)->first();


            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Username atau password salah',
                ], 401);
            }


            // 3. 🔥 HAPUS SEMUA TOKEN LAMA USER INI
            $this->revokeAllUserTokens($user);

            // 4. Buat token pair baru
            $tokenPair = $user->createTokenPair('auth_token');

            Log::info('User login - semua session lama dihapus', [
                'user_id' => $user->id,
                'username' => $user->username,
                'timestamp' => now()
            ]);

            // 5. Kembalikan Response
            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->full_name ?? $user->name,
                        'email' => $user->email,
                        'cais_role_id' => $user->cais_role_id,
                        'branch_id' => $user->branch_id,
                    ],
                    'access_token' => $tokenPair['access_token']->plainTextToken,
                    'refresh_token' => $tokenPair['refresh_token'],
                    'token_type' => 'Bearer',
                    'access_token_expires_at' => $tokenPair['access_token']->accessToken->expires_at
                        ->timezone('Asia/Jakarta')
                        ->format('Y-m-d H:i:s'),
                    'refresh_token_expires_at' => $tokenPair['refresh_token_model']->expires_at
                        ->timezone('Asia/Jakarta')
                        ->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('Login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'username_attempt' => $request->username ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }
    // Method verifyMD5Password tetap ada tapi tidak dipanggil untuk sementara
    private function verifyMD5Password($inputPassword, $storedMD5Hash)
    {
        $inputMD5 = md5($inputPassword);
        return $inputMD5 === $storedMD5Hash;
    }
    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout user",
     *     description="Menghapus access token Sanctum dan refresh token user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout berhasil",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout berhasil")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function logout()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // 🔥 HAPUS SEMUA TOKEN USER
        $this->revokeAllUserTokens($user);

        Log::info('User logout - semua session dihapus', [
            'user_id' => $user->id,
            'username' => $user->username,
            'timestamp' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ], 200);
    }
    /**
     * @OA\Get(
     *     path="/api/auth/user",
     *     summary="Dapatkan data pengguna yang sedang terautentikasi",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sukses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="employee", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="username", type="string", example="johndoe"),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                     @OA\Property(property="role_name", type="string", example="Admin"),
     *                     @OA\Property(property="company_name", type="string", example="PT Example"),
     *                     @OA\Property(property="remaining_leave_days", type="integer", example=12),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Tidak terautentikasi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function user(Request $request)
    {
        try {
            $user = Auth::user();
            // Cek apakah user terautentikasi
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'employee' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->full_name,
                        'email' => $user->email,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ]
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('User endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id() ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server'
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Authentication"},
     *     summary="Refresh access token menggunakan refresh token",
     *     description="Memperbarui access token yang sudah kadaluarsa menggunakan refresh token yang masih valid dengan Laravel Sanctum",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", example="abcdefghijklmnopqrstuvwxyz123456789", description="Refresh token yang didapat saat login")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Access token berhasil direfresh",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Access token berhasil direfresh"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="access_token", type="string", example="2|abcdefghijklmnopqrstuvwxyz123456789"),
     *                 @OA\Property(property="refresh_token", type="string", example="abcdefghijklmnopqrstuvwxyz123456789"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="access_token_expires_at", type="string", format="date-time", example="2024-01-01T14:00:00+07:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Refresh token tidak valid atau sudah kadaluarsa",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Refresh token tidak valid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Data tidak valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data tidak valid"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error server",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Terjadi kesalahan pada server")
     *         )
     *     )
     * )
     */
    public function refresh(Request $request)
    {
        try {
            $request->validate([
                'refresh_token' => 'required|string'
            ]);

            $hashedToken = hash('sha256', $request->refresh_token);
            $refreshTokenModel = RefreshTokens::where('token', $hashedToken)->first();

            if (!$refreshTokenModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refresh token tidak valid'
                ], 401);
            }

            if ($refreshTokenModel->isExpired()) {
                $refreshTokenModel->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Refresh token telah kadaluarsa'
                ], 401);
            }

            $user = $refreshTokenModel->tokenableUser();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan'
                ], 401);
            }

            // Ambil ID token saat ini agar tidak ikut terhapus (mengenali device)
            $currentTokenId = $refreshTokenModel->access_token_id;

            // Hapus semua token lain kecuali yang sedang dipakai ini
            $this->revokeAllUserTokens($user, $currentTokenId);

            $tokenPair = $user->createTokenPair('auth_token');

            Log::info('Access token refreshed', [
                'user_id' => $user->id,
                'device_info' => $request->userAgent(), // Mengenali via User Agent
                'timestamp' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Access token berhasil direfresh',
                'data' => [
                    'access_token' => $tokenPair['access_token']->plainTextToken,
                    'refresh_token' => $tokenPair['refresh_token'],
                    'token_type' => 'Bearer',
                    'access_token_expires_at' => $tokenPair['access_token']->accessToken->expires_at
                        ->timezone('Asia/Jakarta')
                        ->format('Y-m-d H:i:s'),
                    'refresh_token_expires_at' => $tokenPair['refresh_token_model']->expires_at
                        ->timezone('Asia/Jakarta')
                        ->format('Y-m-d H:i:s'),
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Refresh token error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server'
            ], 500);
        }
    }

    /**
     * 🔥 HELPER: Hapus token yang sudah expired (access token & refresh token)
     * Refresh token yang belum expired TIDAK akan dihapus
     */
    private function revokeAllUserTokens(User $user, $excludeTokenId = null)
    {
        try {
            $now = now();

            // 1️⃣ Hapus ACCESS TOKEN yang sudah expired
            $queryAccessToken = $user->tokens()->where('expires_at', '<', $now);

            if ($excludeTokenId) {
                $queryAccessToken->where('id', '!=', $excludeTokenId);
            }

            $expiredAccessTokenIds = $queryAccessToken->pluck('id')->toArray();

            if (!empty($expiredAccessTokenIds)) {
                $user->tokens()->whereIn('id', $expiredAccessTokenIds)->delete();
            }

            // 2️⃣ Hapus REFRESH TOKEN yang sudah expired (pakai tokenable_id langsung)
            $expiredRefreshCount = RefreshTokens::where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->where('expires_at', '<', $now)
                ->count();

            if ($expiredRefreshCount > 0) {
                RefreshTokens::where('tokenable_type', User::class)
                    ->where('tokenable_id', $user->id)
                    ->where('expires_at', '<', $now)
                    ->delete();
            }

            Log::info('Expired user tokens revoked', [
                'user_id' => $user->id,
                'expired_access_tokens_count' => count($expiredAccessTokenIds),
                'expired_refresh_tokens_count' => $expiredRefreshCount,
                'timestamp' => $now
            ]);

        } catch (Exception $e) {
            Log::error('Error revoking expired user tokens', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    //     private function revokeAllUserTokens(User $user)
// {
//     try {
//         $accessTokenIds = $user->tokens()->pluck('id')->toArray();

    //         if (!empty($accessTokenIds)) {
//             RefreshTokens::whereIn('access_token_id', $accessTokenIds)->delete();
//             $user->tokens()->delete();
//         }

    //         Log::info('All user tokens revoked', [
//             'user_id' => $user->id,
//             'count' => count($accessTokenIds),
//             'timestamp' => now()
//         ]);

    //     } catch (Exception $e) {
//         Log::error('Error revoking all user tokens', [
//             'user_id' => $user->id,
//             'error' => $e->getMessage()
//         ]);
//         throw $e;
//     }
// }
}