<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log aktivitas modul fulfillment, dikelompokkan per PKS (append-only, immutable).
 *
 * Satu tabel untuk seluruh modul fulfillment (item, visit, dst) — dibedakan lewat
 * kolom `jenis`. Tidak global lintas aplikasi.
 *  - pks_id       : PKS pemilik log (kunci pengelompokan)
 *  - jenis        : sub-aktivitas fulfillment, mis. PksFulfillmentLog::JENIS_ITEM
 *  - reference_id : id record di tabel sub-modul (mis. sl_pks_item_fulfillment.id)
 *  - meta         : payload spesifik sub-modul (JSON), mis. item simpan
 *                   {qty_sesi_ini, remaining_sebelum, remaining_sesudah}
 *
 * @property int $id
 * @property int $pks_id
 * @property string $jenis
 * @property int $reference_id
 * @property string $aksi
 * @property string|null $catatan
 * @property array|null $meta
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Pks $pks
 */
class PksFulfillmentLog extends Model
{
    // Append-only, immutable — no SoftDeletes, no updated_at
    const UPDATED_AT = null;

    // Jenis sub-aktivitas fulfillment. Tambah konstanta saat sub-modul lain ikut log.
    const JENIS_ITEM = 'item';

    const JENIS_VISIT = 'visit';

    protected $table = 'sl_pks_fulfillment_log';

    protected $fillable = [
        'pks_id',
        'jenis',
        'reference_id',
        'aksi',
        'catatan',
        'meta',
        'created_by',
        'created_by_user_id',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function pks(): BelongsTo
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    /**
     * Semua log satu PKS (lintas sub-modul fulfillment).
     */
    public function scopeForPks($query, int $pksId)
    {
        return $query->where('pks_id', $pksId);
    }

    /**
     * Log milik satu record sub-modul.
     */
    public function scopeForRef($query, string $jenis, int $referenceId)
    {
        return $query->where('jenis', $jenis)->where('reference_id', $referenceId);
    }
}
