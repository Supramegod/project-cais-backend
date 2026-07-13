<?php

namespace App\Services\Pks;

use App\Models\Leads;
use App\Models\QuotationSite;
use App\Models\SpkSite;
use Illuminate\Http\Request;

class PksLeadsService
{
    public function getAvailableLeadsData(Request $request)
    {
        $query = Leads::filterByUserRole()
            ->whereHas('spkSites', function ($query) {
                $query->whereNull('sl_spk_site.deleted_at')
                    ->whereHas('spk', function ($subQuery) {
                        $subQuery->whereNull('sl_spk.deleted_at');
                    })
                    ->whereDoesntHave('site', function ($siteQuery) {
                        $siteQuery->whereNull('sl_site.deleted_at');
                    });
            })
            ->select('id', 'nomor', 'nama_perusahaan', 'provinsi', 'kota', 'created_by')
            ->distinct()
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchBy = $request->get('search_by', 'nama_perusahaan');

            if ($searchBy === 'nama_perusahaan') {
                $searchTerm = str_contains($searchTerm, ' ')
                    ? '"' . $searchTerm . '"'
                    : $searchTerm . '*';
                $query->whereRaw('MATCH(nama_perusahaan) AGAINST(? IN BOOLEAN MODE)', [$searchTerm]);
            } elseif (in_array($searchBy, ['nomor', 'provinsi', 'kota', 'created_by'], true)) {
                $query->where($searchBy, 'LIKE', '%' . $searchTerm . '%');
            }
        }

        return $query->paginate($request->get('per_page', 15));
    }

    public function getAvailableSitesData($leadsId, $tipe = 'baru')
    {
        $isBaru = ($tipe === 'baru');
        $isAddendum = ($tipe === 'addendum');
        $isRekontrak = ($tipe === 'rekontrak');

        if ($isBaru) {
            $query = SpkSite::with([
                'spk',
                'quotation' => function ($q) use ($leadsId) {
                    $q->where('leads_id', $leadsId)
                        ->with(['company', 'salaryRule', 'ruleThr']);
                },
                'leads',
            ])
                ->where('leads_id', $leadsId)
                ->whereHas('spk', function ($q) {
                    $q->whereNull('deleted_at');
                });

            $orderTable = 'sl_spk';
            $orderColumn = 'spk_id';
        } else {
            $tipeQuotation = $isAddendum ? 'addendum' : 'rekontrak';

            $query = QuotationSite::with([
                'quotation' => function ($q) use ($leadsId, $tipeQuotation) {
                    $q->where('leads_id', $leadsId)
                        ->whereIn('tipe_quotation', [$tipeQuotation, 'revisi'])
                        ->with(['company', 'salaryRule', 'ruleThr']);
                },
                'leads',
            ])
                ->where('leads_id', $leadsId)
                ->whereHas('quotation', function ($q) use ($leadsId, $tipeQuotation) {
                    $q->where('leads_id', $leadsId)
                        ->whereIn('tipe_quotation', [$tipeQuotation, 'revisi'])
                        ->whereNull('deleted_at');
                });

            $orderTable = 'sl_quotation';
            $orderColumn = 'quotation_id';
        }

        $query->whereNull('deleted_at');

        if ($isBaru) {
            $query->whereDoesntHave('site');
        }

        return $query->select('id', 'nama_site', 'provinsi', 'kota', 'penempatan', 'quotation_id', ($isBaru ? 'spk_id' : 'leads_id'))
            ->orderBy(function ($q) use ($orderTable, $orderColumn) {
                $q->select('nomor')
                    ->from($orderTable)
                    ->whereColumn($orderTable . '.id', $orderColumn)
                    ->limit(1);
            }, 'asc')
            ->whereHas('quotation', function ($q) {
                $q->whereNotIn('status_quotation_id', [1, 2]);
            })
            ->get()
            ->filter(function ($site) {
                return $site->quotation !== null;
            })
            ->map(function ($site) use ($isBaru) {
                $quotation = $site->quotation;
                $companyRelation = $quotation ? $quotation->getRelation('company') : null;

                return [
                    'id' => $site->id,
                    'nomor' => $isBaru ? ($site->spk->nomor ?? null) : ($quotation->nomor ?? null),
                    'nama_site' => $site->nama_site,
                    'provinsi' => $site->provinsi,
                    'kota' => $site->kota,
                    'penempatan' => $site->penempatan,
                    'mulai_kontrak' => $quotation?->mulai_kontrak,
                    'kontrak_selesai' => $quotation?->kontrak_selesai,
                    'durasi_kerjasama' => $quotation?->durasi_kerjasama,
                    'company' => ($companyRelation instanceof \Illuminate\Database\Eloquent\Model) ? [
                        'id' => $companyRelation->id,
                        'name' => $companyRelation->name ?? $companyRelation->nama ?? null,
                        'code' => $companyRelation->code ?? null,
                    ] : null,
                    'salary_rule' => $quotation?->salaryRule ? [
                        'id' => $quotation->salaryRule->id,
                        'nama' => $quotation->salaryRule->nama_salary_rule ?? null,
                        'cutoff' => $quotation->salaryRule->cutoff,
                        'pembayaran_invoice' => $quotation->salaryRule->pembayaran_invoice,
                        'rilis_payroll' => $quotation->salaryRule->rilis_payroll,
                    ] : null,
                    'rule_thr' => $quotation?->ruleThr ? [
                        'id' => $quotation->ruleThr->id,
                        'nama' => $quotation->ruleThr->nama ?? null,
                        'hari_penagihan_invoice' => $quotation->ruleThr->hari_penagihan_invoice,
                        'hari_pembayaran_invoice' => $quotation->ruleThr->hari_pembayaran_invoice,
                        'hari_rilis_thr' => $quotation->ruleThr->hari_rilis_thr,
                    ] : null,
                    'quotation_id' => $quotation?->id,
                    'nomor_quotation' => $quotation?->nomor,
                ];
            })
            ->values();
    }
}
