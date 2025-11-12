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
        'management_fee_id',
        'persentase',
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
        'is_ppn',
        'ppn_pph_dipotong',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'nominal_lembur' => 'decimal:2',
        'nominal_tunjangan_holiday' => 'decimal:2',
        'persentase' => 'decimal:2',
        'jam_per_bulan_lembur' => 'decimal:2',
        // Hapus casting untuk is_ppn karena sekarang string
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

    public function managementFee()
    {
        return $this->belongsTo(ManagementFee::class, 'management_fee_id');
    }
}