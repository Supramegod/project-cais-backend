<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_salary_rule';
    protected $fillable = [
        'nama_salary_rule',
        'cutoff',
        'cutoff_awal',
        'cutoff_akhir',
        'crosscheck_absen',
        'crosscheck_absen_awal',
        'crosscheck_absen_akhir',
        'pengiriman_invoice',
        'pengiriman_invoice_awal',
        'pengiriman_invoice_akhir',
        'perkiraan_invoice_diterima',
        'perkiraan_invoice_diterima_awal',
        'perkiraan_invoice_diterima_akhir',
        'pembayaran_invoice',
        'tgl_pembayaran_invoice',
        'rilis_payroll',
        'tgl_rilis_payroll',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = ['updated_at', 'deleted_at'];
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