<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadsKebutuhan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_leads_kebutuhan';
    protected $fillable = [
        'leads_id',
        'kebutuhan_id',
        'tim_sales_id',      // Tambahkan ini
        'tim_sales_d_id',    // Tambahkan ini
        'deleted_at',
        'deleted_by',
    ];

    public $timestamps = false; // kalau tabel pivot nggak punya created_at, updated_at
    // Relasi ke tim sales
    public function timSales()
    {
        return $this->belongsTo(TimSales::class, 'tim_sales_id');
    }

    public function timSalesD()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }

    // Relasi ke kebutuhan
    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class, 'kebutuhan_id');
    }

    // Relasi ke lead
    public function lead()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }
    // Tambahkan di dalam class LeadsKebutuhan di file App\Models\LeadsKebutuhan.php

    public function salesActivities()
    {
        return $this->hasMany(SalesActivity::class, 'leads_kebutuhan_id');
    }

}

