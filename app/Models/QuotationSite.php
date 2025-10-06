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
    protected $guarded = [];

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
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

    /**
     * Format updated_at jadi dd-mm-YYYY
     */
    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

}