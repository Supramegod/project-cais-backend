<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationMargin extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_margin';

    protected $fillable = [
        'quotation_id',
        'leads_id',
        'nominal_hpp',
        'nominal_harga_pokok',
        'ppn_hpp',
        'ppn_harga_pokok',
        'total_biaya_hpp',
        'total_biaya_harga_pokok',
        'margin_hpp',
        'margin_harga_pokok',
        'gpm_hpp',
        'gpm_harga_pokok',
        'created_by',
        'updated_by',
        'deleted_by' // Ditambahkan untuk konsistensi
    ];

    protected $dates = ['deleted_at'];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function lead()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }
}