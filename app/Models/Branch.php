<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris'; // pakai koneksi DB kedua
    protected $table = 'm_branch';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'is_active',
        'name',
        'description',
        'city_id', // tambahkan ini
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function timSales()
    {
        return $this->hasMany(TimSales::class, 'branch_id', 'id');
    }
    
    public function leads()
    {
        return $this->hasMany(Leads::class, 'branch_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'branch_id');
    }
    
    // Tambahkan relasi ke City
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
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
    
    /**
     * Scope untuk filter berdasarkan province
     */
    public function scopeByProvince($query, $provinceId)
    {
        return $query->whereHas('city', function($q) use ($provinceId) {
            $q->where('province_id', $provinceId);
        });
    }
}