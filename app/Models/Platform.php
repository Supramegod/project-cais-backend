<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Platform extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_platform';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nama',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // Relasi ke leads
    public function leads()
    {
        return $this->hasMany(Leads::class, 'platform_id', 'id');
    }
}