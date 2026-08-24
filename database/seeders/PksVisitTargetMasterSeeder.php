<?php

namespace Database\Seeders;

use App\Models\KategoriSesuaiHc;
use App\Models\PksVisitTargetMaster;
use Illuminate\Database\Seeder;

/**
 * Seed matrix master visit target per kebutuhan + range HC (m_pks_visit_target).
 *
 * Idempotent: keyed by (kebutuhan_id, hc_min, hc_max) via updateOrCreate.
 * kategori_sesuai_hc_id di-lookup dinamis dari m_kategori_sesuai_hc berdasarkan nama.
 */
class PksVisitTargetMasterSeeder extends Seeder
{
    public function run(): void
    {
        // Matrix per kebutuhan: [hc_min, hc_max, kategori nama, target_visit_per_tahun]
        // Kebutuhan 1 = Security, 3 = Cleaning Service (range HC kecil)
        // Kebutuhan 2 = Labour Supply, 4 = Logistic (range HC besar)
        $smallRange = [
            [1, 20, 'Silver', 2],
            [21, 30, 'Gold', 4],
            [31, 50, 'Platinum', 6],
            [51, 1000, 'Diamond', 12],
        ];

        $largeRange = [
            [1, 100, 'Silver', 2],
            [101, 300, 'Gold', 4],
            [301, 500, 'Platinum', 6],
            [501, 5000, 'Diamond', 12],
        ];

        $matrix = [
            1 => $smallRange, // Security
            2 => $largeRange, // Labour Supply
            3 => $smallRange, // Cleaning Service
            4 => $largeRange, // Logistic
        ];

        // Lookup dinamis kategori berdasarkan nama (case-insensitive key map)
        $kategoriMap = KategoriSesuaiHc::query()
            ->pluck('id', 'nama')
            ->mapWithKeys(fn ($id, $nama) => [mb_strtolower(trim((string) $nama)) => $id])
            ->all();

        foreach ($matrix as $kebutuhanId => $rows) {
            foreach ($rows as [$hcMin, $hcMax, $kategoriNama, $targetVisit]) {
                $kategoriId = $kategoriMap[mb_strtolower($kategoriNama)] ?? null;

                if ($kategoriId === null) {
                    $this->command?->warn(
                        "PksVisitTargetMasterSeeder: kategori '{$kategoriNama}' tidak ditemukan di m_kategori_sesuai_hc — "
                        . "skip baris kebutuhan_id={$kebutuhanId}, hc {$hcMin}-{$hcMax}."
                    );
                    continue;
                }

                PksVisitTargetMaster::updateOrCreate(
                    [
                        'kebutuhan_id' => $kebutuhanId,
                        'hc_min' => $hcMin,
                        'hc_max' => $hcMax,
                    ],
                    [
                        'kategori_sesuai_hc_id' => $kategoriId,
                        'target_visit_per_tahun' => $targetVisit,
                        'created_by' => 'seeder',
                    ]
                );
            }
        }
    }
}
