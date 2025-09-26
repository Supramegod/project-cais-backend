<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth; // Tambahkan ini
use App\Models\User; // Tambahkan ini

class Company extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_company';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();

        // Menggunakan event Eloquent untuk mengotomatiskan pengisian user ID
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
            } else {
                $model->created_by = 0; // Default jika tidak ada user
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            } else {
                $model->updated_by = 0; // Default jika tidak ada user
            }
        });
    }
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }


    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
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

    public function getCreatedByAttribute($value)
    {
        if ($this->relationLoaded('creator') && $this->creator) {
            return $this->creator->is_active ? $this->creator->full_name : null;
        }
        return null;
    }


    public function getUpdatedByAttribute($value)
    {
        if ($this->relationLoaded('updater') && $this->updater) {
            return $this->updater->is_active ? $this->updater->full_name : null;
        }
        return null;
    }

    public function getDeletedByAttribute($value)
    {
        if ($this->relationLoaded('deleter') && $this->deleter) {
            return $this->deleter->is_active ? $this->deleter->full_name : null;
        }
        return null;
    }
}