<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationDevices extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_devices';
    protected $fillable = [
        'quotation_detail_id',
        'quotation_site_id',
        'quotation_id',
        'barang_id',
        'jumlah',
        'harga',
        'nama',
        'jenis_barang_id',
        'jenis_barang',
        'created_by',
    ];
    protected $guarded = [];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }
    public function scopeByBarangAndQuotation($query, $barangId, $quotationId)
    {
        return $query->whereNull('deleted_at')
            ->where('barang_id', $barangId)
            ->where('quotation_id', $quotationId);
    }
}