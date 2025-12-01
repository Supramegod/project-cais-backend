<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation';
    protected $fillable = [
        // Kolom yang sudah ada
        'leads_id',
        'nomor',
        'status_quotation_id',
        'total_harga',
        'created_by',
        'updated_by',
        'deleted_by',
        'npwp',
        'tgl_penempatan',
        'company_id',
        'kebutuhan',
        'kebutuhan_id',
        'jumlah_site',
        'revisi',
        'alasan_revisi',
        'quotation_asal_id',
        'is_aktif',
        'step',
        'top',
        'persentase',
        'tgl_quotation',
        'nama_perusahaan',
        'tim_sales_id',
        'tim_sales_d_id',
        'ot1',
        'ot2',
        'ot3',
        // Kolom baru yang ditambahkan
        'quotation_client_id',
        'layanan_id',
        'jenis_kontrak',
        'layanan',
        'nama_site',
        'company',
        'mulai_kontrak',
        'kontrak_selesai',
        'penempatan',
        'salary_rule_id',
        'tipe_hari_invoice',
        'jumlah_hari_invoice',
        'alamat_npwp',
        'pic_invoice',
        'telp_pic_invoice',
        'email_pic_invoice',
        'evaluasi_kontrak',
        'durasi_kerjasama',
        'durasi_karyawan',
        'evaluasi_karyawan',
        'rule_thr_id',
        'materai',
        'shift_kerja',
        'hari_kerja',
        'jam_kerja',
        'mulai_kerja',
        'selesai_kerja',
        'sistem_kerja',
        'cuti',
        'hari_cuti_kematian',
        'hari_istri_melahirkan',
        'hari_cuti_menikah',
        'gaji_saat_cuti',
        'prorate',
        'kunjungan_operasional',
        'kunjungan_tim_crm',
        'keterangan_kunjungan_tim_crm',
        'keterangan_kunjungan_operasional',
        'training',
        'kompensasi',
        'joker_reliever',
        'syarat_invoice',
        'lembur',
        'nominal_lembur',
        'jenis_bayar_lembur',
        'jam_per_bulan_lembur',
        'alamat_penagihan_invoice',
        'catatan_site',
        'status_serikat',
        'tunjangan_holiday',
        'nominal_tunjangan_holiday',
        'jenis_bayar_tunjangan_holiday',
        'ppn_pph_dipotong',
        'penagihan',
        'provinsi_id',
        'provinsi',
        'kota_id',
        'kota',
        'upah',
        'nominal_upah',
        'hitungan_upah',
        'management_fee_id',
        'jenis_perusahaan_id',
        'jenis_perusahaan',
        'bidang_perusahaan_id',
        'bidang_perusahaan',
        'resiko',
        'penjamin',
        'program_bpjs',
        'nominal_takaful',
        'persen_insentif',
        'persen_bunga_bank',
        'note_harga_jual',
        'lembur_ditagihkan',
        'is_ppn',
        'pks_id',
        'ot4',
        'ot5',
        'is_sandbox',
        'quotation_referensi_id',
        'tipe_quotation'
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
    // File: app/Models/Quotation.php

    public function managementFee()
    {
        return $this->belongsTo(ManagementFee::class, 'management_fee_id');
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
    // Alternatif yang lebih clean di Quotation.php
    public function scopeByUserRole($query, $user = null)
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return $query;
        }

        // 🌟 PERUBAHAN: TAMBAHKAN ROLE ID 2 (SUPERADMIN) DI SINI
        if ($user->role_id == 2) {
            // Superadmin dapat mengakses SEMUA data tanpa filter.
            return $query;
        }
        // -----------------------------------------------------

        // Sales division
        if (in_array($user->role_id, [29, 30, 31, 32, 33])) {
            if ($user->role_id == 29) {
                // Sales
                $query->whereHas('leads.timSalesD', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } elseif ($user->role_id == 31) {
                // SPV Sales - menggunakan model dengan scope
                $tim = TimSalesDetail::where('user_id', $user->id)->first();
                if ($tim) {
                    $memberSales = TimSalesDetail::byTeam($tim->tim_sales_id)
                        ->active() // hanya yang aktif
                        ->pluck('user_id');
                    $query->whereHas('leads.timSalesD', function ($q) use ($memberSales) {
                        $q->whereIn('user_id', $memberSales);
                    });
                }
            }
        } elseif (in_array($user->role_id, [4, 5])) {
            // RO
            $query->whereHas('leads', function ($q) use ($user) {
                $q->where('ro_id', $user->id);
            });
        }

        return $query;
    }

    /**
     * Scope untuk filter by date range
     */
    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ?: Carbon::now()->startOfMonth()->subMonths(3)->toDateString();
        $endDate = $endDate ?: Carbon::now()->toDateString();

        return $query->whereBetween('tgl_quotation', [$startDate, $endDate]);
    }

    /**
     * Scope untuk filter by company
     */
    public function scopeByCompany($query, $companyId = null)
    {
        if ($companyId) {
            return $query->where('company_id', $companyId);
        }
        return $query;
    }

    /**
     * Scope untuk filter by kebutuhan
     */
    public function scopeByKebutuhan($query, $kebutuhanId = null)
    {
        if ($kebutuhanId) {
            return $query->where('kebutuhan_id', $kebutuhanId);
        }
        return $query;
    }

    /**
     * Scope untuk filter by status
     */
    public function scopeByStatus($query, $statusId = null)
    {
        if ($statusId) {
            return $query->where('status_quotation_id', $statusId);
        }
        return $query;
    }
    // Tambahkan relasi
    public function quotationReferensi()
    {
        return $this->belongsTo(Quotation::class, 'quotation_referensi_id');
    }

    public function quotationTurunan()
    {
        return $this->hasMany(Quotation::class, 'quotation_referensi_id');
    }
    public function wage()
    {
        return $this->hasOne(QuotationDetailWage::class);
    }
}
