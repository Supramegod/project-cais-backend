<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation';
    protected $fillable = [
        'leads_id', 'nomor', 'tanggal', 'status_quotation_id', 'total_harga',
        'created_by', 'updated_by', 'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function pks()
    {
        return $this->hasOne(Pks::class, 'quotation_id');
    }

    public function spk()
    {
        return $this->hasOne(Spk::class, 'quotation_id');
    }

    public function quotationDetails()
    {
        return $this->hasMany(QuotationDetail::class, 'quotation_id');
    }

    public function sites()
    {
        return $this->hasMany(Site::class, 'quotation_id');
    }

    public function statusQuotation()
    {
        return $this->belongsTo(StatusQuotation::class, 'status_quotation_id');
    }
}