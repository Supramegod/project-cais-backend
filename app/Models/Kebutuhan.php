<?php

// app/Models/Kebutuhan.php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kebutuhan extends Model
{
    use HasFactory, SoftDeletes;
    protected $connection = 'mysql';
    protected $table = 'm_kebutuhan';
    protected $fillable = [
        'nama',
        'icon',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    // Relasi langsung ke detail tunjangan dan requirement (sesuai controller)
    public function detailTunjangan()
    {
        return $this->hasMany(KebutuhanDetailTunjangan::class, 'kebutuhan_id');
    }

    public function detailRequirement()
    {
        return $this->hasMany(KebutuhanDetailRequirement::class, 'kebutuhan_id');
    }

    // Relasi ke detail (jika diperlukan)
    public function details()
    {
        return $this->hasMany(KebutuhanDetail::class);
    }
    public function positions()
    {
        return $this->hasMany(Position::class, 'layanan_id');
    }

    public function requirements()
    {
        return $this->hasMany(RequirementPosisi::class, 'kebutuhan_id');
    }
     public function leads()
    {
        // Ini adalah relasi custom karena kebutuhan_id disimpan sebagai string comma separated
        return; // Tidak ada relasi langsung
    }

    // Auto-fill creator
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check() && !$model->created_by) {
                $model->created_by = auth()->user()->full_name ?? auth()->user()->name ?? 'system';
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->user()->full_name ?? auth()->user()->name ?? 'system';
            }
        });

        static::deleting(function ($model) {
            if (auth()->check()) {
                $model->deleted_by = auth()->user()->full_name ?? auth()->user()->name ?? 'system';
                $model->save();
            }
        });

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
