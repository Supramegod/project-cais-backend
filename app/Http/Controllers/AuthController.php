<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
 *     title="Cais Shalter API",
 *     description="Dokumentasi API Authentication dengan Laravel Sanctum (Plain Token)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token"
 * )
 * /**
 * @OA\Tag(
 *     name="Jenis Barang",
 *     description="API Endpoints untuk Authentication"
 * )
 */

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Authentication"},
     *     summary="Login user dengan username dan password",
     *     description="Melakukan autentikasi user dan mengembalikan plain token dari Laravel Sanctum",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", example="3578142602980002", description="Username user"),
     *             @OA\Property(property="password", type="string", format="password", example="3578142602980002", description="Password user")
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
     *                     @OA\Property(property="email", type="string", example="john@example.com")
     *                 ),
     *                 @OA\Property(property="token", type="string", example="1|abcdefghijklmnopqrstuvwxyz123456789"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
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
     *     )
     * )
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                // 'password' => 'required|string'
            ]);

            // Cari user berdasarkan username
            $user = User::where('username', $request->username)->first();

            // // Cek apakah user ditemukan dan password cocok
            // if (!$user || !Hash::check($request->password, $user->password)) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Kredensial yang diberikan tidak valid',
            //     ], 401);
            // }

            // Generate token baru dengan Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('User berhasil login', [
                'user_id' => $user->id,
                'username' => $user->username,
                'timestamp' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->full_name,
                        'email' => $user->email,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
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
                'username' => $request->username ?? 'unknown'
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Authentication"},
     *     summary="Logout user",
     *     description="Menghapus token aktif user dan melakukan logout",
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

        $user->currentAccessToken()->delete();

        Log::info('User logout', [
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
}