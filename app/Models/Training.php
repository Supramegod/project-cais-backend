<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_training';

    protected $fillable = [
        'jenis',
        'nama',
        'jp',
        'menit',
        'total',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'jp' => 'integer',
        'menit' => 'integer',
        'total' => 'integer'
    ];

    // Accessor untuk format tanggal
    public function getCreatedAtFormattedAttribute()
    {
        return $this->created_at ? $this->created_at->format('d M Y H:i') : null;
    }

    public function getUpdatedAtFormattedAttribute()
    {
        return $this->updated_at ? $this->updated_at->format('d M Y H:i') : null;
    }

    // Mutator untuk menghitung total otomatis
    public function setJpAttribute($value)
    {
        $this->attributes['jp'] = $value;
        $this->calculateTotal();
    }

    public function setMenitAttribute($value)
    {
        $this->attributes['menit'] = $value;
        $this->calculateTotal();
    }

    private function calculateTotal()
    {
        if (isset($this->attributes['jp']) && isset($this->attributes['menit'])) {
            $this->attributes['total'] = $this->attributes['jp'] * $this->attributes['menit'];
        }
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