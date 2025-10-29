<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pks extends Model
{
    use SoftDeletes;

    protected $table = 'sl_pks';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'leads_id','quotation_id', 'nomor', 'tgl_pks', 'nama_perusahaan', 'kontrak_awal', 
        'kontrak_akhir', 'status_pks_id', 'company_id', 'salary_rule_id', 
        'rule_thr_id', 'branch_id', 'kode_perusahaan', 'alamat_perusahaan',
        'layanan_id', 'layanan', 'bidang_usaha_id', 'bidang_usaha',
        'jenis_perusahaan_id', 'jenis_perusahaan', 'provinsi_id', 'provinsi',
        'kota_id', 'kota', 'pma', 'sales_id', 'crm_id_1', 'crm_id_2', 'crm_id_3',
        'spv_ro_id', 'ro_id_1', 'ro_id_2', 'ro_id_3', 'loyalty_id', 'loyalty',
        'kategori_sesuai_hc_id', 'kategori_sesuai_hc', 'link_pks_disetujui',
        'total_sebelum_pajak', 'dasar_pengenaan_pajak', 'ppn', 'pph', 'total_invoice',
        'persen_mf', 'nominal_mf', 'persen_bpjs_tk', 'nominal_bpjs_tk',
        'persen_bpjs_ks', 'nominal_bpjs_ks', 'as_tk', 'as_ks', 'ohc', 'thr_provisi',
        'thr_ditagihkan', 'penagihan_selisih_thr', 'kaporlap', 'device', 'chemical',
        'training', 'biaya_training', 'tgl_kirim_invoice', 'jumlah_hari_top',
        'tipe_hari_top', 'tgl_gaji', 'pic_1', 'jabatan_pic_1', 'email_pic_1',
        'telp_pic_1', 'pic_2', 'jabatan_pic_2', 'email_pic_2', 'telp_pic_2',
        'pic_3', 'jabatan_pic_3', 'email_pic_3', 'telp_pic_3', 'is_aktif',
        'ot1', 'ot2', 'ot3', 'ot4', 'ot5', 'created_by', 'updated_by'
    ];

    protected $dates = ['tgl_pks', 'kontrak_awal', 'kontrak_akhir', 'deleted_at'];

    // Relationships
    public function leads(): BelongsTo
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function statusPks(): BelongsTo
    {
        return $this->belongsTo(StatusPks::class, 'status_pks_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'pks_id');
    }

    public function perjanjian(): HasMany
    {
        return $this->hasMany(PksPerjanjian::class, 'pks_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CustomerActivity::class, 'pks_id');
    }
        public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
}