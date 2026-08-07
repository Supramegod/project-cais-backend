<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $pks_id
 * @property int $site_id
 * @property int $leads_id
 * @property string $item_type
 * @property int $item_id
 * @property int $qty_diminta
 * @property int $qty_request
 * @property int $qty_terpenuhi
 * @property string $status
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read int $remaining
 * @property-read int $boleh_direquest
 * @property-read Pks $pks
 * @property-read Site $site
 * @property-read Leads $leads
 * @property-read \Illuminate\Database\Eloquent\Collection|PksFulfillmentLog[] $logs
 * @property-read \Illuminate\Database\Eloquent\Collection|PksItemRequest[] $requests
 */
class PksItemFulfillment extends Model
{
    use SoftDeletes;

    /** Sudah dikirim, belum dikonfirmasi diterima site. */
    const STATUS_REQUESTED = 'requested';

    const STATUS_NOT_YET = 'not_yet_fulfilled';

    const STATUS_PARTIAL = 'partially_fulfilled';

    const STATUS_FULL = 'fully_fulfilled';

    protected $table = 'sl_pks_item_fulfillment';

    protected $fillable = [
        'pks_id',
        'site_id',
        'leads_id',
        'item_type',
        'item_id',
        'qty_diminta',
        'qty_request',
        'qty_terpenuhi',
        'status',
        'created_by',
        'created_by_user_id',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = ['remaining', 'boleh_direquest'];

    /**
     * Sisa quantity yang belum diterima site.
     */
    public function getRemainingAttribute(): int
    {
        return $this->qty_diminta - $this->qty_terpenuhi;
    }

    /**
     * Sisa yang boleh di-request. Beda dengan remaining: barang yang sudah
     * dikirim tapi belum diterima (qty_request) tidak boleh dikirim dua kali.
     */
    public function getBolehDirequestAttribute(): int
    {
        return max(0, $this->qty_diminta - $this->qty_terpenuhi - (int) $this->qty_request);
    }

    /**
     * Status baris dihitung dari angka, bukan disetel manual, supaya request dan
     * penerimaan tidak pernah menghasilkan status yang saling bertentangan.
     */
    public function resolveStatus(): string
    {
        if ($this->qty_terpenuhi > 0 && $this->qty_terpenuhi >= $this->qty_diminta) {
            return self::STATUS_FULL;
        }

        if ($this->qty_terpenuhi > 0) {
            return self::STATUS_PARTIAL;
        }

        return (int) $this->qty_request > 0 ? self::STATUS_REQUESTED : self::STATUS_NOT_YET;
    }

    // ─── Relationships ────────────────────────────────────────────

    public function pks(): BelongsTo
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function leads(): BelongsTo
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function logs(): HasMany
    {
        // Log modul fulfillment jenis item: match reference_id = id.
        return $this->hasMany(PksFulfillmentLog::class, 'reference_id')
            ->where('jenis', PksFulfillmentLog::JENIS_ITEM);
    }

    /**
     * Baris permintaan barang per batch — sumber angka "sedang di jalan".
     */
    public function requests(): HasMany
    {
        return $this->hasMany(PksItemRequest::class, 'fulfillment_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeByPks($query, int $pksId)
    {
        return $query->where('pks_id', $pksId);
    }

    public function scopeBySite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeNotYetFulfilled($query)
    {
        return $query->where('status', self::STATUS_NOT_YET);
    }

    public function scopePartiallyFulfilled($query)
    {
        return $query->where('status', self::STATUS_PARTIAL);
    }

    public function scopeFullyFulfilled($query)
    {
        return $query->where('status', self::STATUS_FULL);
    }

    /**
     * Sudah dikirim, menunggu konfirmasi penerimaan.
     */
    public function scopeRequested($query)
    {
        return $query->where('status', self::STATUS_REQUESTED);
    }
}
