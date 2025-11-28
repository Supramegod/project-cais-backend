<?php

namespace App\DTO;

class CalculationSummary
{
    // Regular calculations
    public $total_sebelum_management_fee = 0;
    public $nominal_management_fee = 0;
    public $grand_total_sebelum_pajak = 0;
    public $ppn = 0;
    public $pph = 0;
    public $dpp = 0;
    public $total_invoice = 0;
    public $pembulatan = 0;
    
    // COSS calculations
    public $total_sebelum_management_fee_coss = 0;
    public $nominal_management_fee_coss = 0;
    public $grand_total_sebelum_pajak_coss = 0;
    public $ppn_coss = 0;
    public $pph_coss = 0;
    public $dpp_coss = 0;
    public $total_invoice_coss = 0;
    public $pembulatan_coss = 0;
    
    // Other calculations
    public $persen_bpjs_ketenagakerjaan = 0;
    public $persen_bpjs_kesehatan = 0;
    public $margin = 0;
    public $gpm = 0;
    public $margin_coss = 0;
    public $gpm_coss = 0;
    public $bunga_bank_total = 0;
    public $insentif_total = 0;
    public $total_base_manpower = 0;
    public $upah_pokok = 0;
    public $total_bpjs = 0;
    public $total_bpjs_kesehatan = 0;
    public $total_base_manpower_coss = 0;
    public $upah_pokok_coss = 0;
    public $total_bpjs_coss = 0;
    public $total_bpjs_kesehatan_coss = 0;
    
    // TAMBAHKAN: BPU calculations
    public $total_potongan_bpu = 0;
    public $potongan_bpu_per_orang = 0;
}