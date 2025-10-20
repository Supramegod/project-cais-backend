<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimSalesDetail extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'm_tim_sales_d';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'tim_sales_id',
        'nama',
        'username',
        'user_id',
        'is_leader',
        'is_active',
        'created_by',
        'deleted_by',
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
    // Di file TimSalesDetail.php
/**
 * Scope untuk mendapatkan member sales berdasarkan tim_sales_id
 */
public function scopeByTeam($query, $timSalesId)
{
    return $query->where('tim_sales_id', $timSalesId);
}

/**
 * Scope untuk user aktif
 */
public function scopeActive($query)
{
    return $query->where('is_active', 1);
}
}
