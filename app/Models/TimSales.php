<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Tambahkan ini

class TimSales extends Model
{
    use HasFactory, SoftDeletes; // Dan tambahkan ini

    protected $table = 'm_tim_sales';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nama',
        'branch_id',
        'branch',
        'user_id',
        'created_by',
        'updated_by',
    ];

    public function details()
    {
        return $this->hasMany(TimSalesDetail::class, 'tim_sales_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}