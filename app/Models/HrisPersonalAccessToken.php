<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

class HrisPersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'personal_access_tokens';
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'tokenable_id',
        'tokenable_type',
    ];

    // Token expires dalam 2 jam
    protected $expirationTime = 24 * 60; // 24 jam dalam menit

    /**
     * Accessor untuk mengecek apakah token sudah expired
     **   */

    /**
     * Boot method untuk set tokenable_type dan expires_at otomatis
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($token) {
            // Set tokenable_type otomatis jika kosong
            if (empty($token->tokenable_type)) {
                $token->tokenable_type = 'App\\Models\\User';
            }

            // Set expires_at otomatis jika NULL
            if (empty($token->expires_at)) {
                $token->expires_at = Carbon::now()->addHours($token->expirationTime);
            }
        });
    }


    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->expires_at ? Carbon::now()->gt($this->expires_at) : false,
        );
    }

    public function refreshToken()
    {
        return $this->hasOne(RefreshTokens::class, 'access_token_id');
    }

}