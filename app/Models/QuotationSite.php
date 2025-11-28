<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationSite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_site';

    protected $fillable = [
        'quotation_id',
        'leads_id',
        'nama_site',
        'provinsi_id',
        'provinsi',
        'kota_id',
        'kota',
        'penempatan',
        'nominal_upah',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function spkSite()
    {
        return $this->hasOne(SpkSite::class, 'quotation_site_id');
    }

    public function site()
    {
        return $this->hasOne(Site::class, 'quotation_site_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
}