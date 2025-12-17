<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RefreshTokens extends Model
{
    use HasFactory;

    protected $table = 'refresh_tokens';
    protected $connection = 'mysql';

    protected $fillable = [
        'access_token_id',
        'token',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    // Refresh token expires dalam 7 hari
    protected $refreshTokenExpiration = 1 * 24;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($token) {
            if (empty($token->expires_at)) {
                $token->expires_at = Carbon::now()->addHours($token->refreshTokenExpiration);
            }
        });
    }

    public function accessToken()
    {
        return $this->belongsTo(HrisPersonalAccessToken::class, 'access_token_id');
    }

    // ✅ accessor pengganti user()
    public function tokenableUser()
    {
        return $this->accessToken ? $this->accessToken->tokenable : null;
    }

    public function isValid()
    {
        return !$this->expires_at || Carbon::now()->lt($this->expires_at);
    }

    public function isExpired()
    {
        return !$this->isValid();
    }
}
