<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class UserEmailConfig extends Model
{
    protected $table = 'sl_user_email_configs';

    protected $fillable = [
        'user_id',
        'email_host',
        'email_port',
        'email_username',
        'email_password',
        'email_encryption',
        'email_from_address',
        'email_from_name',
        'is_active'
    ];

    protected $hidden = [
        'email_password'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_port' => 'integer'
    ];

    /**
     * Relationship dengan User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor untuk email_password (decrypt otomatis)
     */
    public function getEmailPasswordAttribute()
    {
        // Jika nilai di database kosong, kembalikan null
        if (empty($this->attributes['email_password'])) {
            return null;
        }
        
        try {
            // Coba decrypt
            return Crypt::decryptString($this->attributes['email_password']);
        } catch (\Exception $e) {
            Log::warning('Failed to decrypt email password for user_id: ' . $this->user_id . '. Error: ' . $e->getMessage());
            // Kembalikan nilai mentah (mungkin sudah plaintext)
            return $this->attributes['email_password'];
        }
    }

    /**
     * Mutator untuk email_password (encrypt otomatis)
     */
    public function setEmailPasswordAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['email_password'] = null;
        } else {
            try {
                // Coba encrypt
                $this->attributes['email_password'] = Crypt::encryptString($value);
            } catch (\Exception $e) {
                Log::error('Failed to encrypt email password for user_id: ' . $this->user_id);
                // Simpan sebagai plaintext jika encrypt gagal
                $this->attributes['email_password'] = $value;
            }
        }
    }

    /**
     * Method untuk mendapatkan password yang sudah di-decrypt
     * (lebih aman daripada langsung mengakses attribute)
     */
    public function getDecryptedPassword(): ?string
    {
        if (empty($this->attributes['email_password'])) {
            return null;
        }
        
        try {
            return Crypt::decryptString($this->attributes['email_password']);
        } catch (\Exception $e) {
            Log::warning('Failed to decrypt password, returning as is');
            return $this->attributes['email_password'];
        }
    }

    /**
     * Cek apakah konfigurasi lengkap
     */
    public function isComplete(): bool
    {
        return !empty($this->email_host) && 
               !empty($this->email_username) && 
               !empty($this->attributes['email_password']) && // Gunakan attributes langsung
               filter_var($this->email_username, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Dapatkan array konfigurasi untuk Laravel Mail
     */
    public function toMailConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->email_host,
            'port' => $this->email_port ?: 587,
            'encryption' => $this->email_encryption ?: 'tls',
            'username' => $this->email_username,
            'password' => $this->getDecryptedPassword(),
            'timeout' => null,
            'auth_mode' => null,
        ];
    }

    /**
     * Dapatkan array untuk "from"
     */
    public function getFromConfig(): array
    {
        return [
            'address' => $this->email_from_address ?? $this->email_username,
            'name' => $this->email_from_name ?? $this->user->full_name ?? $this->user->name,
        ];
    }
}