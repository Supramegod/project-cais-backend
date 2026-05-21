<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PksPerjanjianHistory extends Model
{
    protected $table = 'sl_pks_perjanjian_history';
    protected $fillable = [
        'pks_perjanjian_id', 'pks_id', 'pasal', 'judul', 'raw_text', 'snapshot', 'changed_by'
    ];
    protected $casts = [
        'snapshot' => 'array',
    ];

    // Relasi ke perjanjian asal (versi terbaru)
    public function perjanjian()
    {
        return $this->belongsTo(PksPerjanjian::class, 'pks_perjanjian_id');
    }

    // Relasi ke PKS induk
    public function pks()
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }
}