<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master matrix acuan visit target per kebutuhan + range HC.
 *
 * @property int $id
 * @property int $kebutuhan_id
 * @property int $hc_min
 * @property int $hc_max
 * @property int $kategori_sesuai_hc_id
 * @property int $target_visit_per_tahun
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Kebutuhan $kebutuhan
 * @property-read KategoriSesuaiHc $kategoriSesuaiHc
 */
class PksVisitTargetMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_pks_visit_target';

    protected $fillable = [
        'kebutuhan_id',
        'hc_min',
        'hc_max',
        'kategori_sesuai_hc_id',
        'target_visit_per_tahun',
        'created_by',
        'created_by_user_id',
        'updated_by',
        'deleted_by',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function kebutuhan(): BelongsTo
    {
        return $this->belongsTo(Kebutuhan::class, 'kebutuhan_id');
    }

    public function kategoriSesuaiHc(): BelongsTo
    {
        return $this->belongsTo(KategoriSesuaiHc::class, 'kategori_sesuai_hc_id');
    }

    // ─── Query helpers ────────────────────────────────────────────

    /**
     * Lookup target visit per tahun berdasarkan kebutuhan + total HC.
     */
    public static function lookupTarget(int $kebutuhanId, int $totalHc): ?self
    {
        return static::where('kebutuhan_id', $kebutuhanId)
            ->where('hc_min', '<=', $totalHc)
            ->where('hc_max', '>=', $totalHc)
            ->first();
    }
}
