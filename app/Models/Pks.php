<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pks extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_pks';
    protected $fillable = [
        'leads_id', 'quotation_id', 'kontrak_awal', 'kontrak_akhir', 'status_pks_id',
        'created_by', 'updated_by', 'deleted_by'
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

    public function statusPks()
    {
        return $this->belongsTo(StatusPks::class, 'status_pks_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'pks_id');
    }
}