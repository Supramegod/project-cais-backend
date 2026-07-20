<?php

// app/Models/City.php

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
    protected $fillable = ['province_id', 'name', 'kode', 'branch_id','is_active'];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function umks(): HasMany
    {
        return $this->hasMany(Umk::class, 'city_id');
    }

    /**
     * UMK aktif terbaru — latestOfMany menghindari N+1.
     */
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

    /**
     * Semua UMSK aktif (satu per sektor) — eager-loadable tanpa N+1.
     * HasMany karena satu kota bisa punya banyak sektor aktif sekaligus.
     */
    public function activeUmsks(): HasMany
    {
        return $this->hasMany(Umsk::class, 'city_id')
            ->where('is_aktif', true)
            ->orderBy('sektor');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByProvince(Builder $query, int $provinceId): Builder
    {
        return $query->where('province_id', $provinceId);
    }
}