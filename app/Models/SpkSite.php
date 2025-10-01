<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpkSite extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_spk_site';

    protected $fillable = [
        'quotation_site_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function quotationSite()
    {
        return $this->belongsTo(QuotationSite::class, 'quotation_site_id');
    }
}