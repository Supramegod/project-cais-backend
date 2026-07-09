<?php

namespace App\Services\Quotation;

use App\Models\Leads;
use App\Models\Quotation;

class QuotationDataService
{
    /**
     * Load a quotation referensi with all needed relations for duplication.
     */
    public function loadQuotationReferensi(?int $quotationReferensiId): ?Quotation
    {
        if (!$quotationReferensiId) {
            return null;
        }

        return Quotation::with([
            'quotationDetails.quotationDetailHpps',
            'quotationDetails.quotationDetailCosses',
            'quotationDetails.wage',
            'quotationDetails.quotationDetailRequirements',
            'quotationDetails.quotationDetailTunjangans',
            'leads',
            'statusQuotation',
            'quotationSites',
            'quotationPics',
            'quotationAplikasis',
            'quotationKaporlaps',
            'quotationDevices',
            'quotationChemicals',
            'quotationOhcs',
            'quotationTrainings',
            'quotationKerjasamas',
        ])->findOrFail($quotationReferensiId);
    }

    /**
     * Get available leads for quotation creation based on tipe_quotation.
     */
    public function getAvailableLeads(string $tipeQuotation): \Illuminate\Support\Collection
    {
        $query = Leads::select('id', 'nama_perusahaan', 'pic', 'status_leads_id', 'branch_id', 'customer_id')
            ->with([
                'statusLeads:id,nama',
                'branch:id,name',
            ])
            ->filterByUserRole();

        switch ($tipeQuotation) {
            case 'revisi':
                $query->whereHas('quotations', function ($q) {
                    $q->whereIn('status_quotation_id', [2, 3, 4, 5, 6, 7, 8])
                        ->whereNull('deleted_at');
                });
                break;

            case 'rekontrak':
            case 'addendum':
                $query->where('status_leads_id', 102)
                    ->whereHas('quotations', function ($q) {
                        $q->whereIn('status_quotation_id', [3, 6])
                            ->whereHas('sites', function ($siteQuery) {
                                $siteQuery->whereHas('pks', function ($pksQuery) {
                                    $pksQuery->where('is_aktif', 1);
                                });
                            });
                    });
                break;
        }

        $query->orderBy('created_at', 'desc');

        return $query->get()->map(function ($lead) {
            return [
                'id' => $lead->id,
                'nama_perusahaan' => $lead->nama_perusahaan,
                'pic' => $lead->pic,
                'wilayah' => $lead->branch->name ?? 'Unknown',
                'status_leads_id' => $lead->status_leads_id,
                'status_leads' => $lead->statusLeads->nama ?? 'Unknown',
                'customer_id' => $lead->customer_id,
            ];
        });
    }
}
