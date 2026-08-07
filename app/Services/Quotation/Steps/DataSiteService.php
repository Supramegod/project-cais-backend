<?php

namespace App\Services\Quotation\Steps;

use App\Models\Quotation;
use App\Models\QuotationSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataSiteService
{
    public function execute(Quotation $quotation, Request $request): void
    {
        // For simplicity, we assume single site handling as standard if not explicitly array.
        // If the payload contains an array of sites, it could be handled here.
        // Based on the fields provided, we'll upsert the first site for the quotation.
        
        $site = QuotationSite::where('quotation_id', $quotation->id)->first();
        
        if (!$site) {
            $site = new QuotationSite();
            $site->quotation_id = $quotation->id;
            $site->leads_id = $quotation->leads_id;
            $site->created_by = Auth::user()->full_name;
        }

        $site->nama_perusahaan = $request->input('nama_perusahaan');
        $site->kota = $request->input('kota');
        // also store kota_id if provided or derived, for now just follow existing structure if they send text
        if ($request->has('kota_id')) {
            $site->kota_id = $request->input('kota_id');
        }
        $site->cabang = $request->input('cabang');
        if ($request->has('jenis_perusahaan')) {
            $site->jenis_perusahaan = $request->input('jenis_perusahaan');
            if ($request->has('jenis_perusahaan_id')) {
                $site->jenis_perusahaan_id = $request->input('jenis_perusahaan_id');
            }
        }
        $site->status_gedung = $request->input('status_gedung');
        $site->alamat_lengkap = $request->input('alamat_lengkap');
        $site->link_maps = $request->input('link_maps');
        $site->jumlah_lantai = $request->input('jumlah_lantai');
        $site->luas_estimasi_area = $request->input('luas_estimasi_area');
        $site->hari_operasional = $request->input('hari_operasional');
        $site->pengaturan_shift_kerja = $request->input('pengaturan_shift_kerja');
        $site->jumlah_hc = $request->input('jumlah_headcount', $request->input('jumlah_hc'));
        $site->area_khusus = $request->input('area_khusus');
        $site->catatan = $request->input('catatan');

        $site->updated_by = Auth::user()->full_name;
        $site->save();
        
        $quotation->calculated_at = null;
        $quotation->updated_by = Auth::user()->full_name;
        $quotation->save();
    }
}
