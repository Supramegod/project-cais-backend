<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationKerjasama extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_kerjasama';

    protected $fillable = [
        'quotation_id',
        'perjanjian',
        'is_delete',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // Relationship dengan Quotation
    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    // Scope untuk data yang aktif (tidak terhapus)
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    // Scope untuk quotation tertentu
    public function scopeByQuotation($query, $quotationId)
    {
        return $query->where('quotation_id', $quotationId);
    }
}