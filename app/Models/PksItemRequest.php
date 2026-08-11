<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan barang per item per batch — tahap pertama alur dua tahap.
 *
 * Satu baris = "batch X mengirim N unit item ini". Baris ditutup saat penerimaan
 * dicatat: STATUS_RECEIVED bila diterima penuh, STATUS_SHORT bila kurang.
 * Baris yang sudah ditutup tidak pernah dibuka lagi — kekurangannya kembali
 * menjadi sisa yang boleh di-request ulang lewat batch baru.
 *
 * Satu baris menyentuh DUA batch, dan keduanya harus dibedakan: batch_id adalah
 * batch pengiriman (diisi saat baris dibuat), received_batch_id adalah batch
 * penerimaan yang menutupnya. Tanpa pemisahan ini, baris berstatus 'received'
 * terbaca seolah miliknya batch request — itu sumber kebingungan "sudah diterima
 * tapi aksinya masih request".
 *
 * @property int $id
 * @property int $pks_id
 * @property int $site_id
 * @property int $fulfillment_id
 * @property string $batch_id batch PENGIRIMAN — diisi saat baris dibuat
 * @property int|null $batch_ke
 * @property string|null $received_batch_id batch PENERIMAAN yang menutup baris ini
 * @property int|null $received_batch_ke
 * @property string $item_type
 * @property int $item_id
 * @property int $qty_request
 * @property int $qty_diterima
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read int $kurang
 * @property-read PksItemFulfillment $fulfillment
 */
class PksItemRequest extends Model
{
    /** Belum dikonfirmasi diterima. */
    const STATUS_OPEN = 'open';

    /** Diterima persis sebanyak yang dikirim. */
    const STATUS_RECEIVED = 'received';

    /** Diterima kurang dari yang dikirim. */
    const STATUS_SHORT = 'short';

    protected $table = 'sl_pks_item_request';

    protected $fillable = [
        'pks_id',
        'site_id',
        'fulfillment_id',
        'batch_id',
        'batch_ke',
        'received_batch_id',
        'received_batch_ke',
        'item_type',
        'item_id',
        'qty_request',
        'qty_diterima',
        'status',
        'received_at',
        'created_by',
        'created_by_user_id',
        'updated_by',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    protected $appends = ['kurang'];

    /**
     * Selisih yang tidak jadi diterima. Selalu 0 selama baris masih open.
     */
    public function getKurangAttribute(): int
    {
        if ($this->status === self::STATUS_OPEN) {
            return 0;
        }

        return max(0, (int) $this->qty_request - (int) $this->qty_diterima);
    }

    // ─── Relationships ────────────────────────────────────────────

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(PksItemFulfillment::class, 'fulfillment_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    /**
     * Baris yang masih menunggu penerimaan.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForFulfillment($query, int $fulfillmentId)
    {
        return $query->where('fulfillment_id', $fulfillmentId);
    }

    public function scopeForBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeForPks($query, int $pksId)
    {
        return $query->where('pks_id', $pksId);
    }
}
