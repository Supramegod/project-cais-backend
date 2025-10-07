<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Spk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_spk';
    protected $fillable = [
        'leads_id',
        'quotation_id',
        'nomor',
        'tgl_spk',
        'nama_perusahaan',
        'tim_sales_id',
        'tim_sales_d_id',
        'link_spk_disetujui',
        'status_spk_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function timSales()
    {
        return $this->belongsTo(TimSales::class, 'tim_sales_id');
    }

    public function timSalesDetail()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }

    public function statusSpk()
    {
        return $this->belongsTo(StatusSpk::class, 'status_spk_id');
    }

    public function spkSites()
    {
        return $this->hasMany(SpkSite::class, 'spk_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'spk_id');
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

    public function getTglSpkAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
}