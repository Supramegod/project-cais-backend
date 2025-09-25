<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManagementFee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_management_fee';
    protected $fillable = ['nama','created_by','updated_by','deleted_by'];
    protected $hidden = ['updated_at', 'deleted_at'];

    // Relation jika diperlukan di masa depan
     /**
     * Format created_at jadi dd-mm-YYYY
     */
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
