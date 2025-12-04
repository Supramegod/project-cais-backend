<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleThr extends Model
{
    use SoftDeletes;

    protected $table = 'm_rule_thr';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nama',
        'hari_penagihan_invoice',
        'hari_pembayaran_invoice',
        'hari_rilis_thr',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    // Relasi ke Pks
    public function pks(): HasMany
    {
        return $this->hasMany(Pks::class, 'rule_thr_id');
    }
}