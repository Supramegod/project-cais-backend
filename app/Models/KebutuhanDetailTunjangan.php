<?php

// app/Models/KebutuhanDetailTunjangan.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KebutuhanDetailTunjangan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_kebutuhan_detail_tunjangan';
    protected $fillable = [
        'kebutuhan_id',
        'position_id',
        'nama',
        'nominal',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
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
