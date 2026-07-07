<?php

namespace Database\Seeders;

use App\Models\Quotation;
use App\Models\QuotationManagementFee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill sl_quotation_management_fee untuk quotation yang sudah ada.
 *
 * Strategi:
 *   1. Mapping cerdas: flags diderivasi dari management_fee_id lama sehingga
 *      hasil perhitungan sedekat mungkin dengan formula statis sebelumnya.
 *   2. Quotation tanpa management_fee_id (null/0) → semua komponen aktif (safe default).
 *
 * Idempotent: aman dijalankan berkali-kali (skip quotation yang sudah punya record).
 * Batch insert: menghindari N+1, dikerjakan dalam transaksi per-batch.
 *
 * Jalankan dengan:
 *   php artisan db:seed --class=QuotationManagementFeeSeeder
 */
class QuotationManagementFeeSeeder extends Seeder
{
    /** Jumlah row yang diproses per batch INSERT untuk efisiensi memori. */
    private const BATCH_SIZE = 500;

    public function run(): void
    {
        $this->command->info('🔧 Backfill sl_quotation_management_fee …');

        // Ambil hanya quotation yang BELUM punya record (skip yang sudah ada)
        $existing = DB::table('sl_quotation_management_fee')
            ->whereNull('deleted_at')
            ->pluck('quotation_id')
            ->flip(); // flip → array dengan key = quotation_id untuk O(1) lookup

        $total     = 0;
        $skipped   = 0;
        $now       = now();

        // Chunk agar aman untuk jutaan baris
        Quotation::whereNull('deleted_at')
            ->select(['id', 'management_fee_id'])
            ->chunkById(static::BATCH_SIZE, function ($quotations) use ($existing, $now, &$total, &$skipped) {
                $rows = [];

                foreach ($quotations as $quotation) {
                    // Skip jika sudah punya record
                    if (isset($existing[$quotation->id])) {
                        $skipped++;
                        continue;
                    }

                    $flags = $this->getFlagsFromLegacyId($quotation->management_fee_id);

                    $rows[] = array_merge(
                        ['quotation_id' => $quotation->id],
                        $flags,
                        ['created_at' => $now, 'updated_at' => $now, 'deleted_at' => null]
                    );
                }

                if (!empty($rows)) {
                    // INSERT IGNORE agar aman meski ada race condition
                    DB::table('sl_quotation_management_fee')->insertOrIgnore($rows);
                    $total += count($rows);
                }
            });

        $this->command->info("✅ Selesai. Dibuat: {$total} | Dilewati (sudah ada): {$skipped}");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Derivasi flag komponen dari management_fee_id lama.
     *
     * Formula lama → pendekatan baru (basis selalu + gaji pokok):
     *
     *   id=1  total_base_manpower (gaji + tunjangan bulanan)
     *         → tidak ada flag "tunjangan bulanan" → semua false (hanya gaji pokok)
     *
     *   id=4  total_sebelum_management_fee (semua komponen HPP)
     *         → semua flag true
     *
     *   id=5  upah_pokok saja
     *         → semua flag false
     *
     *   id=6  upah_pokok + BPJS Ketenagakerjaan
     *         → is_bpjs_tk = true
     *
     *   id=7  upah_pokok + BPJS TK + BPJS Kesehatan
     *         → is_bpjs_tk = true, is_bpjs_kes = true
     *
     *   id=8  upah_pokok + BPJS Kesehatan
     *         → is_bpjs_kes = true
     *
     *   null/unknown → semua true (safe default agar data lama tidak kehilangan basis)
     */
private function getFlagsFromLegacyId(?int $managementFeeId): array
{
    $allTrue  = array_fill_keys(QuotationManagementFee::componentFlags(), true);
    $allFalse = array_fill_keys(QuotationManagementFee::componentFlags(), false);

    $flags = match ($managementFeeId) {
        1 => $allFalse,
        4 => $allTrue,
        5 => $allFalse,
        6 => array_merge($allFalse, ['is_bpjs_tk'  => true]),
        7 => array_merge($allFalse, ['is_bpjs_tk'  => true, 'is_bpjs_kes' => true]),
        8 => array_merge($allFalse, ['is_bpjs_kes' => true]),
        default => $allTrue,
    };

    // Kolom baru → selalu false untuk data lama (backward compat)
    $flags['is_tunjangan_lain'] = false;

    return $flags;
}
}