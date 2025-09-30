<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris'; // pakai koneksi DB kedua
    protected $table = 'm_branch';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'is_active',
        'name',
        'description',
    ];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function timSales()
    {
        return $this->hasMany(TimSales::class, 'branch_id', 'id');
    }
    public function leads()
    {
        return $this->hasMany(Leads::class, 'branch_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'branch_id');
    }
}
