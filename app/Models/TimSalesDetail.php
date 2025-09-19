<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimSalesDetail extends Model
{
    use HasFactory;

    protected $table = 'm_tim_sales_d';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'tim_sales_id',
        'user_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    // Relasi ke tim sales
    public function timSales()
    {
        return $this->belongsTo(TimSales::class, 'tim_sales_id', 'id');
    }

    // Relasi ke user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
