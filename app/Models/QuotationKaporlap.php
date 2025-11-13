<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationKaporlap extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_kaporlap';
    protected $fillable = [
        'quotation_detail_id',
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
    public function scopeByBarangAndDetail($query, $barangId, $detailId)
    {
        return $query->whereNull('deleted_at')
            ->where('barang_id', $barangId)
            ->where('quotation_detail_id', $detailId);
    }
}