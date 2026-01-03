<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationDetailTunjangan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_detail_tunjangan';

    protected $fillable = [
        'quotation_id',
        'quotation_detail_id',
        'nama_tunjangan',
        'nominal',
        'nominal_coss',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function quotationDetail()
    {
        return $this->belongsTo(QuotationDetail::class, 'quotation_detail_id');
    }

    public function scopeDistinctTunjanganByQuotation($query, $quotationId)
    {
        return $query->select('nama_tunjangan as nama')
            ->whereNull('deleted_at')
            ->where('quotation_id', $quotationId)
            ->distinct();
    }
}