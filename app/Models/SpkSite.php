<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpkSite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_spk_site';

    protected $fillable = [
        'spk_id',
        'quotation_id',
        'quotation_site_id',
        'leads_id',
        'nama_site',
        'provinsi_id',
        'provinsi',
        'kota_id',
        'kota',
        'ump',
        'umk',
        'nominal_upah',
        'penempatan',
        'kebutuhan_id',
        'kebutuhan',
        'jenis_site',
        'nomor_quotation',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function quotationSite()
    {
        return $this->belongsTo(QuotationSite::class, 'quotation_site_id');
    }

    public function spk()
    {
        return $this->belongsTo(Spk::class, 'spk_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function site()
    {
        return $this->hasOne(Site::class, 'spk_site_id');
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