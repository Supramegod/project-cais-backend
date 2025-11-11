<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationDetailHpp extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_detail_hpp';
    protected $fillable = [
        'quotation_id',
        'quotation_detail_id',
        'position_id',
        'leads_id',
        'jumlah_hc',
        'gaji_pokok',
        'total_tunjangan',
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
        'total_biaya_per_personil',
        'total_biaya_all_personil',
        'management_fee',
        'persen_management_fee',
        'provisi_ohc',
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