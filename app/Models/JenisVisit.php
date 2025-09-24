<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisVisit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_jenis_visit';
    protected $fillable = ['nama'];
    protected $dates = ['deleted_at'];
}