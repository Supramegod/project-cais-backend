<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;

class HrisPersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'personal_access_tokens';
    protected $connection = 'mysql';

    // Token expires dalam 2 jam
    protected $expirationTime = 2;


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

    public function isValid()
    {
        return !$this->expires_at || Carbon::now()->lt($this->expires_at);
    }

    public function isExpired()
    {
        return !$this->isValid();
    }
}