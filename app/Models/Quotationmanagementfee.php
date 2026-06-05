<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Konfigurasi komponen management fee per quotation.
 *
 * Setiap record mendefinisikan komponen biaya mana saja yang ikut dihitung
 * sebagai basis Management Fee. Gaji Pokok selalu masuk (tidak ada flag).
 *
 * @property bool $is_thr
 * @property bool $is_kompensasi
 * @property bool $is_thl
 * @property bool $is_lembur
 * @property bool $is_bpjs_kes
 * @property bool $is_bpjs_tk
 * @property bool $is_chemical
 * @property bool $is_kaporlap
 * @property bool $is_device
 * @property bool $is_ohc
 */
class QuotationManagementFee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_management_fee';

    protected $fillable = [
        'quotation_id',
        'is_thr',
        'is_kompensasi',
        'is_thl',
        'is_lembur',
        'is_bpjs_kes',
        'is_bpjs_tk',
        'is_chemical',
        'is_kaporlap',
        'is_device',
        'is_ohc',
    ];

    protected $casts = [
        'is_thr'        => 'boolean',
        'is_kompensasi' => 'boolean',
        'is_thl'        => 'boolean',
        'is_lembur'     => 'boolean',
        'is_bpjs_kes'   => 'boolean',
        'is_bpjs_tk'    => 'boolean',
        'is_chemical'   => 'boolean',
        'is_kaporlap'   => 'boolean',
        'is_device'     => 'boolean',
        'is_ohc'        => 'boolean',
    ];

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Nama-nama semua flag komponen (tanpa gaji pokok yang selalu true).
     */
    public static function componentFlags(): array
    {
        return [
            'is_thr',
            'is_kompensasi',
            'is_thl',
            'is_lembur',
            'is_bpjs_kes',
            'is_bpjs_tk',
            'is_chemical',
            'is_kaporlap',
            'is_device',
            'is_ohc',
        ];
    }

    /**
     * Konfigurasi default: semua komponen aktif.
     * Dipakai sebagai fallback untuk quotation lama yang belum punya record.
     */
    public static function defaultConfig(): array
    {
        return array_fill_keys(static::componentFlags(), true);
    }

    /**
     * Upsert konfigurasi untuk satu quotation.
     * Aman digunakan meski record sudah soft-deleted (akan di-restore otomatis).
     *
     * @param int   $quotationId
     * @param array $flags  [ 'is_thr' => true, 'is_lembur' => false, … ]
     */
    public static function upsertForQuotation(int $quotationId, array $flags): void
    {
        // Sanitasi: hanya proses flag yang dikenal, casting ke boolean
        $allowedFlags = static::componentFlags();
        $sanitized    = [];
        foreach ($allowedFlags as $flag) {
            $sanitized[$flag] = isset($flags[$flag]) ? (bool) $flags[$flag] : true;
        }

        // Gunakan DB::table agar bisa mengabaikan soft-delete pada lookup
        // dan tidak terkena duplikat unique constraint
        DB::table('sl_quotation_management_fee')->upsert(
            [array_merge(
                ['quotation_id' => $quotationId],
                $sanitized,
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,   // restore jika sebelumnya ter-soft-delete
                ]
            )],
            uniqueBy: ['quotation_id'],
            update: array_merge($allowedFlags, ['updated_at', 'deleted_at'])
        );
    }

    public static function resolveForQuotation(int $quotationId): self
    {
        return static::where('quotation_id', $quotationId)->first()
            ?? new static(static::defaultConfig());
    }
    // ── Relasi ────────────────────────────────────────────────────────────────

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }
}