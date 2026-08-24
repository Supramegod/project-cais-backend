<?php

namespace App\Services\Pks\Fulfillment;

use App\Models\PksItemFulfillment;
use App\Models\PksItemRequest;
use App\Models\QuotationChemical;
use App\Models\QuotationDevices;
use App\Models\QuotationKaporlap;

class ItemFulfillmentDashboardService
{
    private const CHUNK = 2000;

    /**
     * @var array<int, array{0:class-string, 1:string, 2:string, 3:string}>
     */
    private const SEMESTA_ITEM = [
        [QuotationKaporlap::class, 'sl_quotation_kaporlap', 'sl_quotation_detail', 'quotation_detail_id'],
        [QuotationDevices::class, 'sl_quotation_devices', 'sl_quotation_site', 'quotation_site_id'],
        [QuotationChemical::class, 'sl_quotation_chemical', 'sl_quotation_site', 'quotation_site_id'],
    ];

    /**
     * @param  array<int, int|null>  $quotationIdByPksId  peta pks_id => quotation_id
     * @return array<int, array{jumlah_request:int, jumlah_receive:int, jumlah_fulfilled:int, total_item:int, jumlah_remaining:int}>
     */
    public function perPks(array $quotationIdByPksId): array
    {
        if (empty($quotationIdByPksId)) {
            return [];
        }

        $pksIds = array_map('intval', array_keys($quotationIdByPksId));
        $quotationIds = array_values(array_unique(array_filter(
            array_map('intval', $quotationIdByPksId)
        )));

        $requestTerbuka = $this->countRequestTerbuka($pksIds);
        $fulfillment = $this->countFulfillment($pksIds);
        $semesta = $this->countSemestaItem($quotationIds);

        $hasil = [];

        foreach ($quotationIdByPksId as $pksId => $quotationId) {
            $pksId = (int) $pksId;
            $quotationId = (int) $quotationId;

            $totalItem = $quotationId > 0 ? (int) ($semesta[$quotationId] ?? 0) : 0;
            $jumlahFulfilled = (int) ($fulfillment[$pksId]['jumlah_fulfilled'] ?? 0);

            $hasil[$pksId] = [
                'jumlah_request' => (int) ($requestTerbuka[$pksId] ?? 0),
                'jumlah_receive' => (int) ($fulfillment[$pksId]['jumlah_receive'] ?? 0),
                'jumlah_fulfilled' => $jumlahFulfilled,
                'total_item' => $totalItem,
                'jumlah_remaining' => max(0, $totalItem - $jumlahFulfilled),
            ];
        }

        return $hasil;
    }

    /**
     * @param  array<int, array<string, int>>  $perPks  hasil perPks()
     * @return array{total_request:int, total_receive:int, total_fulfilled:int, total_remaining:int}
     */
    public function summaryFor(array $perPks): array
    {
        $summary = [
            'total_request' => 0,
            'total_receive' => 0,
            'total_fulfilled' => 0,
            'total_remaining' => 0,
        ];

        foreach ($perPks as $baris) {
            $summary['total_request'] += (int) $baris['jumlah_request'];
            $summary['total_receive'] += (int) $baris['jumlah_receive'];
            $summary['total_fulfilled'] += (int) $baris['jumlah_fulfilled'];
            $summary['total_remaining'] += (int) $baris['jumlah_remaining'];
        }

        return $summary;
    }

    /**
     * @param  array<int, int>  $pksIds
     * @return array<int, int>
     */
    private function countRequestTerbuka(array $pksIds): array
    {
        $hasil = [];

        foreach (array_chunk($pksIds, self::CHUNK) as $chunk) {
            $rows = PksItemRequest::query()
                ->whereIn('pks_id', $chunk)
                ->where('status', PksItemRequest::STATUS_OPEN)
                ->groupBy('pks_id')
                ->selectRaw('pks_id, COUNT(*) as jumlah')
                ->get();

            foreach ($rows as $row) {
                $hasil[(int) $row->pks_id] = (int) $row->jumlah;
            }
        }

        return $hasil;
    }

    /**
     * @param  array<int, int>  $pksIds
     * @return array<int, array{jumlah_receive:int, jumlah_fulfilled:int}>
     */
    private function countFulfillment(array $pksIds): array
    {
        $hasil = [];

        foreach (array_chunk($pksIds, self::CHUNK) as $chunk) {
            $rows = PksItemFulfillment::query()
                ->whereIn('pks_id', $chunk)
                ->groupBy('pks_id')
                ->selectRaw(
                    'pks_id, SUM(CASE WHEN qty_terpenuhi > 0 THEN 1 ELSE 0 END) as jumlah_receive, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as jumlah_fulfilled',
                    [PksItemFulfillment::STATUS_FULL]
                )
                ->get();

            foreach ($rows as $row) {
                $hasil[(int) $row->pks_id] = [
                    'jumlah_receive' => (int) $row->jumlah_receive,
                    'jumlah_fulfilled' => (int) $row->jumlah_fulfilled,
                ];
            }
        }

        return $hasil;
    }

    /**
     * @param  array<int, int>  $quotationIds
     * @return array<int, int> quotation_id => jumlah baris item
     */
    private function countSemestaItem(array $quotationIds): array
    {
        $hasil = [];

        if (empty($quotationIds)) {
            return $hasil;
        }

        foreach (self::SEMESTA_ITEM as [$model, $tabel, $induk, $foreignKey]) {
            foreach (array_chunk($quotationIds, self::CHUNK) as $chunk) {
                $rows = $model::query()
                    ->join($induk, function ($join) use ($tabel, $induk, $foreignKey) {
                        $join->on($induk.'.id', '=', $tabel.'.'.$foreignKey)
                            ->whereNull($induk.'.deleted_at');
                    })
                    ->whereIn($tabel.'.quotation_id', $chunk)
                    ->groupBy($tabel.'.quotation_id')
                    ->selectRaw($tabel.'.quotation_id as quotation_id, COUNT(*) as jumlah')
                    ->get();

                foreach ($rows as $row) {
                    $quotationId = (int) $row->quotation_id;
                    $hasil[$quotationId] = ($hasil[$quotationId] ?? 0) + (int) $row->jumlah;
                }
            }
        }

        return $hasil;
    }
}
