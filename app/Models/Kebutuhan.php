<?php

// app/Models/Kebutuhan.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kebutuhan extends Model
{
    use HasFactory, SoftDeletes;

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
}