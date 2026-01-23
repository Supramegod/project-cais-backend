<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Target extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_target';

    protected $fillable = [
        'user_id',
        'cais_role_id',
        'branch_id',
        'tahun', // Tambahkan ini
        'nama',
        'target',
        'created_by',
        'updated_by',
        'deleted_by'
    ];
    // Relasi ke User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi ke Branch
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}