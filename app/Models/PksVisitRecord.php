<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $schedule_id
 * @property int $pks_id
 * @property int $site_id
 * @property int $leads_id
 * @property string $role
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $tgl_visit_aktual
 * @property string|null $hasil_visit
 * @property string|null $catatan
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read PksVisitSchedule $schedule
 * @property-read Pks $pks
 * @property-read Site $site
 * @property-read Leads $leads
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|PksVisitRecordFoto[] $fotos
 */
class PksVisitRecord extends Model
{
    use SoftDeletes;

    protected $table = 'sl_pks_visit_record';

    protected $fillable = [
        'schedule_id',
        'pks_id',
        'site_id',
        'leads_id',
        'role',
        'user_id',
        'tgl_visit_aktual',
        'hasil_visit',
        'catatan',
        'created_by',
        'created_by_user_id',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'tgl_visit_aktual' => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PksVisitSchedule::class, 'schedule_id');
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(PksVisitRecordFoto::class, 'visit_record_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeByPks($query, int $pksId)
    {
        return $query->where('pks_id', $pksId);
    }

    public function scopeByLeads($query, int $leadsId)
    {
        return $query->where('leads_id', $leadsId);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
