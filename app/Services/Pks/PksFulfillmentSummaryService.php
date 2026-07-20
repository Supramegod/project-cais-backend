<?php

namespace App\Services\Pks;

use App\Models\Pks;
use App\Models\PksVisitSchedule;
use App\Models\PksVisitTarget;

/**
 * Ringkasan pemenuhan (fulfillment) per PKS — menggabungkan Item & Visit
 * dalam satu payload untuk ditampilkan di view PKS. Slot Training & HC
 * disediakan (null) dan akan diisi setelah tabelnya dikonfirmasi.
 */
class PksFulfillmentSummaryService
{
    public function __construct(
        private ItemFulfillmentService $itemFulfillmentService,
        private HcFulfillmentService $hcFulfillmentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Pks $pks): array
    {
        return [
            'pks' => [
                'id' => $pks->id,
                'nomor' => $pks->nomor,
                'status_pks_id' => $pks->status_pks_id,
                'kontrak_awal' => $this->toDate($pks->kontrak_awal),
                'kontrak_akhir' => $this->toDate($pks->kontrak_akhir),
            ],
            'item' => $this->itemSummary($pks),
            'visit' => $this->visitSummary($pks),
            'training' => null, // menunggu konfirmasi tabel (read-only, dari HRIS)
            'hc' => $this->hcFulfillmentService->forPks($pks)['overall'],
        ];
    }

    /**
     * Rekap item: total per status + persentase qty, overall & per site.
     *
     * @return array<string, mixed>
     */
    private function itemSummary(Pks $pks): array
    {
        $perSite = [];
        $overall = [
            'total_item' => 0,
            'fully_fulfilled' => 0,
            'partially_fulfilled' => 0,
            'not_yet_fulfilled' => 0,
            'total_qty_diminta' => 0,
            'total_qty_terpenuhi' => 0,
        ];

        $sites = $pks->sites()->select('id', 'pks_id', 'nama_site')->get();

        foreach ($sites as $site) {
            $items = $this->itemFulfillmentService->getRequestedItems($pks, $site->id);

            $siteRow = [
                'site_id' => $site->id,
                'nama_site' => $site->nama_site,
                'total_item' => count($items),
                'fully_fulfilled' => 0,
                'partially_fulfilled' => 0,
                'not_yet_fulfilled' => 0,
                'total_qty_diminta' => 0,
                'total_qty_terpenuhi' => 0,
            ];

            foreach ($items as $item) {
                $status = $item['status'] ?? 'not_yet_fulfilled';
                if (isset($siteRow[$status])) {
                    $siteRow[$status]++;
                }
                $siteRow['total_qty_diminta'] += (int) ($item['qty_diminta'] ?? 0);
                $siteRow['total_qty_terpenuhi'] += (int) ($item['qty_terpenuhi'] ?? 0);
            }

            $siteRow['persen'] = $this->persen($siteRow['total_qty_terpenuhi'], $siteRow['total_qty_diminta'], $siteRow['total_item']);
            $perSite[] = $siteRow;

            $overall['total_item'] += $siteRow['total_item'];
            $overall['fully_fulfilled'] += $siteRow['fully_fulfilled'];
            $overall['partially_fulfilled'] += $siteRow['partially_fulfilled'];
            $overall['not_yet_fulfilled'] += $siteRow['not_yet_fulfilled'];
            $overall['total_qty_diminta'] += $siteRow['total_qty_diminta'];
            $overall['total_qty_terpenuhi'] += $siteRow['total_qty_terpenuhi'];
        }

        $overall['persen'] = $this->persen($overall['total_qty_terpenuhi'], $overall['total_qty_diminta'], $overall['total_item']);

        return [
            'overall' => $overall,
            'per_site' => $perSite,
        ];
    }

    /**
     * Rekap visit: target per role, jumlah jadwal per status, jadwal terdekat.
     *
     * @return array<string, mixed>
     */
    private function visitSummary(Pks $pks): array
    {
        $perRole = PksVisitTarget::where('pks_id', $pks->id)
            ->get()
            ->map(function ($target) {
                return [
                    'role' => $target->role,
                    'target_total' => (int) $target->target_total,
                    'target_terpakai' => (int) $target->target_terpakai,
                    'sisa' => (int) $target->target_total - (int) $target->target_terpakai,
                ];
            })->all();

        $counts = ['scheduled' => 0, 'rescheduled' => 0, 'done' => 0, 'missed' => 0];
        $rows = PksVisitSchedule::where('pks_id', $pks->id)
            ->selectRaw('status, COUNT(*) as jml')
            ->groupBy('status')
            ->pluck('jml', 'status');
        foreach ($rows as $status => $jml) {
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $jml;
            }
        }

        $upcoming = PksVisitSchedule::where('pks_id', $pks->id)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->whereDate('tgl_jadwal', '>=', now()->toDateString())
            ->orderBy('tgl_jadwal')
            ->select('id', 'pks_id', 'site_id', 'role', 'tgl_jadwal', 'status')
            ->first();

        return [
            'per_role' => $perRole,
            'schedule_counts' => $counts,
            'upcoming' => $upcoming ? [
                'id' => $upcoming->id,
                'site_id' => $upcoming->site_id,
                'role' => $upcoming->role,
                'tgl_jadwal' => $this->toDate($upcoming->tgl_jadwal),
            ] : null,
        ];
    }

    /**
     * Persentase pemenuhan qty. Bila tidak ada item sama sekali → 100 (tak ada
     * kewajiban); bila ada item tapi qty_diminta 0 → 0.
     */
    private function persen(int $terpenuhi, int $diminta, int $totalItem): float
    {
        if ($totalItem === 0) {
            return 100.0;
        }
        if ($diminta <= 0) {
            return 0.0;
        }

        return round(min(100, $terpenuhi / $diminta * 100), 1);
    }

    private function toDate($value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof \Carbon\CarbonInterface
            ? $value->toDateString()
            : (string) $value;
    }
}
