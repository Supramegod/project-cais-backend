<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Position extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_position';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'description',
        'company_id',
        'layanan_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'layanan_id' => 'integer',
        'company_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relasi dengan company
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Relasi dengan kebutuhan
    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class, 'layanan_id');
    }

    // Relasi dengan requirements
    public function requirements()
    {
        return $this->hasMany(RequirementPosisi::class, 'position_id');
    }

    // Relasi ke user untuk created_by dan updated_by
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Mutator untuk memastikan created_by/updated_by diisi secara otomatis
    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_by = Auth::id();
        });

        static::updating(function ($model) {
            $model->updated_by = Auth::id();
        });
    }

    // Accessor untuk mendapatkan nama pengguna dari relasi
public function getCreatedByAttribute($value)
{
    // Jika relasi creator dimuat dan ada, kembalikan nama lengkap.
    if ($this->relationLoaded('creator') && $this->creator) {
        return $this->creator->full_name;
    }
    // Jika tidak, kembalikan nilai ID aslinya.
    return $value;
}

public function getUpdatedByAttribute($value)
{
    // Jika relasi updater dimuat dan ada, kembalikan nama lengkap.
    if ($this->relationLoaded('updater') && $this->updater) {
        return $this->updater->full_name;
    }
    // Jika tidak, kembalikan nilai ID aslinya.
    return $value;
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