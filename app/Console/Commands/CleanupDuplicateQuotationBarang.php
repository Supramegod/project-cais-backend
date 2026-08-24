<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan baris barang quotation yang telanjur dobel.
 *
 * Duplikat terbentuk dari dua bug yang sudah diperbaiki:
 * - Step6Service menghapus jenis_barang_id 17 padahal meng-insert 8, sehingga tiap
 *   simpan step 6 menambah satu set device aplikasi pendukung.
 * - Listener duplikasi quotation bisa jalan dua kali saat job di-retry.
 *
 * Command ini menyisakan baris ber-id terkecil per kombinasi kunci dan
 * soft delete sisanya. Data tidak dihapus permanen.
 */
class CleanupDuplicateQuotationBarang extends Command
{
    protected $signature = 'quotation:cleanup-duplicate-barang
                            {--quotation= : Batasi ke satu quotation_id}
                            {--dry-run : Hanya tampilkan jumlah, tanpa mengubah data}';

    protected $description = 'Soft delete baris barang quotation (devices/chemical/ohc/kaporlap) yang duplikat';

    /** Tabel → kolom pembeda selain quotation_id + barang_id + jenis_barang_id. */
    private const TABLES = [
        'sl_quotation_devices' => 'quotation_site_id',
        'sl_quotation_chemical' => 'quotation_site_id',
        'sl_quotation_ohc' => 'quotation_site_id',
        'sl_quotation_kaporlap' => 'quotation_detail_id',
    ];

    public function handle(): int
    {
        $quotationId = $this->option('quotation');
        $dryRun = (bool) $this->option('dry-run');
        $totalDeleted = 0;

        foreach (self::TABLES as $table => $scopeColumn) {
            $duplicateIds = $this->findDuplicateIds($table, $scopeColumn, $quotationId);
            $count = count($duplicateIds);
            $totalDeleted += $count;

            if ($count === 0) {
                $this->info("{$table}: bersih.");
                continue;
            }

            if ($dryRun) {
                $this->warn("{$table}: {$count} baris duplikat (dry-run, tidak diubah).");
                continue;
            }

            foreach (array_chunk($duplicateIds, 1000) as $chunk) {
                DB::table($table)->whereIn('id', $chunk)->update([
                    'deleted_at' => now(),
                    'deleted_by' => 'system:cleanup',
                ]);
            }

            $this->warn("{$table}: {$count} baris duplikat di-soft delete.");
        }

        $this->info($dryRun
            ? "Total {$totalDeleted} baris duplikat ditemukan (dry-run)."
            : "Total {$totalDeleted} baris duplikat dibersihkan.");

        return self::SUCCESS;
    }

    /**
     * Ambil id baris duplikat — semua baris aktif dalam satu grup kecuali id terkecil.
     */
    private function findDuplicateIds(string $table, string $scopeColumn, ?string $quotationId): array
    {
        $rows = DB::table($table)
            ->select('id', 'quotation_id', $scopeColumn, 'barang_id', 'jenis_barang_id', 'nama')
            ->whereNull('deleted_at')
            ->when($quotationId, fn ($query) => $query->where('quotation_id', $quotationId))
            ->orderBy('id')
            ->get();

        $seen = [];
        $duplicateIds = [];

        foreach ($rows as $row) {
            // Barang custom tidak punya barang_id — pakai nama sebagai pembeda,
            // sama seperti QuotationBarangService::generateItemKey().
            $key = implode('|', [
                $row->quotation_id,
                $row->{$scopeColumn},
                $row->barang_id ?? 'custom:' . ($row->nama ?? ''),
                $row->jenis_barang_id,
            ]);

            if (isset($seen[$key])) {
                $duplicateIds[] = $row->id;
                continue;
            }

            $seen[$key] = true;
        }

        return $duplicateIds;
    }
}
