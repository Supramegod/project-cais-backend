<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_submission';

    protected $fillable = [
        'leads_id',
        'customer_id',
        'nomor',
        'tgl_leads',
        'nama_perusahaan',
        'telp_perusahaan',
        'jenis_perusahaan_id',
        'branch_id',
        'platform_id',
        'kebutuhan_id',
        'alamat',
        'notes',
        'pic',
        'jabatan',
        'jabatan_id',
        'no_telp',
        'email',
        'status_leads_id',
        'tim_sales_id',
        'tim_sales_d_id',
        'provinsi_id',
        'provinsi',
        'kota_id',
        'kota',
        'kecamatan_id',
        'kecamatan',
        'kelurahan_id',
        'kelurahan',
        'is_aktif',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

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
}
