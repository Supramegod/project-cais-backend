<?php

namespace App\Services\Pks;

use Illuminate\Support\Facades\DB;

/**
 * Rekap pemenuhan (Item + Visit) untuk sekumpulan PKS pada satu halaman
 * dashboard. Dirancang efisien: query/pagination PKS dilakukan di controller
 * (mengikuti pola PksController@index), sedangkan service ini hanya menghitung
 * rekap untuk baris-baris pada halaman aktif via beberapa query agregat
 * ber-GROUP BY (bukan loop per-site).
 *
 * Kelengkapan (is_complete) sementara dari Item + Visit; Training & HC menyusul.
 */
class PksFulfillmentDashboardService
{
    public function __construct(
        private HcFulfillmentService $hcFulfillmentService,
    ) {}

    /**
     * @param  iterable  $pksRows  Koleksi baris PKS halaman aktif (punya ->id & ->quotation_id)
     * @return array<int, array{item:array, visit:array, hc:array, is_complete:bool}>  keyed by pks_id
     */
    public function recapForPage(iterable $pksRows): array
    {
        $rows = collect($pksRows);
        $pksIds = $rows->pluck('id')->filter()->all();

        if (empty($pksIds)) {
            return [];
        }

        $quotationIds = $rows->pluck('quotation_id')->filter()->unique()->all();

        // Item diminta per quotation (kaporlap+device+chemical): jumlah baris & total qty.
        $itemReq = $this->aggregateRequestedItems($quotationIds);

        // Fulfillment per PKS: total terpenuhi qty + jumlah baris fully_fulfilled.
        $fulfillAgg = DB::table('sl_pks_item_fulfillment')
            ->whereIn('pks_id', $pksIds)
            ->whereNull('deleted_at')
            ->selectRaw('pks_id, SUM(qty_terpenuhi) as qty_terpenuhi, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as fully', ['fully_fulfilled'])
            ->groupBy('pks_id')
            ->get()
            ->keyBy('pks_id');

        // Visit target per PKS.
        $visitAgg = DB::table('sl_pks_visit_target')
            ->whereIn('pks_id', $pksIds)
            ->selectRaw('pks_id, SUM(target_total) as target_total, SUM(target_terpakai) as target_terpakai')
            ->groupBy('pks_id')
            ->get()
            ->keyBy('pks_id');

        // Visit missed per PKS.
        $missedAgg = DB::table('sl_pks_visit_schedule')
            ->whereIn('pks_id', $pksIds)
            ->where('status', 'missed')
            ->selectRaw('pks_id, COUNT(*) as missed')
            ->groupBy('pks_id')
            ->get()
            ->keyBy('pks_id');

        // HC overall per PKS (batched, dari HRIS).
        $hcMap = $this->hcFulfillmentService->overallForPksIds($pksIds);

        $map = [];
        foreach ($rows as $pks) {
            $req = $itemReq[$pks->quotation_id] ?? ['total' => 0, 'qty_diminta' => 0];
            $ful = $fulfillAgg[$pks->id] ?? null;
            $vis = $visitAgg[$pks->id] ?? null;
            $missed = (int) ($missedAgg[$pks->id]->missed ?? 0);

            $itemTotal = (int) $req['total'];
            $itemDiminta = (int) $req['qty_diminta'];
            $itemTerpenuhi = (int) ($ful->qty_terpenuhi ?? 0);
            $itemFully = (int) ($ful->fully ?? 0);
            $itemPersen = $this->persen($itemTerpenuhi, $itemDiminta, $itemTotal);

            $targetTotal = (int) ($vis->target_total ?? 0);
            $targetTerpakai = (int) ($vis->target_terpakai ?? 0);
            $visitPersen = $this->persen($targetTerpakai, $targetTotal, $targetTotal);

            $hc = $hcMap[$pks->id] ?? $this->hcFulfillmentService->emptyOverall();

            $itemComplete = $itemPersen >= 100;
            $visitComplete = $targetTotal === 0 || $targetTerpakai >= $targetTotal;
            $hcComplete = (int) $hc['sisa_outstanding'] <= 0;

            $map[$pks->id] = [
                'item' => [
                    'total' => $itemTotal,
                    'fully_fulfilled' => $itemFully,
                    'qty_diminta' => $itemDiminta,
                    'qty_terpenuhi' => $itemTerpenuhi,
                    'persen' => $itemPersen,
                ],
                'visit' => [
                    'target_total' => $targetTotal,
                    'target_terpakai' => $targetTerpakai,
                    'persen' => $visitPersen,
                    'missed' => $missed,
                ],
                'hc' => $hc,
                'is_complete' => $itemComplete && $visitComplete && $hcComplete,
            ];
        }

        return $map;
    }

    /**
     * Rekap fulfillment agregat (item + visit) dari hasil recapForPage — dipakai
     * sebagai blok "summary" dashboard (rekap seluruh PKS yang cocok filter).
     *
     * @param  array<int, array>  $recap  hasil recapForPage()
     * @return array<string, mixed>
     */
    public function aggregateSummary(array $recap): array
    {
        $item = ['total' => 0, 'fully_fulfilled' => 0, 'qty_diminta' => 0, 'qty_terpenuhi' => 0];
        $visit = ['target_total' => 0, 'target_terpakai' => 0, 'missed' => 0];
        $hc = ['total_vacancy' => 0, 'target_kebutuhan' => 0, 'jumlah_pemanggilan_only' => 0, 'jumlah_pengiriman_only' => 0, 'akumulasi_pengiriman' => 0];

        foreach ($recap as $r) {
            $item['total'] += $r['item']['total'];
            $item['fully_fulfilled'] += $r['item']['fully_fulfilled'];
            $item['qty_diminta'] += $r['item']['qty_diminta'];
            $item['qty_terpenuhi'] += $r['item']['qty_terpenuhi'];

            $visit['target_total'] += $r['visit']['target_total'];
            $visit['target_terpakai'] += $r['visit']['target_terpakai'];
            $visit['missed'] += $r['visit']['missed'];

            $rowHc = $r['hc'] ?? [];
            $hc['total_vacancy'] += $rowHc['total_vacancy'] ?? 0;
            $hc['target_kebutuhan'] += $rowHc['target_kebutuhan'] ?? 0;
            $hc['jumlah_pemanggilan_only'] += $rowHc['jumlah_pemanggilan_only'] ?? 0;
            $hc['jumlah_pengiriman_only'] += $rowHc['jumlah_pengiriman_only'] ?? 0;
            $hc['akumulasi_pengiriman'] += $rowHc['akumulasi_pengiriman'] ?? 0;
        }

        $item['persen'] = $this->persen($item['qty_terpenuhi'], $item['qty_diminta'], $item['total']);
        $visit['persen'] = $this->persen($visit['target_terpakai'], $visit['target_total'], $visit['target_total']);
        $hc['sisa_outstanding'] = max(0, $hc['target_kebutuhan'] - $hc['akumulasi_pengiriman']);
        $hc['persen'] = $this->persen($hc['akumulasi_pengiriman'], $hc['target_kebutuhan'], $hc['total_vacancy']);

        return [
            'item' => $item,
            'visit' => $visit,
            'hc' => $hc,
        ];
    }

    /**
     * Agregat item diminta per quotation_id dari 3 tabel item.
     *
     * @return array<int, array{total:int, qty_diminta:int}>
     */
    private function aggregateRequestedItems(array $quotationIds): array
    {
        $result = [];
        if (empty($quotationIds)) {
            return $result;
        }

        foreach (['sl_quotation_kaporlap', 'sl_quotation_devices', 'sl_quotation_chemical'] as $table) {
            $rows = DB::table($table)
                ->whereIn('quotation_id', $quotationIds)
                ->whereNull('deleted_at')
                ->selectRaw('quotation_id, COUNT(*) as jml, SUM(jumlah) as qty')
                ->groupBy('quotation_id')
                ->get();

            foreach ($rows as $row) {
                $qid = (int) $row->quotation_id;
                if (! isset($result[$qid])) {
                    $result[$qid] = ['total' => 0, 'qty_diminta' => 0];
                }
                $result[$qid]['total'] += (int) $row->jml;
                $result[$qid]['qty_diminta'] += (int) $row->qty;
            }
        }

        return $result;
    }

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
}
