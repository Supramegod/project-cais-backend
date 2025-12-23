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
        'tokenable_id',      // ✅ Tambah
        'tokenable_type',    // ✅ Tambah
        'token',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    // Refresh token expires dalam 1 hari (24 jam)
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

    /**
     * Relasi ke access token
     */
    public function accessToken()
    {
        return $this->belongsTo(HrisPersonalAccessToken::class, 'access_token_id');
    }

    /**
     * ✅ Relasi polymorphic langsung ke user
     */
    public function tokenable()
    {
        return $this->morphTo();
    }

    /**
     * ✅ Accessor untuk mendapatkan user
     * Prioritas: dari tokenable langsung, fallback ke accessToken
     */
    public function tokenableUser()
    {
        // Coba ambil dari relasi tokenable dulu (lebih cepat)
        if ($this->tokenable) {
            return $this->tokenable;
        }

        // Fallback ke accessToken jika tokenable tidak ada
        return $this->accessToken ? $this->accessToken->tokenable : null;
    }

    /**
     * Check apakah token masih valid
     */
    public function isValid()
    {
        return !$this->expires_at || Carbon::now()->lt($this->expires_at);
    }

    /**
     * Check apakah token sudah expired
     */
    public function isExpired()
    {
        return !$this->isValid();
    }
}