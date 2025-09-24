<?php

// app/Models/KebutuhanDetailRequirement.php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KebutuhanDetailRequirement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_kebutuhan_detail_requirement';
    protected $fillable = [
        'kebutuhan_id',
        'position_id',
        'requirement',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'position_id' => 'integer',
        'kebutuhan_id' => 'integer'
    ];

    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class, 'kebutuhan_id');
    }

    public function kebutuhanDetail()
    {
        return $this->belongsTo(KebutuhanDetail::class, 'kebutuhan_id', 'kebutuhan_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
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