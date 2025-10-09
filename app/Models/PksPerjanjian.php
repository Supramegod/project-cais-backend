<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PksPerjanjian extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_pks_perjanjian';
    protected $fillable = [
        'pks_id',
        'pasal',
        'judul', 
        'raw_text',
        'created_by',
        'updated_by'
    ];
    protected $dates = ['deleted_at'];

    // Relasi ke Pks
    public function pks()
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }
}