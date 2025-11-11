<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_detail';
    protected $guarded = [];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }
    // App/Models/QuotationDetail.php
    public function quotationDetailRequirements()
    {
        return $this->hasMany(QuotationDetailRequirement::class, 'quotation_detail_id', 'id');
    }

    public function quotationDetailTunjangans()
    {
        return $this->hasMany(QuotationDetailTunjangan::class, 'quotation_detail_id', 'id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id', 'id');
    }

}