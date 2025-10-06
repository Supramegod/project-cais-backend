<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Umk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_umk';
    protected $fillable = [
        'city_id',
        'city_name',
        'umk',
        'tgl_berlaku',
        'sumber',
        'is_aktif'
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'umk' => 'decimal:2',
        'tgl_berlaku' => 'date',
        'is_aktif' => 'boolean'
    ];

    /**
     * Format umk sebelum menyimpan
     */
    public function setUmkAttribute($value)
    {
        // Format: Rp. 1.000.000,00 -> 1000000.00
        $value = str_replace(["Rp.", ".", ","], "", $value);
        $this->attributes['umk'] = (float) $value;
    }

    /**
     * Get active UMK data
     */
    public static function getActive()
    {
        return self::where('is_aktif', 1)->get();
    }

    /**
     * Get UMK by city ID
     */
    public static function getByCity($cityId)
    {
        return self::where('city_id', $cityId)
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * Scope untuk data aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_aktif', 1);
    }

    /**
     * Scope untuk city tertentu
     */
    public function scopeByCity($query, $cityId)
    {
        return $query->where('city_id', $cityId);
    }
}