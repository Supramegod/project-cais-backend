<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubmissionV2 extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_submission_v2';

    protected $guarded = ['id'];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function platform()
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    public function statusLeads()
    {
        return $this->belongsTo(StatusLeads::class, 'status_leads_id');
    }

    public function timSalesD()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }

    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class, 'kebutuhan_id');
    }
}
