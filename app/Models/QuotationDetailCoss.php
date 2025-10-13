<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationDetailCoss extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_detail_coss';
    protected $fillable = [
        'quotation_id',
        'quotation_detail_id',
        'position_id',
        'leads_id',
        'jumlah_hc',
        'gaji_pokok',
        'total_tunjangan',
        'total_base_manpower',
        'tunjangan_hari_raya',
        'kompensasi',
        'tunjangan_hari_libur_nasional',
        'lembur',
        'bpjs_jkk',
        'bpjs_jkm',
        'bpjs_jht',
        'bpjs_jp',
        'bpjs_ks',
        'persen_bpjs_jkk',
        'persen_bpjs_jkm',
        'persen_bpjs_jht',
        'persen_bpjs_jp',
        'persen_bpjs_ks',
        'provisi_seragam',
        'provisi_peralatan',
        'provisi_chemical',
        'total_exclude_base_manpower',
        'bunga_bank',
        'insentif',
        'management_fee',
        'persen_bunga_bank',
        'persen_insentif',
        'persen_management_fee',
        'grand_total',
        'ppn',
        'pph',
        'total_invoice',
        'pembulatan',
        'is_pembulatan',
        'created_by',
        'updated_by'
    ];
    protected $dates = ['deleted_at'];

    // Relasi ke Quotation
    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    // Relasi ke QuotationDetail
    public function quotationDetail()
    {
        return $this->belongsTo(QuotationDetail::class, 'quotation_detail_id');
    }

    // Relasi ke Leads
    public function lead()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }
}