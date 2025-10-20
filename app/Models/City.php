<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_city';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'province_id',
        'nama',
        'kode',
        'is_active'
    ];

    public function province()
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function districts()
    {
        return $this->hasMany(District::class, 'city_id');
    }

    public function leads()
    {
        return $this->hasMany(Leads::class, 'kota_id');
    }

    /**
     * Scope untuk filter berdasarkan province
     */
    public function scopeByProvince($query, $provinceId)
    {
        return $query->where('province_id', $provinceId);
    }
}