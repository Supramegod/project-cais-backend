<?php

namespace App\Services\Pks;

use App\Models\HrisSite;
use App\Models\Pks;
use App\Models\Site;
use App\Models\Vacancy;

/**
 * Pemenuhan HC (rekrutmen) per PKS — READ-ONLY, data dari HRIS (mysqlhris).
 * Tidak ada aksi tulis: angka pemenuhan HC dikelola di HRIS.
 *
 * Full Eloquent (relasi + withCount), tanpa join/selectRaw manual.
 * Alur: Vacancy (m_vacancy, target `needs`) → applicants (t_applicant) →
 * employee (m_employee: status_approval & followup_status).
 * Tautan ke PKS: HRIS m_site.site_id = CRM sl_site.id (dibuat saat aktivasi PKS),
 * jadi vacancy difilter lewat m_site milik PKS.
 *
 * Funnel status_approval (m_employee):
 *   0 + followup 'Pemanggilan' → tahap pemanggilan
 *   1                          → tahap pengiriman
 *   >= 3                       → terpenuhi (akumulasi mengisi kebutuhan)
 */
class HcFulfillmentService
{
    /**
     * @return array{per_vacancy: array<int, array<string, mixed>>, overall: array<string, mixed>}
     */
    public function forPks(Pks $pks): array
    {
        // sl_site.id milik PKS (mysql) → m_site HRIS (m_site.site_id = sl_site.id).
        $siteIds = $pks->sites()->pluck('id')->filter()->values()->all();
        if (empty($siteIds)) {
            return ['per_vacancy' => [], 'overall' => $this->emptyOverall()];
        }

        $hrisSiteIds = HrisSite::whereIn('site_id', $siteIds)->pluck('id')->all();
        if (empty($hrisSiteIds)) {
            return ['per_vacancy' => [], 'overall' => $this->emptyOverall()];
        }

        $vacancies = Vacancy::query()
            ->whereIn('site_id', $hrisSiteIds)
            ->where('is_active', 1)
            ->when($pks->kontrak_akhir, fn ($q) => $q->whereDate('start_date', '<=', $pks->kontrak_akhir))
            ->when($pks->kontrak_awal, fn ($q) => $q->whereDate('end_date', '>=', $pks->kontrak_awal))
            ->with(['hrisSite.branch', 'position'])
            ->withCount($this->funnelCounts())
            ->orderBy('id')
            ->get();

        return $this->format($vacancies);
    }

    /**
     * Rekap HC overall untuk sekumpulan pks_id (batched, dipakai dashboard).
     * Satu query vacancy untuk semua PKS, lalu diagregat per pks_id di PHP.
     *
     * @param  array<int>  $pksIds
     * @return array<int, array<string, mixed>>  keyed by pks_id
     */
    public function overallForPksIds(array $pksIds): array
    {
        if (empty($pksIds)) {
            return [];
        }

        // sl_site.id → pks_id (CRM)
        $sitePks = Site::whereIn('pks_id', $pksIds)->pluck('pks_id', 'id');
        if ($sitePks->isEmpty()) {
            return [];
        }

        // HRIS m_site.id → sl_site.id
        $hrisToSl = HrisSite::whereIn('site_id', $sitePks->keys()->all())->pluck('site_id', 'id');
        if ($hrisToSl->isEmpty()) {
            return [];
        }

        $vacancies = Vacancy::query()
            ->whereIn('site_id', $hrisToSl->keys()->all())
            ->where('is_active', 1)
            ->withCount($this->funnelCounts())
            ->get(['id', 'site_id', 'needs']);

        // Agregat per pks_id
        $sums = [];
        foreach ($vacancies as $v) {
            $slSiteId = $hrisToSl[$v->site_id] ?? null;
            $pksId = $slSiteId !== null ? ($sitePks[$slSiteId] ?? null) : null;
            if ($pksId === null) {
                continue;
            }

            $sums[$pksId] ??= ['target' => 0, 'pemanggilan' => 0, 'pengiriman' => 0, 'akumulasi' => 0, 'total' => 0];
            $sums[$pksId]['target'] += (int) ($v->needs ?? 0);
            $sums[$pksId]['pemanggilan'] += (int) $v->jumlah_pemanggilan_only;
            $sums[$pksId]['pengiriman'] += (int) $v->jumlah_pengiriman_only;
            $sums[$pksId]['akumulasi'] += (int) $v->akumulasi_pengiriman;
            $sums[$pksId]['total']++;
        }

        $map = [];
        foreach ($sums as $pksId => $s) {
            $map[$pksId] = [
                'total_vacancy' => $s['total'],
                'target_kebutuhan' => $s['target'],
                'jumlah_pemanggilan_only' => $s['pemanggilan'],
                'jumlah_pengiriman_only' => $s['pengiriman'],
                'akumulasi_pengiriman' => $s['akumulasi'],
                'sisa_outstanding' => max(0, $s['target'] - $s['akumulasi']),
                'persen' => $this->persen($s['akumulasi'], $s['target'], $s['total']),
            ];
        }

        return $map;
    }

    /**
     * Definisi withCount funnel HC (dipakai forPks & overallForPksIds).
     *
     * @return array<string, \Closure>
     */
    private function funnelCounts(): array
    {
        return [
            'applicants as jumlah_pemanggilan_only' => fn ($q) => $q
                ->where('is_active', 1)
                ->whereHas('employee', fn ($e) => $e
                    ->where('is_active', 1)
                    ->where('status_approval', 0)
                    ->where('followup_status', 'like', '%Pemanggilan%')),
            'applicants as jumlah_pengiriman_only' => fn ($q) => $q
                ->where('is_active', 1)
                ->whereHas('employee', fn ($e) => $e
                    ->where('is_active', 1)
                    ->where('status_approval', 1)),
            'applicants as akumulasi_pengiriman' => fn ($q) => $q
                ->where('is_active', 1)
                ->whereHas('employee', fn ($e) => $e
                    ->where('is_active', 1)
                    ->where('status_approval', '>=', 3)),
        ];
    }

    /**
     * @return array{per_vacancy: array<int, array<string, mixed>>, overall: array<string, mixed>}
     */
    private function format($vacancies): array
    {
        $perVacancy = [];
        $sumTarget = 0;
        $sumPemanggilan = 0;
        $sumPengiriman = 0;
        $sumAkumulasi = 0;

        foreach ($vacancies as $v) {
            $target = (int) ($v->needs ?? 0);
            $pemanggilan = (int) $v->jumlah_pemanggilan_only;
            $pengiriman = (int) $v->jumlah_pengiriman_only;
            $akumulasi = (int) $v->akumulasi_pengiriman;
            $sisa = max(0, $target - $akumulasi);

            $site = $v->hrisSite;
            $branch = $site?->branch;

            $perVacancy[] = [
                'branch_id' => $branch?->id,
                'nama_cabang' => $branch?->name,
                'site_id' => $site?->site_id !== null ? (int) $site->site_id : null,
                'nama_site' => $site?->name,
                'nama_posisi' => $v->position?->name,
                'vacancy_id' => (int) $v->id,
                'judul_lowongan' => $v->title,
                'start_date' => $v->start_date,
                'end_date' => $v->end_date,
                'target_kebutuhan' => $target,
                'jumlah_pemanggilan_only' => $pemanggilan,
                'jumlah_pengiriman_only' => $pengiriman,
                'akumulasi_pengiriman' => $akumulasi,
                'sisa_outstanding' => $sisa,
            ];

            $sumTarget += $target;
            $sumPemanggilan += $pemanggilan;
            $sumPengiriman += $pengiriman;
            $sumAkumulasi += $akumulasi;
        }

        return [
            'per_vacancy' => $perVacancy,
            'overall' => [
                'total_vacancy' => count($perVacancy),
                'target_kebutuhan' => $sumTarget,
                'jumlah_pemanggilan_only' => $sumPemanggilan,
                'jumlah_pengiriman_only' => $sumPengiriman,
                'akumulasi_pengiriman' => $sumAkumulasi,
                'sisa_outstanding' => max(0, $sumTarget - $sumAkumulasi),
                'persen' => $this->persen($sumAkumulasi, $sumTarget, count($perVacancy)),
            ],
        ];
    }

    public function emptyOverall(): array
    {
        return [
            'total_vacancy' => 0,
            'target_kebutuhan' => 0,
            'jumlah_pemanggilan_only' => 0,
            'jumlah_pengiriman_only' => 0,
            'akumulasi_pengiriman' => 0,
            'sisa_outstanding' => 0,
            'persen' => 100.0,
        ];
    }

    private function persen(int $terpenuhi, int $target, int $totalVacancy): float
    {
        if ($totalVacancy === 0) {
            return 100.0;
        }
        if ($target <= 0) {
            return 0.0;
        }

        return round(min(100, $terpenuhi / $target * 100), 1);
    }
}
