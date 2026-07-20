<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fulfillment_id
 * @property string $aksi
 * @property int $qty_sesi_ini
 * @property int $remaining_sebelum
 * @property int $remaining_sesudah
 * @property string|null $catatan
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read PksItemFulfillment $fulfillment
 */
class PksItemFulfillmentLog extends Model
{
    // Append-only, immutable — no SoftDeletes, no updated_at

    const UPDATED_AT = null;

    protected $table = 'sl_pks_item_fulfillment_log';

    protected $fillable = [
        'fulfillment_id',
        'aksi',
        'qty_sesi_ini',
        'remaining_sebelum',
        'remaining_sesudah',
        'catatan',
        'created_by',
        'created_by_user_id',
    ];

    protected $guarded = ['id'];

    // ─── Relationships ────────────────────────────────────────────

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(PksItemFulfillment::class, 'fulfillment_id');
    }
}
