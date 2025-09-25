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