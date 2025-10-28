<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_customers';
    protected $fillable = [
        'leads_id',
        'accurate_id',
        'nomor',
        'tgl_customer',
        'tim_sales_id',
        'tim_sales_d_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at', 'tgl_customer'];

    // Relasi ke Leads
    public function lead()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    // Relasi ke TimSales
    public function timSales()
    {
        return $this->belongsTo(TimSales::class, 'tim_sales_id');
    }

    // Relasi ke TimSalesDetail
    public function timSalesD()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }
}