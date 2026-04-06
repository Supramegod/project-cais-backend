<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class City extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_city';
    protected $primaryKey = 'id';
    protected $fillable = ['province_id', 'name', 'kode', 'is_active'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function umks(): HasMany
    {
        return $this->hasMany(Umk::class, 'city_id');
    }


    public function activeUmk(): HasOne
    {
        return $this->hasOne(Umk::class, 'city_id')
            ->where('is_aktif', true)
            ->latestOfMany('tgl_berlaku');
    }

    public function umsks(): HasMany
    {
        return $this->hasMany(Umsk::class, 'city_id');
    }

    // City.php
    public function activeUmsks(): HasOne
    {
        return $this->hasOne(Umsk::class, 'city_id')
            ->where('is_aktif', true)
            ->latestOfMany('tgl_berlaku');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvince(Builder $query, int $provinceId): Builder
    {
        return $query->where('province_id', $provinceId);
    }
}