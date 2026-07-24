<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $pks_id
 * @property int $site_id
 * @property int $leads_id
 * @property string $item_type
 * @property int $item_id
 * @property int $qty_diminta
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
 * @property-read Pks $pks
 * @property-read Site $site
 * @property-read Leads $leads
 * @property-read \Illuminate\Database\Eloquent\Collection|PksFulfillmentLog[] $logs
 */
class PksItemFulfillment extends Model
{
    use SoftDeletes;

    protected $table = 'sl_pks_item_fulfillment';

    protected $fillable = [
        'pks_id',
        'site_id',
        'leads_id',
        'item_type',
        'item_id',
        'qty_diminta',
        'qty_terpenuhi',
        'status',
        'created_by',
        'created_by_user_id',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = ['remaining'];

    /**
     * Sisa quantity yang belum terpenuhi.
     */
    public function getRemainingAttribute(): int
    {
        return $this->qty_diminta - $this->qty_terpenuhi;
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
        return $query->where('status', 'not_yet_fulfilled');
    }

    public function scopePartiallyFulfilled($query)
    {
        return $query->where('status', 'partially_fulfilled');
    }

    public function scopeFullyFulfilled($query)
    {
        return $query->where('status', 'fully_fulfilled');
    }
}
