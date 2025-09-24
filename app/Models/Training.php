<?php

namespace App\Models;

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
}