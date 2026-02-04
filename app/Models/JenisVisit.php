<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisVisit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_jenis_visit';
    
    protected $fillable = [
        'nama',
        'created_by',
        'updated_by',
        'deleted_by'
    ];
    
    protected $dates = ['deleted_at'];
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';
}