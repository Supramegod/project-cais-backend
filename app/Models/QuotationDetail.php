<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_detail';

    protected $fillable = [
        'quotation_id',
        'quotation_site_id',
        'nama_site',
        'position_id',
        'jabatan_kebutuhan',
        'jumlah_hc',
        'nominal_upah',
        'penjamin_kesehatan',
        'is_bpjs_jkk',
        'is_bpjs_jkm',
        'is_bpjs_jht',
        'is_bpjs_jp',
        'nominal_takaful',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function quotationDetailRequirements()
    {
        return $this->hasMany(QuotationDetailRequirement::class, 'quotation_detail_id', 'id');
    }

    public function quotationDetailTunjangans()
    {
        return $this->hasMany(QuotationDetailTunjangan::class, 'quotation_detail_id', 'id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id', 'id');
    }

    public function wage()
    {
        return $this->hasOne(QuotationDetailWage::class, 'quotation_detail_id');
    }

    public function quotationSite()
    {
        return $this->belongsTo(QuotationSite::class, 'quotation_site_id');
    }
    // TAMBAHKAN RELASI INI
    public function quotationDetailHpps()
    {
        return $this->hasMany(QuotationDetailHpp::class, 'quotation_detail_id');
    }

    public function quotationDetailCosses()
    {
        return $this->hasMany(QuotationDetailCoss::class, 'quotation_detail_id');
    }
}