<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'm_role';
    protected $primaryKey = 'id';
    protected $connection = 'mysqlhris';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'Is_active',
    ];

    // Relasi ke user
    public function users()
    {
        return $this->hasMany(User::class, 'role_id', 'id');
    }
    public function scopeActive($query)
{
    return $query->where('is_active', '!=', 0);
}

}
