<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Leads extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_leads';
    protected $fillable = [
        'nama_perusahaan',
        'leads_id',
        'customer_id',
        'branch_id',
        'kebutuhan_id',
        'tim_sales_id',
        'tim_sales_d_id',
        'status_leads_id',
        'ro_id',
        'ro',
        'ro_id_1',
        'ro_id_2',
        'ro_id_3',
        'crm_id',
        'crm',
        'crm_id_1',
        'crm_id_2',
        'company_id',
        'jenis_perusahaan_id',
        'nomor',
        'created_by',
        'updated_by',
        'deleted_by',
        'tgl_leads',
        'telp_perusahaan',
        'jenis_perusahaan',
        'bidang_perusahaan_id',
        'bidang_perusahaan',
        'platform_id',
        'alamat',
        'pic',
        'jabatan',
        'no_telp',
        'email',
        'pma',
        'notes',
        'provinsi_id',
        'provinsi',
        'kota_id',
        'kota',
        'kecamatan_id',
        'kecamatan',
        'kelurahan_id',
        'kelurahan',
        'benua_id',
        'benua',
        'negara_id',
        'customer_active',
        'is_aktif',
        'negara'
    ];

    protected $dates = ['deleted_at'];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
    // Leads.php

    public function kebutuhan()
    {
        return $this->belongsToMany(
            Kebutuhan::class,          // model tujuan
            'sl_leads_kebutuhan',      // nama tabel pivot
            'leads_id',                // foreign key di tabel pivot
            'kebutuhan_id'             // foreign key ke tabel kebutuhan
        )
            ->withPivot('tim_sales_id', 'tim_sales_d_id')
            ->wherePivot('deleted_at', null); // Tambahkan kondisi ini
    }


    public function timSales()
    {
        return $this->belongsTo(TimSales::class, 'tim_sales_id');
    }

    public function timSalesD()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }

    public function statusLeads()
    {
        return $this->belongsTo(StatusLeads::class, 'status_leads_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function jenisPerusahaan()
    {
        return $this->belongsTo(JenisPerusahaan::class, 'jenis_perusahaan_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'leads_id');
    }
    public function spkSites()
    {
        return $this->hasMany(SpkSite::class, 'leads_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'leads_id');
    }

    public function pks()
    {
        return $this->hasMany(Pks::class, 'leads_id');
    }
    /**
     * Relasi ke SPK (Surat Perintah Kerja)
     */
    public function spk()
    {
        return $this->hasMany(Spk::class, 'leads_id', 'id');
    }

    // Relasi balik ke detail grup
    public function groupDetails()
    {
        return $this->hasMany(PerusahaanGroupDetail::class, 'leads_id', 'id');
    }
    public function platform()
    {
        return $this->belongsTo(Platform::class, 'platform_id', 'id');
    }
    // Tambahkan relasi ke Customer
    public function customer()
    {
        return $this->hasOne(Customer::class, 'leads_id');
    }
    public function jabatanPic()
    {
        return $this->belongsTo(JabatanPic::class, 'jabatan');
    }
    /**
     * Relasi ke LeadsKebutuhan untuk akses tim sales
     */
    public function leadsKebutuhan()
    {
        return $this->hasMany(LeadsKebutuhan::class, 'leads_id');
    }

    /**
     * Relasi untuk mendapatkan tim sales details melalui leads_kebutuhan
     */
    public function timSalesDThroughKebutuhan()
    {
        return $this->hasManyThrough(
            TimSalesDetail::class,
            LeadsKebutuhan::class,
            'leads_id',        // Foreign key di leads_kebutuhan
            'id',              // Foreign key di tim_sales_details
            'id',              // Local key di leads
            'tim_sales_d_id'   // Local key di leads_kebutuhan
        );
    }


    // Update scope AvailableCustomers
    public function scopeAvailableCustomers($query)
    {
        return $query->whereNotNull('customer_id')
            ->whereNull('deleted_at');
    }

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
    public function getTglLeadsAttribute($value)
    {
        // Pastikan locale Carbon diatur ke 'id' di config/app.php
        return Carbon::parse($value)->isoFormat('D MMMM Y');
    }
    /**
     * Scope untuk filter leads berdasarkan role user
     */
    public function scopeFilterByUserRole($query, $user = null)
    {
        $user = $user ?: Auth::user();

        if (!$user) {
            return $query;
        }

        // 🌟 Superadmin dapat mengakses SEMUA data tanpa filter
        if ($user->role_id == 2) {
            return $query;
        }

        // Sales division
        if (in_array($user->role_id, [29, 30, 31, 32, 33])) {
            if ($user->role_id == 29) {
                // Sales - hanya melihat leads mereka sendiri
                // Filter berdasarkan sl_leads_kebutuhan
                $query->whereHas('leadsKebutuhan.timSalesD', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            } elseif ($user->role_id == 31) {
                // Sales Leader - melihat leads seluruh anggota tim
                $tim = TimSalesDetail::where('user_id', $user->id)->first();
                if ($tim) {
                    $memberSales = TimSalesDetail::where('tim_sales_id', $tim->tim_sales_id)
                        ->pluck('user_id')
                        ->toArray();

                    // Filter berdasarkan sl_leads_kebutuhan
                    $query->whereHas('leadsKebutuhan.timSalesD', function ($q) use ($memberSales) {
                        $q->whereIn('user_id', $memberSales);
                    });
                }
            }
            // Untuk role 30, 32, 33 (Sales lainnya) - tidak ada filter khusus
        }
        // RO division
        elseif (in_array($user->role_id, [4, 5, 6, 8])) {
            // RO - filter berdasarkan ro_id (jika kolom ada)
            if (in_array($user->role_id, [4, 5])) {
                $query->where('ro_id', $user->id);
            }
            // Role 6,8 - tanpa filter (lihat semua)
        }
        // CRM division
        elseif (in_array($user->role_id, [54, 55, 56])) {
            // CRM - filter berdasarkan crm_id (jika kolom ada)
            if ($user->role_id == 54) {
                $query->where('crm_id', $user->id);
            }
            // Role 55,56 - tanpa filter (lihat semua)
        }

        return $query;
    }

    /**
     * Scope untuk leads yang tersedia untuk aktivitas
     */
    public function scopeAvailableForActivity($query, $user = null)
    {
        $user = $user ?: Auth::user();

        // Panggil scope filter role
        return $this->scopeFilterByUserRole($query, $user);
    }

    /**
     * Scope untuk leads yang tersedia untuk quotation
     */
    public function scopeAvailableForQuotation($query, $user = null)
    {
        $user = $user ?: Auth::user();

        // Hanya leads parent (bukan child)
        $query->whereNull('leads_id');

        // Panggil scope filter role
        return $this->scopeFilterByUserRole($query, $user);
    }

}