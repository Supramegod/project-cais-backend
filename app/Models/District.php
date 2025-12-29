<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_district';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'city_id',
        'nama',
        'kode',
        'is_active'
    ];

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function villages()
    {
        return $this->hasMany(Village::class, 'district_id');
    }

    public function leads()
    {
        return $this->hasMany(Leads::class, 'kecamatan_id');
    }
       /**
     * Scope untuk filter berdasarkan city
     */
    public function scopeByCity($query, $cityId)
    {
        return $query->where('city_id', $cityId);
    }
}