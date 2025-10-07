<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_supplier';
    protected $fillable = [
        'nama_supplier',
        'alamat',
        'kontak',
        'pic',
        'npwp',
        'kategori_barang',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_by',
        
    ];

    protected $hidden = [ 'updated_at', 'deleted_at'];

    /**
     * Scope untuk data aktif (tidak terhapus)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Get all suppliers dengan kolom tertentu
     */
    public static function getAllSuppliers()
    {
        return self::select(
            'id',
            'nama_supplier',
            'alamat',
            'kontak',
            'pic',
            'npwp',
            'kategori_barang',
            'created_at',
            'created_by'
        )->active()->get();
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