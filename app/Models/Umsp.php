<?php

// app/Models/Umsp.php

namespace App\Models;

use App\Traits\HasWageHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Upah Minimum Sektoral Provinsi (UMSP).
 * Satu record per (province_id + sektor + periode).
 * Deaktivasi dilakukan per-sektor, bukan per-provinsi.
 */
class Umsp extends Model
{
    use HasFactory, SoftDeletes, HasWageHistory;

    protected $connection = 'mysql';
    protected $table      = 'm_umsp';

    protected $fillable = [
        'province_id',
        'province_name',
        'sektor',
        'umsp',
        'tgl_berlaku',
        'sumber',
        'is_aktif',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['deleted_at'];

    protected $casts = [
        'umsp'       => 'decimal:2',
        'tgl_berlaku'=> 'date:Y-m-d',
        'is_aktif'   => 'boolean',
    ];

    // ── Mutators ──────────────────────────────────────────────────────────────

    public function setUmspAttribute(mixed $value): void
    {
        $this->attributes['umsp'] = $this->parseCurrencyInput($value);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    public function scopeByProvince(Builder $query, int $provinceId): Builder
    {
        return $query->where('province_id', $provinceId);
    }

    public function scopeBySektor(Builder $query, string $sektor): Builder
    {
        return $query->where('sektor', $sektor);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Deaktivasi record aktif per (province_id + sektor).
     * Dipanggil sebelum insert record baru agar hanya ada satu aktif per sektor.
     */
    public static function deactivatePreviousBySector(
        int    $provinceId,
        string $sektor,
        string $updatedBy = 'System',
    ): void {
        static::where('province_id', $provinceId)
            ->where('sektor', $sektor)
            ->where('is_aktif', true)
            ->update([
                'is_aktif'   => false,
                'updated_by' => $updatedBy,
            ]);
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    public function formatumsp(): string
    {
        return $this->formatWage('umsp');
    }
}