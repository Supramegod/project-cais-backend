<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_customer_activity';
    protected $fillable = [
        'tgl_activity',
        'quotation_id',
        'branch_id',
        'nomor',
        'leads_id',
        'spk_id',
        'pks_id',
        'tim_sales_id',
        'tim_sales_d_id',
        'notes',
        'tipe',
        'start',
        'end',
        'durasi',
        'tgl_realisasi',
        'jam_realisasi',
        'penerima',
        'notes_tipe',
        'link_bukti_foto',
        'notulen',
        'email',
        'ro_id',
        'ro',
        'crm_id',
        'crm',
        'status_leads_id',
        'jenis_visit',
        'jenis_visit_id',
        'is_activity',
        'user_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at'
    ];

    protected $dates = ['deleted_at'];

    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function timSales()
    {
        return $this->belongsTo(TimSales::class, 'tim_sales_id');
    }

    public function timSalesDetail()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }

    public function statusLeads()
    {
        return $this->belongsTo(StatusLeads::class, 'status_leads_id');
    }

    public function files()
    {
        return $this->hasMany(CustomerActivityFile::class, 'customer_activity_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    // CustomerActivity.php - tambahkan method ini

    public function pks()
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }

    public function spk()
    {
        return $this->belongsTo(Spk::class, 'spk_id');
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