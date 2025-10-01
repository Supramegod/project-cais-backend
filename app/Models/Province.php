<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_province';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'nama',
        'kode',
        'is_active'
    ];

    public function cities()
    {
        return $this->hasMany(City::class, 'province_id');
    }

    public function leads()
    {
        return $this->hasMany(Leads::class, 'provinsi_id');
    }
}