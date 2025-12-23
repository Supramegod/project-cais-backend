<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class User extends Authenticatable
{
    protected $table = 'm_user';
    protected $primaryKey = 'id';
    protected $connection = 'mysqlhris';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasUuids;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'full_name',
        'email',
        'role_id',
        'branch_id',
        'is_active',
        'created_by',
        'updated_by', // jangan lupa kalau mau simpan token plain
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relasi ke role
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    // Relasi ke branch
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    // Relasi ke tim sales detail
    public function timSalesDetails()
    {
        return $this->hasMany(TimSalesDetail::class, 'user_id', 'id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'user_id');
    }
    // app/Models/User.php (tambahkan method)
    public function emailConfig()
    {
        return $this->hasOne(UserEmailConfig::class, 'user_id', 'id');
    }

    public function getActiveEmailConfig()
    {
        return $this->emailConfig()
            ->where('is_active', true)
            ->whereNotNull('email_host')
            ->whereNotNull('email_username')
            ->whereNotNull('email_password')
            ->first();
    }
    // Method untuk membuat token pair dengan Sanctum
    public function createTokenPair($name = 'auth_token', array $abilities = ['*'])
    {
        // Hapus token lama yang expired
        $this->tokens()->where('expires_at', '<', now())->delete();

        // Buat access token dengan Sanctum (2 jam expiry)
        $accessToken = $this->createToken($name, $abilities, now()->addHours(2));

        // Buat refresh token
        $refreshToken = RefreshTokens::create([
            'access_token_id' => $accessToken->accessToken->id,
            'token' => hash('sha256', $plainRefreshToken = \Illuminate\Support\Str::random(40)),
            // 'expires_at' => now()->addDays(7)
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $plainRefreshToken,
            'refresh_token_model' => $refreshToken
        ];
    }

    // Relasi ke refresh tokens melalui access tokens
    public function refreshTokens()
    {
        return $this->hasManyThrough(
            RefreshTokens::class,
            HrisPersonalAccessToken::class,
            'tokenable_id', // Foreign key pada personal_access_tokens
            'access_token_id', // Foreign key pada refresh_tokens
            'id', // Local key pada users
            'id' // Local key pada personal_access_tokens
        );
    }

    public function scopeCheckLogin(Builder $query, $username, $password)
    {
        $hashedPassword = md5('SHELTER-' . $password . '-SHELTER');

        return $query->where('username', $username)
            ->where('password', $hashedPassword);
    }
    /**
     * Get the user's notifications.
     */
    public function notifications()
    {
        return $this->hasMany(LogNotification::class, 'user_id');
    }

    /**
     * Get the user's unread notifications.
     */
    public function unreadNotifications()
    {
        return $this->notifications()->unread(true);
    }

    /**
     * Get the user's read notifications.
     */
    public function readNotifications()
    {
        return $this->notifications()->unread(false);
    }

    /**
     * Get notifications count.
     *
     * @param  bool  $unread
     * @return int
     */
    public function notificationsCount($unread = null)
    {
        $query = $this->notifications();

        if ($unread === true) {
            $query->unread(true);
        } elseif ($unread === false) {
            $query->unread(false);
        }

        return $query->count();
    }
}
