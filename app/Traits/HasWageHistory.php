<?php
// app/Traits/HasWageHistory.php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait HasWageHistory
{
    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    // --- Static helpers ---

    /**
     * Non-aktifkan semua record aktif untuk foreign key tertentu.
     * Dipanggil di dalam DB::transaction oleh service layer.
     */
    public static function deactivatePrevious(
        string $foreignKey,
        int    $foreignId,
        string $updatedBy = 'System',
    ): void {
        static::where($foreignKey, $foreignId)
            ->where('is_aktif', true)
            ->update([
                'is_aktif'   => false,
                'updated_by' => $updatedBy,
            ]);
    }

    // --- Accessors ---

    public function getTglBerlakuAttribute(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    // --- Helpers ---

    /**
     * Strip format Rupiah sebelum disimpan ke DB.
     * Menerima: "Rp. 3.000.000,00" atau "3000000"
     */
    protected function parseCurrencyInput(mixed $value): float
    {
        if (is_string($value)) {
            $value = preg_replace('/[Rr][Pp]\.?\s*/', '', $value);
            $value = str_replace(['.', ','], ['', '.'], trim($value));
        }

        return (float) $value;
    }

    /**
     * Format nilai upah sebagai "Rp 3.000.000".
     * Panggil dengan nama field: $this->formatWage('ump')
     */
    public function formatWage(string $field): string
    {
        $value = (float) ($this->attributes[$field] ?? 0);

        return 'Rp ' . number_format($value, 0, ',', '.');
    }
}