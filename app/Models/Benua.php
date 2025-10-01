<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Benua extends Model
{
    use HasFactory;

    protected $table = 'm_benua';
    protected $primaryKey = 'id_benua';
    
    protected $fillable = [
        'nama_benua',
        'kode_benua',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function negara()
    {
        return $this->hasMany(Negara::class, 'benua_id');
    }

    public function leads()
    {
        return $this->hasMany(Leads::class, 'benua_id');
    }
}