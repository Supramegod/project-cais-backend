<?php

namespace App\Models;

use App\Models\Leads;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_client';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'customer_id',
        'name', 
        'address', 
        'is_active',
        'created_by',
        'updated_by'
    ];

    // Relasi ke Site HRIS
    public function sites()
    {
        return $this->hasMany(Site::class, 'client_id');
    }

    // Relasi ke Leads (melalui customer_id)
    public function lead()
    {
        return $this->belongsTo(Leads::class, 'customer_id', 'id');
    }
}