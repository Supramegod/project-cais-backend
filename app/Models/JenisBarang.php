<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisBarang extends Model
{
    use SoftDeletes;

    protected $table = 'm_jenis_barang';
    protected $fillable = ['nama', 'created_by', 'updated_by', 'deleted_by'];
}