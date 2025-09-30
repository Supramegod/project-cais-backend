<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\PerusahaanGroup;
use App\Models\Leads; // Pastikan model Leads diimpor!

class PerusahaanGroupDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_perusahaan_groups_d';
    protected $primaryKey = 'id';

    protected $fillable = [
        'group_id',
        'leads_id',
        'nama_perusahaan',
        'created_by',
        'update_by',
        'deleted_by'
    ];

    protected $dates = [
        'created_at',
        'update_at',
        'deleted_at'
    ];

    public $timestamps = false;

    /**
     * Relasi ke Grup Perusahaan (Many-to-One).
     * Detail milik satu PerusahaanGroup.
     */
    public function group()
    {
        return $this->belongsTo(PerusahaanGroup::class, 'group_id', 'id');
    }

    /**
     * Relasi ke Leads (Many-to-One).
     * Detail ini merujuk ke satu Leads/Perusahaan.
     */
    public function lead()
    {
        return $this->belongsTo(Leads::class, 'leads_id', 'id');
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
}
