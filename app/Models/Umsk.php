<?php
// app/Models/Umsk.php

namespace App\Models;

use App\Traits\HasWageHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Umsk extends Model
{
    use HasFactory, SoftDeletes, HasWageHistory;

    protected $connection = 'mysql';
    protected $table = 'm_umsk';
    protected $fillable = [
        'city_id',
        'city_name',
        'umsk',
        'tgl_berlaku',
        'sumber',
        'is_aktif',
        'created_by',
        'updated_by',
    ];
    protected $hidden = ['deleted_at'];
    protected $casts = [
        'umsk' => 'decimal:2',
        'tgl_berlaku' => 'date:Y-m-d',
        'is_aktif' => 'boolean',
    ];

    public function setUmskAttribute(mixed $value): void
    {
        $this->attributes['umsk'] = $this->parseCurrencyInput($value);
    }

    public function scopeByCity(Builder $query, int $cityId): Builder
    {
        return $query->where('city_id', $cityId);
    }

    /**
     * Override trait: deaktivasi per (city_id + sektor) bukan hanya city_id.
     */
    public static function deactivatePreviousBySector(
        int $cityId,
        string $sektor,
        string $updatedBy = 'System',
    ): void {
        static::where('city_id', $cityId)
            ->where('is_aktif', true)
            ->update(['is_aktif' => false, 'updated_by' => $updatedBy]);
    }

    public function formatumsk(): string
    {
        return $this->formatWage('umsk');
    }
}