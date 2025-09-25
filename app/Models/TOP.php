<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Top extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_top';
    protected $fillable = ['nama', 'persentase','created_by','updated_by','deleted_by'];
    protected $hidden = ['updated_at', 'deleted_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'persentase' => 'decimal:2',
    ];

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