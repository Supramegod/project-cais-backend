<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leads extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_leads';
    protected $fillable = [
        'nama_perusahaan', 'branch_id', 'kebutuhan_id', 'tim_sales_id', 'tim_sales_d_id',
        'status_leads_id', 'ro_id', 'ro', 'ro_id_1', 'ro_id_2', 'ro_id_3', 'crm_id', 'crm',
        'crm_id_1', 'crm_id_2', 'company_id', 'jenis_perusahaan_id', 'nomor',
        'created_by', 'updated_by', 'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class, 'kebutuhan_id');
    }

    public function timSales()
    {
        return $this->belongsTo(TimSales::class, 'tim_sales_id');
    }

    public function timSalesD()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }

    public function statusLeads()
    {
        return $this->belongsTo(StatusLeads::class, 'status_leads_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function jenisPerusahaan()
    {
        return $this->belongsTo(JenisPerusahaan::class, 'jenis_perusahaan_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'leads_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'leads_id');
    }

    public function pks()
    {
        return $this->hasMany(Pks::class, 'leads_id');
    }
}