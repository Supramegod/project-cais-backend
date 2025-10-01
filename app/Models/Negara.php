<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Negara extends Model
{
    use HasFactory;

    protected $table = 'm_negara';
    protected $primaryKey = 'id_negara';
    
    protected $fillable = [
        'benua_id',
        'nama_negara',
        'kode_negara',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function benua()
    {
        return $this->belongsTo(Benua::class, 'benua_id', 'id_benua');
    }

    public function leads()
    {
        return $this->hasMany(Leads::class, 'negara_id');
    }
}