<?php

namespace App\Http\Resources\Quotation;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Focused resource for quotation site data (grouped by site).
 * Handles kaporlap, devices, chemicals, and ohc site-level grouping.
 */
class QuotationSiteResource extends JsonResource
{
    /**
     * Transform quotation kaporlaps grouped by site.
     */
    public static function kaporlapBySite($quotation): array
    {
        if (! $quotation->relationLoaded('quotationKaporlaps')) {
            return [];
        }

        return $quotation->quotationKaporlaps->groupBy('quotation_detail_id')->map(function ($kaporlaps, $detailId) use ($quotation) {
            $detail = $quotation->quotationDetails->firstWhere('id', $detailId);
            return [
                'quotation_detail_id' => $detailId,
                'quotation_site_id' => $detail->quotation_site_id ?? null,
                'jabatan_kebutuhan' => $detail->jabatan_kebutuhan ?? 'Legacy Item',
                'jumlah_hc' => $detail->jumlah_hc ?? 0,
                'items' => $kaporlaps->map(fn ($k) => [
                    'id' => $k->id,
                    'barang_id' => $k->barang_id,
                    'nama' => $k->nama,
                    'jenis_barang_id' => $k->jenis_barang_id,
                    'jenis_barang' => $k->jenis_barang,
                    'jumlah' => $k->jumlah,
                    'harga' => $k->harga,
                    'total' => $k->jumlah * $k->harga,
                ])->values(),
            ];
        })
            ->values()
            ->groupBy('quotation_site_id')
            ->map(function ($details, $siteId) use ($quotation) {
                $site = $quotation->quotationSites->firstWhere('id', $siteId);
                return [
                    'quotation_site_id' => $siteId,
                    'nama_site' => $site->nama_site ?? 'Unknown Site',
                    'details' => $details->map(function ($detail) {
                        return collect($detail)->except('quotation_site_id')->toArray();
                    })->values(),
                ];
            })
            ->values();
    }

    /**
     * Transform quotation devices grouped by site.
     */
    public static function devicesBySite($quotation): array
    {
        if (! $quotation->relationLoaded('quotationDevices')) {
            return [];
        }

        return $quotation->quotationDevices->groupBy('quotation_site_id')->map(function ($devices, $siteId) use ($quotation) {
            $site = $quotation->quotationSites->firstWhere('id', $siteId);
            $totalHc = $quotation->quotationDetails->where('quotation_site_id', $siteId)->sum('jumlah_hc');
            $jabatanKebutuhan = $quotation->quotationDetails->where('quotation_site_id', $siteId)->pluck('jabatan_kebutuhan')->unique()->implode(', ');

            $namaSite = (! $site && ! $siteId) ? 'Legacy/Global Data' : ($site->nama_site ?? 'Unknown Site');
            $jabatanKebutuhan = ($site || $siteId) ? $jabatanKebutuhan : ($jabatanKebutuhan ?: 'Unassigned Site');

            return [
                'quotation_site_id' => $siteId,
                'nama_site' => $namaSite,
                'jabatan_kebutuhan' => $jabatanKebutuhan,
                'jumlah_hc' => $totalHc,
                'items' => $devices->map(function ($device) {
                    return [
                        'id' => $device->id,
                        'barang_id' => $device->barang_id,
                        'nama' => $device->nama,
                        'jenis_barang_id' => $device->jenis_barang_id,
                        'jenis_barang' => $device->jenis_barang,
                        'jumlah' => $device->jumlah,
                        'harga' => $device->harga,
                        'total' => $device->jumlah * $device->harga,
                    ];
                })->values(),
            ];
        })->values();
    }

    /**
     * Transform quotation chemicals grouped by site.
     */
    public static function chemicalsBySite($quotation): array
    {
        if (! $quotation->relationLoaded('quotationChemicals')) {
            return [];
        }

        return $quotation->quotationChemicals->groupBy('quotation_site_id')->map(function ($chemicals, $siteId) use ($quotation) {
            $site = $quotation->quotationSites->firstWhere('id', $siteId);
            $totalHc = $quotation->quotationDetails->where('quotation_site_id', $siteId)->sum('jumlah_hc');
            $jabatanKebutuhan = $quotation->quotationDetails->where('quotation_site_id', $siteId)->pluck('jabatan_kebutuhan')->unique()->implode(', ');

            $namaSite = (! $site && ! $siteId) ? 'Legacy/Global Data' : ($site->nama_site ?? 'Unknown Site');
            $jabatanKebutuhan = ($site || $siteId) ? $jabatanKebutuhan : ($jabatanKebutuhan ?: 'Unassigned Site');

            return [
                'quotation_site_id' => $siteId,
                'nama_site' => $namaSite,
                'jabatan_kebutuhan' => $jabatanKebutuhan,
                'jumlah_hc' => $totalHc,
                'items' => $chemicals->map(function ($chemical) {
                    return [
                        'id' => $chemical->id,
                        'barang_id' => $chemical->barang_id,
                        'nama' => $chemical->nama,
                        'jenis_barang_id' => $chemical->jenis_barang_id,
                        'jenis_barang' => $chemical->jenis_barang,
                        'jumlah' => $chemical->jumlah,
                        'harga' => $chemical->harga,
                        'masa_pakai' => $chemical->masa_pakai,
                        'total_per_tahun' => $chemical->harga * $chemical->jumlah / $chemical->masa_pakai * 12,
                    ];
                })->values(),
            ];
        })->values();
    }

    /**
     * Transform quotation ohcs grouped by site.
     */
    public static function ohcsBySite($quotation): array
    {
        if (! $quotation->relationLoaded('quotationOhcs')) {
            return [];
        }

        return $quotation->quotationOhcs->groupBy('quotation_site_id')->map(function ($ohcs, $siteId) use ($quotation) {
            $site = $quotation->quotationSites->firstWhere('id', $siteId);
            $totalHc = $quotation->quotationDetails->where('quotation_site_id', $siteId)->sum('jumlah_hc');
            $jabatanKebutuhan = $quotation->quotationDetails->where('quotation_site_id', $siteId)->pluck('jabatan_kebutuhan')->unique()->implode(', ');

            $namaSite = (! $site && ! $siteId) ? 'Legacy/Global Data' : ($site->nama_site ?? 'Unknown Site');
            $jabatanKebutuhan = ($site || $siteId) ? $jabatanKebutuhan : ($jabatanKebutuhan ?: 'Unassigned Site');

            return [
                'quotation_site_id' => $siteId,
                'nama_site' => $namaSite,
                'jabatan_kebutuhan' => $jabatanKebutuhan,
                'jumlah_hc' => $totalHc,
                'items' => $ohcs->map(function ($ohc) {
                    return [
                        'id' => $ohc->id,
                        'barang_id' => $ohc->barang_id,
                        'nama' => $ohc->nama,
                        'jenis_barang_id' => $ohc->jenis_barang_id,
                        'jenis_barang' => $ohc->jenis_barang,
                        'jumlah' => $ohc->jumlah,
                        'harga' => $ohc->harga,
                        'total' => $ohc->jumlah * $ohc->harga,
                    ];
                })->values(),
            ];
        })->values();
    }
}
