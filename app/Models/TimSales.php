<?php

namespace App\Models;

use Carbon\Carbon;
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
     public function leads()
    {
        return $this->hasMany(Leads::class, 'tim_sales_id');
    }

    public function customerActivities()
    {
        return $this->hasMany(CustomerActivity::class, 'tim_sales_id');
    }
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }

    /**
     * Format updated_at jadi dd-mm-YYYY
     */
    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
    
}