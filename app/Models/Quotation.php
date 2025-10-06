<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation';
    protected $fillable = [
        'leads_id', 'nomor', 'tanggal', 'status_quotation_id', 'total_harga',
        'created_by', 'updated_by', 'deleted_by', 'npwp', 'tgl_penempatan',
        'company_id', 'kebutuhan', 'kebutuhan_id', 'jumlah_site', 'revisi',
        'alasan_revisi', 'quotation_asal_id', 'is_aktif', 'step', 'top', 'persentase',
        'tgl_quotation', 'nama_perusahaan', 'tim_sales_id', 'tim_sales_d_id','tgl_quotation',
        'ot1', 'ot2', 'ot3' // TAMBAHKAN FIELD INI
    ];
    protected $dates = ['deleted_at'];

    // Tambahan: Accessor untuk format tanggal
    public function getTglQuotationAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    public function getTglPenempatanAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    public function getCreatedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    public function getUpdatedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    // Relasi yang sudah ada
    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function pks()
    {
        return $this->hasOne(Pks::class, 'quotation_id');
    }

    public function spk()
    {
        return $this->hasOne(Spk::class, 'quotation_id');
    }

    public function quotationDetails()
    {
        return $this->hasMany(QuotationDetail::class, 'quotation_id');
    }

    public function sites()
    {
        return $this->hasMany(Site::class, 'quotation_id');
    }

    public function statusQuotation()
    {
        return $this->belongsTo(StatusQuotation::class, 'status_quotation_id');
    }

    // RELASI BARU YANG DIPERLUKAN:

    // Relasi ke QuotationSite
    public function quotationSites()
    {
        return $this->hasMany(QuotationSite::class, 'quotation_id');
    }

    // Relasi ke Company
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Relasi ke QuotationPic
    public function quotationPics()
    {
        return $this->hasMany(QuotationPic::class, 'quotation_id');
    }

    // Relasi ke QuotationDetailRequirement
    public function quotationDetailRequirements()
    {
        return $this->hasMany(QuotationDetailRequirement::class, 'quotation_id');
    }

    // Relasi ke QuotationDetailHpp
    public function quotationDetailHpps()
    {
        return $this->hasMany(QuotationDetailHpp::class, 'quotation_id');
    }

    // Relasi ke QuotationDetailCoss
    public function quotationDetailCosses()
    {
        return $this->hasMany(QuotationDetailCoss::class, 'quotation_id');
    }

    // Relasi ke QuotationDetailTunjangan
    public function quotationDetailTunjangans()
    {
        return $this->hasMany(QuotationDetailTunjangan::class, 'quotation_id');
    }

    // Relasi ke QuotationKaporlap
    public function quotationKaporlaps()
    {
        return $this->hasMany(QuotationKaporlap::class, 'quotation_id');
    }

    // Relasi ke QuotationDevices
    public function quotationDevices()
    {
        return $this->hasMany(QuotationDevices::class, 'quotation_id');
    }

    // Relasi ke QuotationChemical
    public function quotationChemicals()
    {
        return $this->hasMany(QuotationChemical::class, 'quotation_id');
    }

    // Relasi ke QuotationOhc
    public function quotationOhcs()
    {
        return $this->hasMany(QuotationOhc::class, 'quotation_id');
    }

    // Relasi ke QuotationAplikasi
    public function quotationAplikasis()
    {
        return $this->hasMany(QuotationAplikasi::class, 'quotation_id');
    }

    // Relasi ke QuotationKerjasama
    public function quotationKerjasamas()
    {
        return $this->hasMany(QuotationKerjasama::class, 'quotation_id');
    }

    // Relasi ke QuotationTraining
    public function quotationTrainings()
    {
        return $this->hasMany(QuotationTraining::class, 'quotation_id');
    }

    // Relasi ke Tim Sales Detail
    public function timSalesDetail()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }

    // ACCESSOR/METHOD BARU YANG DIPERLUKAN:

    // Accessor untuk mendapatkan PIC utama (is_kuasa = 1)
    public function getPicAttribute()
    {
        return $this->quotationPics()->where('is_kuasa', 1)->first();
    }

    // Accessor untuk format tanggal penempatan (digunakan di blade)
    public function getTglPenempatanFormattedAttribute()
    {
        return $this->tgl_penempatan ? Carbon::parse($this->tgl_penempatan)->isoFormat('D MMMM Y') : null;
    }

    // Accessor untuk details (alias untuk quotationDetails - digunakan di blade)
    public function getDetailsAttribute()
    {
        return $this->quotationDetails;
    }

    // Method untuk mengecek apakah quotation memiliki SPK
    public function hasSpk()
    {
        return $this->spk()->exists();
    }

    // Method untuk mengecek apakah quotation aktif
    public function isActive()
    {
        return $this->is_aktif == 1;
    }

    // Scope untuk quotation aktif
    public function scopeActive($query)
    {
        return $query->where('is_aktif', 1);
    }

    // Scope untuk quotation yang belum dihapus
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted_at');
    }

    // Method untuk mendapatkan total HC dari quotation details
    public function getTotalHcAttribute()
    {
        return $this->quotationDetails->sum('jumlah_hc');
    }

    // Relasi ke quotation asal (untuk revisi)
    public function quotationAsal()
    {
        return $this->belongsTo(Quotation::class, 'quotation_asal_id');
    }

    // Relasi ke quotation revisi
    public function quotationRevisions()
    {
        return $this->hasMany(Quotation::class, 'quotation_asal_id');
    }
}