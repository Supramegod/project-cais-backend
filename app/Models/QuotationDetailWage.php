<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationDetailWage extends Model
{
    use SoftDeletes;

    protected $table = 'sl_quotation_detail_wages';

    protected $fillable = [
        'quotation_detail_id',
        'quotation_id',
        'upah',
        'hitungan_upah',
        'lembur',
        'nominal_lembur',
        'jenis_bayar_lembur',
        'jam_per_bulan_lembur',
        'lembur_ditagihkan',
        'kompensasi',
        'thr',
        'tunjangan_holiday',
        'nominal_tunjangan_holiday',
        'jenis_bayar_tunjangan_holiday',
        'created_by',
        'updated_by',
        'deleted_by',
    ];



    /**
     * Relationships
     */
    public function quotationDetail()
    {
        return $this->belongsTo(QuotationDetail::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    // HAPUS relasi managementFee karena kolom management_fee_id dihapus
}