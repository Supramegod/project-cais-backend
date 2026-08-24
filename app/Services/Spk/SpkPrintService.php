<?php

namespace App\Services\Spk;

use App\Models\Company;
use App\Models\JabatanPic;
use App\Models\QuotationDetail;
use App\Models\QuotationPic;
use App\Models\Spk;
use App\Models\SpkSite;
use Carbon\Carbon;

/**
 * Handles SPK print/PDF data preparation.
 */
class SpkPrintService
{
    /**
     * Data cetak SPK.
     */
    public function cetakSpk(int $id): ?array
    {
        $now = Carbon::now()->isoFormat('DD MMMM Y');

        $spk = Spk::with(['quotation', 'leads'])->find($id);
        if (! $spk) {
            return null;
        }

        $spkSites = SpkSite::where('spk_id', $id)->get();

        if ($spkSites->isEmpty()) {
            throw new \Exception('No SPK sites found');
        }

        $quotation = $spk->quotation;
        $leads = $spk->leads;

        $spk->unsetRelation('quotation');
        $spk->unsetRelation('leads');

        // Get jabatan PIC
        if ($leads->jabatan) {
            $jabatanPic = JabatanPic::find($leads->jabatan);
            if ($jabatanPic) {
                $leads->jabatan_nama = $jabatanPic->nama_jabatan;
            }
        }

        // Process quotation data
        if ($quotation) {
            $quotation->tgl_penempatan_formatted = $quotation->tgl_penempatan
                ? Carbon::parse($quotation->tgl_penempatan)->isoFormat('D MMMM Y')
                : null;

            $quotation->details = QuotationDetail::where('quotation_id', $quotation->id)
                ->whereNull('deleted_at')
                ->get();

            $quotation->total_hc = $quotation->details->sum('jumlah_hc');

            $quotation->pic = QuotationPic::where('quotation_id', $quotation->id)
                ->where('is_kuasa', 1)
                ->whereNull('deleted_at')
                ->first();
        }

        $company = $quotation ? Company::find($quotation->company_id) : null;

        return [
            'now' => $now,
            'spk' => $spk,
            'spk_sites' => $spkSites,
            'quotation' => $quotation,
            'leads' => $leads,
            'company' => $company,
        ];
    }
}
