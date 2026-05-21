<?php

// app/Models/Province.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Province extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_province';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'is_active'];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'province_id');
    }

    public function umps(): HasMany
    {
        return $this->hasMany(Ump::class, 'province_id');
    }

    /**
     * UMP aktif terbaru — latestOfMany menghindari N+1.
     */
    public function activeUmp(): HasOne
    {
        return $this->hasOne(Ump::class, 'province_id')
            ->where('is_aktif', true)
            ->latestOfMany('tgl_berlaku');
    }

    public function umsps(): HasMany
    {
        return $this->hasMany(Umsp::class, 'province_id');
    }

    /**
     * Semua UMSP aktif (satu per sektor) — eager-loadable tanpa N+1.
     * Menggunakan HasMany karena satu provinsi bisa punya banyak sektor aktif.
     */
    public function activeUmsps(): HasMany
    {
        return $this->hasMany(Umsp::class, 'province_id')
            ->where('is_aktif', true)
            ->orderBy('sektor');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}