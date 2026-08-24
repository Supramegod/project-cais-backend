<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot target visit per-PKS per-role (tidak dihapus, bukan transactional delete).
 *
 * @property int $id
 * @property int $pks_id
 * @property string $role
 * @property int $kategori_sesuai_hc_id
 * @property int $target_total
 * @property int $target_terpakai
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $sisa_target
 * @property-read Pks $pks
 * @property-read KategoriSesuaiHc $kategoriSesuaiHc
 */
class PksVisitTarget extends Model
{
    // Snapshot data — tidak pakai SoftDeletes

    protected $table = 'sl_pks_visit_target';

    protected $fillable = [
        'pks_id',
        'role',
        'kategori_sesuai_hc_id',
        'target_total',
        'target_terpakai',
        'created_by',
        'created_by_user_id',
        'updated_by',
    ];

    protected $appends = ['sisa_target'];

    /**
     * Sisa target kunjungan yang belum terpakai.
     */
    public function getSisaTargetAttribute(): int
    {
        return $this->target_total - $this->target_terpakai;
    }

    // ─── Relationships ────────────────────────────────────────────

    public function pks(): BelongsTo
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }

    public function kategoriSesuaiHc(): BelongsTo
    {
        return $this->belongsTo(KategoriSesuaiHc::class, 'kategori_sesuai_hc_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
