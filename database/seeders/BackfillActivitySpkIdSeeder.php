<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi sl_activity_sales.spk_id untuk baris aktivitas SPK lama yang masih NULL.
 *
 * Baris SPK lama (2025-02 s/d 2026-05) ditulis tanpa spk_id dan tanpa notulen,
 * jadi kolom `aksi` di laporan detail aktivitas sales selalu null — tautan ke
 * dokumen SPK-nya hilang. Tidak ada kolom lain yang menyimpan id itu, jadi
 * satu-satunya jalan adalah mencocokkan lewat leads_id + tanggal.
 *
 * Pencocokan dilakukan per grup (leads_id, tgl_activity) dan hanya diterima bila
 * TEPAT satu aktivitas berhadapan dengan TEPAT satu SPK:
 *
 *   1. sl_spk.tgl_spk pada tanggal yang sama — sumber utama.
 *   2. sl_spk.created_at pada tanggal yang sama — hanya dipakai bila (1) kosong,
 *      untuk baris lama yang tgl_spk-nya tidak diisi.
 *
 * SPK yang sudah dirujuk baris aktivitas lain sengaja dibuang dari kandidat
 * supaya satu SPK tidak tertaut ke dua aktivitas. Grup yang tetap ambigu
 * dilewati, bukan ditebak, dan dilaporkan di akhir.
 *
 * Aman diulang: hanya menyentuh baris yang spk_id-nya masih NULL. Memakai query
 * builder, bukan Eloquent, supaya updated_at tidak ikut berubah.
 */
class BackfillActivitySpkIdSeeder extends Seeder
{
    public function run(): void
    {
        $pending = $this->pendingActivities();

        if ($pending->isEmpty()) {
            $this->command?->info('Tidak ada aktivitas SPK dengan spk_id NULL. Tidak ada yang dikerjakan.');

            return;
        }

        $usedSpkIds = $this->spkIdsAlreadyLinked();
        $candidates = $this->candidatesByLeadsAndDate($pending->pluck('leads_id')->unique()->all(), $usedSpkIds);

        $resolved = [];
        $ambiguous = [];
        $unmatched = [];

        foreach ($pending->groupBy(fn ($row) => ((int) $row->leads_id).'|'.$row->tgl_activity) as $key => $rows) {
            $group = $candidates[$key] ?? [];

            if ($group === []) {
                $unmatched[] = $key.' ('.$rows->count().' aktivitas, 0 SPK)';

                continue;
            }

            if ($rows->count() !== 1 || count($group) !== 1) {
                $ambiguous[] = $key.' ('.$rows->count().' aktivitas, '.count($group).' SPK)';

                continue;
            }

            $resolved[(int) $rows->first()->id] = (int) $group[0];
        }

        $updated = 0;

        DB::transaction(function () use ($resolved, &$updated): void {
            foreach ($resolved as $activityId => $spkId) {
                $updated += DB::table('sl_activity_sales')
                    ->where('id', $activityId)
                    ->whereNull('spk_id')
                    ->update([
                        'spk_id' => $spkId,
                        'updated_at' => DB::raw('updated_at'),
                    ]);
            }
        });

        $this->report($updated, $pending->count(), $ambiguous, $unmatched);
    }

    /**
     * Aktivitas SPK yang spk_id-nya masih NULL.
     */
    private function pendingActivities(): \Illuminate\Support\Collection
    {
        return DB::table('sl_activity_sales')
            ->select('id', 'leads_id', 'tgl_activity')
            ->where('jenis_activity', 'SPK')
            ->whereNull('spk_id')
            ->whereNotNull('leads_id')
            ->whereNotNull('tgl_activity')
            ->get()
            ->map(function ($row) {
                $row->tgl_activity = substr((string) $row->tgl_activity, 0, 10);

                return $row;
            });
    }

    /**
     * @return array<int, int>
     */
    private function spkIdsAlreadyLinked(): array
    {
        return DB::table('sl_activity_sales')
            ->whereNotNull('spk_id')
            ->distinct()
            ->pluck('spk_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Kandidat SPK per "leads_id|tanggal".
     *
     * tgl_spk didahulukan; created_at hanya mengisi grup yang belum punya
     * kandidat sama sekali, supaya tanggal pembuatan tidak menimpa tanggal SPK
     * yang sebenarnya.
     *
     * @param  array<int, mixed>  $leadsIds
     * @param  array<int, int>  $usedSpkIds
     * @return array<string, array<int, int>>
     */
    private function candidatesByLeadsAndDate(array $leadsIds, array $usedSpkIds): array
    {
        if ($leadsIds === []) {
            return [];
        }

        $spks = DB::table('sl_spk')
            ->select('id', 'leads_id', 'tgl_spk', 'created_at')
            ->whereIn('leads_id', $leadsIds)
            ->whereNull('deleted_at')
            ->get();

        $used = array_flip($usedSpkIds);
        $primary = [];
        $fallback = [];

        foreach ($spks as $spk) {
            if (isset($used[(int) $spk->id])) {
                continue;
            }

            if ($spk->tgl_spk !== null) {
                $primary[((int) $spk->leads_id).'|'.substr((string) $spk->tgl_spk, 0, 10)][] = (int) $spk->id;
            }

            if ($spk->created_at !== null) {
                $fallback[((int) $spk->leads_id).'|'.substr((string) $spk->created_at, 0, 10)][] = (int) $spk->id;
            }
        }

        return $primary + $fallback;
    }

    /**
     * @param  array<int, string>  $ambiguous
     * @param  array<int, string>  $unmatched
     */
    private function report(int $updated, int $pending, array $ambiguous, array $unmatched): void
    {
        $this->command?->info("Backfill spk_id selesai: {$updated} dari {$pending} baris terisi.");

        if ($ambiguous !== []) {
            $this->command?->warn('Dilewati karena jumlah aktivitas dan SPK tidak satu lawan satu:');
            foreach ($ambiguous as $item) {
                $this->command?->warn("  - {$item}");
            }
        }

        if ($unmatched !== []) {
            $this->command?->warn('Dilewati karena tidak ada SPK pada tanggal yang sama:');
            foreach ($unmatched as $item) {
                $this->command?->warn("  - {$item}");
            }
        }
    }
}
