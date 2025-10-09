<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriSesuaiHc extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_kategori_sesuai_hc';
    protected $fillable = ['nama', 'created_by', 'updated_by'];
    protected $dates = ['deleted_at'];

    // Relasi ke Pks
    public function pks()
    {
        return $this->hasMany(Pks::class, 'kategori_sesuai_hc_id');
    }
}