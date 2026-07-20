<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $visit_record_id
 * @property string $url_file
 * @property string $nama_file
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read PksVisitRecord $visitRecord
 */
class PksVisitRecordFoto extends Model
{
    // Immutable — no SoftDeletes, no updated_at

    const UPDATED_AT = null;

    protected $table = 'sl_pks_visit_record_foto';

    protected $fillable = [
        'visit_record_id',
        'url_file',
        'nama_file',
        'created_by',
        'created_by_user_id',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function visitRecord(): BelongsTo
    {
        return $this->belongsTo(PksVisitRecord::class, 'visit_record_id');
    }
}
