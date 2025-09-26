<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TunjanganPosisi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_tunjangan_posisi';
    protected $fillable = [
        'kebutuhan_id',
        'position_id',
        'nama',
        'nominal',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'nominal' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relasi ke Kebutuhan
    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class, 'kebutuhan_id');
    }

    // Relasi ke Position (cross-database)
    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    // Relasi ke User untuk tracking
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by', 'id');
    }

    // Accessor untuk format tanggal
    public function getCreatedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    public function getUpdatedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('d-m-Y') : null;
    }

    // Accessor untuk nama creator/updater/deleter
    public function getCreatedByNameAttribute()
    {
        if ($this->relationLoaded('creator') && $this->creator) {
            return $this->creator->is_active ? $this->creator->full_name : null;
        }
        return $this->created_by;
    }

    public function getUpdatedByNameAttribute()
    {
        if ($this->relationLoaded('updater') && $this->updater) {
            return $this->updater->is_active ? $this->updater->full_name : null;
        }
        return $this->updated_by;
    }

    public function getDeletedByNameAttribute()
    {
        if ($this->relationLoaded('deleter') && $this->deleter) {
            return $this->deleter->is_active ? $this->deleter->full_name : null;
        }
        return $this->deleted_by;
    }

    // Scope untuk filter active
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    // Boot method untuk auto-fill creator
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->user()->full_name ?? auth()->user()->name;
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->user()->full_name ?? auth()->user()->name;
            }
        });

        static::deleting(function ($model) {
            if (auth()->check()) {
                $model->deleted_by = auth()->user()->full_name ?? auth()->user()->name;
            }
        });
    }
}