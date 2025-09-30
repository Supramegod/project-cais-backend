<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BarangDefaultQty extends Model
{
    use SoftDeletes;

    protected $table = 'm_barang_default_qty';

    protected $fillable = [
        'barang_id',
        'layanan_id',
        'layanan',
        'qty_default',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at'
    ];

    protected $casts = [
        'qty_default' => 'integer',
        'barang_id' => 'integer',
        'layanan_id' => 'integer',
    ];

    /**
     * Relasi ke Barang
     */
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    /**
     * Relasi ke Kebutuhan/Layanan
     */
    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class, 'layanan_id');
    }

    /**
     * Scope untuk filter berdasarkan barang
     */
    public function scopeByBarang($query, $barangId)
    {
        return $query->where('barang_id', $barangId);
    }

    /**
     * Scope untuk filter berdasarkan layanan
     */
    public function scopeByLayanan($query, $layananId)
    {
        return $query->where('layanan_id', $layananId);
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