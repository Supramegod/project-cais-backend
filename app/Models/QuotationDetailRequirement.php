<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationDetailRequirement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_detail_requirement';

    protected $fillable = [
        'quotation_id',
        'quotation_detail_id',
        'requirement',
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
}