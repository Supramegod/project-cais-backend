<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Spk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_spk';
    protected $fillable = [
        'leads_id',
        'nomor_quotation',
        'quotation_id',
        'nomor',
        'tgl_spk',
        'nama_perusahaan',
        'tim_sales_id',
        'tim_sales_d_id',
        'link_spk_disetujui',
        'status_spk_id',
        'created_by',
        'updated_by',
        'deleted_at',
        'deleted_by'
    ];


    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function timSales()
    {
        return $this->belongsTo(TimSales::class, 'tim_sales_id');
    }

    public function timSalesDetail()
    {
        return $this->belongsTo(TimSalesDetail::class, 'tim_sales_d_id');
    }

    public function statusSpk()
    {
        return $this->belongsTo(StatusSpk::class, 'status_spk_id');
    }
    public function pks()
    {
        return $this->hasMany(pks::class, 'spk_id');
    }

    public function spkSites()
    {
        return $this->hasMany(SpkSite::class, 'spk_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'spk_id');
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

    public function getTglSpkAttribute($value)
    {
        $carbonDate = Carbon::parse($value);
        $carbonDate->setLocale('id');
        return $carbonDate->isoFormat('D MMMM Y');
    }
    /**
     * SCOPE: Mendapatkan SPK berdasarkan leads_id
     * Logika filter ada di sini (DI MODEL SPK)
     */
    public function scopeByLeadsId(Builder $query, int $leadsId, array $filters = [])
    {
        return $query->where('leads_id', $leadsId);

    }

    /**
     * SCOPE: Hanya SPK aktif
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('status_spk_id', 2);
    }

    /**
     * SCOPE: SPK berdasarkan jenis
     */
    public function scopeByType(Builder $query, string $type)
    {
        return $query->where('jenis_SPK', $type);
    }

    /**
     * Method untuk summary SPK berdasarkan leads_id
     */
    public static function getSummaryByLeadsId(int $leadsId): array
    {
        $total = self::where('leads_id', $leadsId)->count();
        $active = self::where('leads_id', $leadsId)->active()->count();
        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
        ];
    }
    public function getNamaStatusAttribute()
    {
        if ($this->relationLoaded('statusSpk') && $this->statusSpk) {
            return $this->statusSpk->nama;
        }

        return $this->statusSpk?->nama;
    }
}