<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\PerusahaanGroupDetail; // Pastikan ini diimpor

class PerusahaanGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_perusahaan_groups';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nama_grup',
        'jumlah_perusahaan',
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
     * Relasi ke detail grup (One-to-Many).
     * Satu Grup memiliki banyak PerusahaanGroupDetail.
     */
    public function details()
    {
        return $this->hasMany(PerusahaanGroupDetail::class, 'group_id', 'id');
    }

    /**
     * Scope untuk pencarian berdasarkan nama_grup.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('nama_grup', 'like', '%' . $search . '%');
    }

    /**
     * Accessor untuk created_at format Indonesia.
     */
    public function getCreatedAtFormattedAttribute()
    {
        // Pastikan created_at ada dan bukan string kosong sebelum parsing
        return $this->created_at ? Carbon::parse($this->created_at)->isoFormat('D MMMM Y') : null;
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
