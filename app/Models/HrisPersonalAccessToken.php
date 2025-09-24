<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class HrisPersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Tentukan nama tabel secara manual.
     *
     * @var string
     */
    protected $table = 'personal_access_tokens'; // ✅ Tambahkan baris ini

    /**
     * Tentukan koneksi database yang sama dengan model User.
     *
     * @var string
     */
    protected $connection = 'mysql';
}