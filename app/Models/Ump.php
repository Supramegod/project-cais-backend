<?php

namespace App\Models;

use App\Traits\HasWageHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ump extends Model
{
    use HasFactory, SoftDeletes, HasWageHistory;

    protected $connection = 'mysql';
    protected $table = 'm_ump';
    protected $fillable = [
        'province_id',
        'province_name',
        'ump',
        'tgl_berlaku',
        'sumber',
        'is_aktif',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'ump' => 'decimal:2',
        'tgl_berlaku' => 'date',
        'is_aktif' => 'boolean'
    ];

    /**
     * Format ump sebelum menyimpan
     */
    public function setUmpAttribute(mixed $value): void
    {
        $this->attributes['ump'] = $this->parseCurrencyInput($value);
    }

    /**
     * Get active UMP data
     */
    public static function getActive()
    {
        return self::where('is_aktif', 1)->get();
    }

    /**
     * Get UMP by province ID
     */
    public static function getByProvince($provinceId)
    {
        return self::where('province_id', $provinceId)
            ->whereNull('deleted_at')
            ->get();
    }
    public function scopeByProvince(Builder $query, int $provinceId): Builder
    {
        return $query->where('province_id', $provinceId);
    }
    /**
     * Scope untuk data aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_aktif', 1);
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
    public function getTglBerlakuAttribute($value)
    {
        return $value
            ? Carbon::parse($value)->format('d-m-Y')
            : null;
    }
    public function formatump(): string
    {
        return $this->formatWage('ump');
    }
}