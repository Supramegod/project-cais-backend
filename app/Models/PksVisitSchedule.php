<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $pks_id
 * @property int $site_id
 * @property int $leads_id
 * @property string $role
 * @property int $pic_user_id
 * @property \Illuminate\Support\Carbon|null $tgl_jadwal
 * @property \Illuminate\Support\Carbon|null $tgl_jadwal_asli
 * @property string|null $alasan_reschedule
 * @property int|null $direschedule_oleh
 * @property string $status
 * @property string|null $created_by
 * @property int|null $created_by_user_id
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Pks $pks
 * @property-read Site $site
 * @property-read Leads $leads
 * @property-read User $picUser
 * @property-read User $direscheduleOleh
 * @property-read PksVisitRecord|null $visitRecord
 */
class PksVisitSchedule extends Model
{
    protected $table = 'sl_pks_visit_schedule';

    protected $fillable = [
        'pks_id',
        'site_id',
        'leads_id',
        'role',
        'pic_user_id',
        'tgl_jadwal',
        'tgl_jadwal_asli',
        'alasan_reschedule',
        'direschedule_oleh',
        'status',
        'created_by',
        'created_by_user_id',
        'updated_by',
    ];

    protected $casts = [
        'tgl_jadwal' => 'date',
        'tgl_jadwal_asli' => 'date',
    ];

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

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function direscheduleOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direschedule_oleh');
    }

    public function visitRecord(): HasOne
    {
        return $this->hasOne(PksVisitRecord::class, 'schedule_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeByPks($query, int $pksId)
    {
        return $query->where('pks_id', $pksId);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByPic($query, int $userId)
    {
        return $query->where('pic_user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'rescheduled']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('tgl_jadwal', '<', now()->toDateString())
            ->whereNotIn('status', ['done', 'missed']);
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('tgl_jadwal', now()->toDateString());
    }

    public function scopeNearEnd($query)
    {
        return $query->join('sl_pks', 'sl_pks.id', '=', 'sl_pks_visit_schedule.pks_id')
            ->orderBy('sl_pks.kontrak_akhir', 'asc')
            ->select('sl_pks_visit_schedule.*');
    }
}
