<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_site';
    protected $fillable = [
        'spk_site_id',
        'quotation_site_id',
        'leads_id',
        'nama_site',
        'provinsi_id',
        'provinsi',
        'kota_id',
        'kota',
        'status_site_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function spkSite()
    {
        return $this->belongsTo(SpkSite::class, 'spk_site_id');
    }

    public function quotationSite()
    {
        return $this->belongsTo(QuotationSite::class, 'quotation_site_id');
    }

    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function statusSite()
    {
        return $this->belongsTo(StatusSite::class, 'status_site_id');
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