<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SalesTarget extends Model
{
    protected $table = 'sl_sales_targets';

    protected $fillable = [
        'user_id',
        'branch_id',
        'type',
        'period_type',
        'year',
        'month',
        'target_amount',
    ];

    protected $casts = [
        'year'          => 'integer',
        'month'         => 'integer',
        'target_amount' => 'float',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function scopeForMonth(Builder $query, int $month): Builder
    {
        return $query->where('month', $month);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOfPeriod(Builder $query, string $periodType): Builder
    {
        return $query->where('period_type', $periodType);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Bangun cache-key yang konsisten untuk keying di-memory.
     * Format: "{type}|{user_id}|{branch_id}|{year}|{month}"
     */
    public function getCacheKey(): string
    {
        return implode('|', [
            $this->type,
            $this->user_id  ?? 'null',
            $this->branch_id ?? 'null',
            $this->year,
            $this->month    ?? 'null',
        ]);
    }

    /**
     * Buat cache-key statis (untuk pencarian cepat tanpa instance).
     */
    public static function buildKey(
        string $type,
        mixed $userId,
        mixed $branchId,
        int $year,
        ?int $month
    ): string {
        return implode('|', [
            $type,
            $userId   ?? 'null',
            $branchId ?? 'null',
            $year,
            $month    ?? 'null',
        ]);
    }
}