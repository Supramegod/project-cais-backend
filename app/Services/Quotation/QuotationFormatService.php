<?php

namespace App\Services\Quotation;

use App\Models\Leads;
use App\Models\Quotation;

class QuotationFormatService
{
    /**
     * Get filtered quotations for reference selection.
     */
    public function getFilteredQuotations(string $leadsId, string $tipeQuotation): \Illuminate\Support\Collection
    {
        $query = Quotation::select([
            'id', 'nomor', 'nama_perusahaan', 'tgl_quotation', 'kebutuhan_id',
            'kebutuhan', 'company', 'mulai_kontrak', 'kontrak_selesai',
            'jumlah_site', 'step', 'is_aktif', 'status_quotation_id', 'tipe_quotation',
        ])
            ->where('leads_id', $leadsId)
            ->with([
                'statusQuotation:id,nama',
                'pks:id,quotation_id,nomor,tgl_pks,kontrak_awal,kontrak_akhir,is_aktif',
                'quotationSites:id,quotation_id,nama_site,provinsi,provinsi_id,kota,kota_id,penempatan,ump,umk',
            ])
            ->withoutTrashed();

        switch ($tipeQuotation) {
            case 'baru':
            case 'revisi':
                $query->whereIn('status_quotation_id', [2, 3, 4, 5, 7, 8]);
                break;
            case 'addendum':
            case 'rekontrak':
                $query->whereIn('status_quotation_id', [3, 6])
                    ->whereHas('sites', function ($siteQuery) {
                        $siteQuery->whereHas('pks', function ($pksQuery) {
                            $pksQuery->where('is_aktif', 1);
                        });
                    });
                break;
        }

        return $query->latest('created_at')
            ->get()
            ->map(fn (Quotation $quotation) => $this->formatQuotationData($quotation, $tipeQuotation));
    }

    /**
     * Format quotation data for reference response shape.
     */
    public function formatQuotationData(Quotation $quotation, string $tipeQuotation): array
    {
        $data = [
            'id' => $quotation->id,
            'nomor' => $quotation->nomor,
            'nama_perusahaan' => $quotation->nama_perusahaan,
            'mulai_kontrak' => $quotation->mulai_kontrak,
            'kontrak_selesai' => $quotation->kontrak_selesai,
            'tgl_quotation' => $quotation->tgl_quotation,
            'kebutuhan_id' => $quotation->kebutuhan_id,
            'jumlah_site' => $quotation->jumlah_site,
            'step' => $quotation->step,
            'is_aktif' => $quotation->is_aktif,
            'status_quotation_id' => $quotation->status_quotation_id,
            'status_quotation' => $quotation->statusQuotation->nama ?? 'Unknown',
            'tipe_quotation' => $quotation->tipe_quotation,
            'kebutuhan' => $quotation->kebutuhan,
            'company' => $quotation->company,
            'source' => 'quotation',
            'sites' => $quotation->quotationSites->map(function ($site) {
                return [
                    'id' => $site->id,
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,
                    'provinsi_id' => $site->provinsi_id,
                    'kota_id' => $site->kota_id,
                    'ump' => $site->ump,
                    'umk' => $site->umk,
                ];
            })->toArray(),
        ];

        $data['site'] = $quotation->quotationSites->first()->nama_site ?? null;

        if ($tipeQuotation === 'rekontrak' && $quotation->pks) {
            $data['pks_data'] = [
                'id' => $quotation->pks->id,
                'nomor' => $quotation->pks->nomor,
                'tgl_pks' => $quotation->pks->tgl_pks,
                'kontrak_awal' => $quotation->pks->kontrak_awal,
                'kontrak_akhir' => $quotation->pks->kontrak_akhir,
                'is_aktif' => $quotation->pks->is_aktif,
            ];
        }

        return $data;
    }
}
