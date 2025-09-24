<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barang extends Model
{
    use SoftDeletes;

    protected $table = 'm_barang';
    
    // Tambahkan deleted_by, created_by, updated_by ke fillable
    protected $fillable = [
        'nama', 
        'jenis_barang_id', 
        'jenis_barang', 
        'harga', 
        'satuan',
        'masa_pakai', 
        'merk', 
        'jumlah_default', 
        'urutan',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at'  // Tambahkan juga deleted_at jika diperlukan manual update
    ];

    // Tentukan kolom untuk soft delete jika berbeda dari default
    protected $dates = ['deleted_at'];

    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_barang_id');
    }

    public function defaultQty()
    {
        return $this->hasMany(BarangDefaultQty::class, 'barang_id');
    }
     public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

    /**
     * Format updated_at jadi dd-mm-YYYY
     */
    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
}