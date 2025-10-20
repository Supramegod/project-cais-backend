<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barang extends Model
{
    use SoftDeletes;

    protected $table = 'm_barang';

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
        'deleted_at'
    ];

    protected $casts = [
        'harga' => 'decimal:2',
        'masa_pakai' => 'integer',
        'jumlah_default' => 'integer',
        'urutan' => 'integer',
        'jenis_barang_id' => 'integer',
    ];

    /**
     * Relasi ke JenisBarang
     */
    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_barang_id');
    }

    /**
     * Relasi ke BarangDefaultQty
     */
    public function defaultQty()
    {
        return $this->hasMany(BarangDefaultQty::class, 'barang_id');
    }

    /**
     * Scope untuk urutkan berdasarkan urutan
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('urutan')->orderBy('nama');
    }

    /**
     * Scope untuk filter berdasarkan jenis barang
     */
    public function scopeByJenis($query, $jenisId)
    {
        return $query->where('jenis_barang_id', $jenisId);
    }

    /**
     * Accessor untuk format harga
     */
    public function getFormattedHargaAttribute()
    {
         $harga = $this->harga ?? 0;
    return number_format($harga, 0, ',', '.');
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