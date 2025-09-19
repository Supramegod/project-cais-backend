<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class User extends Authenticatable
{
    protected $table = 'm_user';
    protected $primaryKey = 'id';
     protected $connection= 'mysqlhris';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens,HasUuids;
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
}
