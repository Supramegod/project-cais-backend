<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RuleThr extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_rule_thr';
    protected $fillable = [
        'nama', 
        'hari_penagihan_invoice',
        'hari_pembayaran_invoice', 
        'hari_rilis_thr',
        'created_by', 
        'updated_by'
    ];
    protected $dates = ['deleted_at'];

    // Relasi ke Pks
    public function pks()
    {
        return $this->hasMany(Pks::class, 'rule_thr_id');
    }
}