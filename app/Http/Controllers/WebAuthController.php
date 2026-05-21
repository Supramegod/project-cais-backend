<?php

namespace App\Http\Controllers;

use App\Models\RefreshTokens;
use App\Models\User;
use App\Models\HrisPersonalAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebAuthController extends Controller
{
    /**
     * Login web (dari form welcome)
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::checkLogin($request->username, $request->password)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        // Login session web
        Auth::login($user, $request->has('remember'));
        $request->session()->regenerate();

        // Buat dummy access token (khusus untuk web, tidak digunakan untuk auth API)
        // Token ini akan terikat ke user dan tidak expired (atau expired lama)
        // Buat token lewat relasi user
        $dummyToken = $user->tokens()->create([
            'name' => 'web_dummy_token',
            'token' => hash('sha256', $plainDummyToken = Str::random(40)),
            'abilities' => json_encode(['*']),
            'expires_at' => now()->addDays(30),
        ]);

        // Buat refresh token untuk web (disimpan di cookie), terkait dengan dummy token
        $plainRefreshToken = Str::random(64);
        $hashedToken = hash('sha256', $plainRefreshToken);

        RefreshTokens::updateOrCreate(
            [
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'access_token_id' => $dummyToken->id, // isi dengan ID dummy token
            ],
            [
                'token' => $hashedToken,
                'expires_at' => now()->addDays(7),
            ]
        );

        // Kirim refresh token sebagai cookie httpOnly
        $cookie = Cookie::make(
            'web_refresh_token',
            $plainRefreshToken,
            60 * 24 * 7, // 7 hari
            '/',
            null,
            true,  // secure
            true,  // httpOnly
            false,
            'lax'
        );

        return redirect('/api/documentation')->withCookie($cookie);
    }

    /**
     * Logout web
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            // Hapus refresh token web dari database
            RefreshTokens::where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->whereHas('accessToken', function ($query) {
                    $query->where('name', 'web_dummy_token');
                })->delete();

            // Hapus dummy access token milik user ini
            HrisPersonalAccessToken::where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->where('name', 'web_dummy_token')
                ->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookie = Cookie::forget('web_refresh_token');

        return redirect('/')->withCookie($cookie);
    }

    /**
     * Refresh web session menggunakan refresh token cookie
     */
    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie('web_refresh_token');
        if (!$refreshToken) {
            return response()->json(['error' => 'No refresh token'], 401);
        }

        $hashed = hash('sha256', $refreshToken);

        // Cari refresh token yang masih valid dan terkait dengan dummy access token
        $tokenModel = RefreshTokens::where('token', $hashed)
            ->where('expires_at', '>', now())
            ->whereHas('accessToken', function ($query) {
                $query->where('name', 'web_dummy_token');
            })
            ->first();

        if (!$tokenModel) {
            $cookie = Cookie::forget('web_refresh_token');
            return response()->json(['error' => 'Invalid or expired refresh token'], 401)->withCookie($cookie);
        }

        $user = $tokenModel->tokenableUser();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }

        // Login ulang user (perpanjang session)
        Auth::login($user);
        $request->session()->regenerate();

        // Rotasi refresh token: buat token baru, hapus yang lama
        $newPlainToken = Str::random(64);
        $tokenModel->update([
            'token' => hash('sha256', $newPlainToken),
            'expires_at' => now()->addDays(7),
        ]);

        $cookie = Cookie::make(
            'web_refresh_token',
            $newPlainToken,
            60 * 24 * 7,
            '/',
            null,
            true,
            true,
            false,
            'lax'
        );

        return response()->json(['message' => 'Session refreshed'])->withCookie($cookie);
    }
}