<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Village extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_village';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'district_id',
        'name',
        'kode',
        'is_active'
    ];

    public function district()
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function leads()
    {
        return $this->hasMany(Leads::class, 'kelurahan_id');
    }
}