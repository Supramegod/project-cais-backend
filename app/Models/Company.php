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

    // Relasi untuk mendapatkan user
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors untuk menampilkan nama
    public function getCreatedByAttribute($value)
    {
        // Pastikan relasi sudah dimuat sebelum diakses
        if ($this->relationLoaded('creator')) {
            return $this->creator ? $this->creator->full_name : $value;
        }
        return $value;
    }

    public function getUpdatedByAttribute($value)
    {
        // Pastikan relasi sudah dimuat sebelum diakses
        if ($this->relationLoaded('updater')) {
            return $this->updater ? $this->updater->full_name : $value;
        }
        return $value;
    }
    /**
     * Format created_at jadi dd-mm-YYYY
     */
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