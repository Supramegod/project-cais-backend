<?php
// app/Models/KebutuhanDetail.php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KebutuhanDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_kebutuhan_detail';
    protected $fillable = [
        'kebutuhan_id',
        'nama',
        'position_id',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class);
    }

    public function tunjangan()
    {
        return $this->hasMany(KebutuhanDetailTunjangan::class, 'kebutuhan_id', 'kebutuhan_id');
    }

    public function requirement()
    {
        return $this->hasMany(KebutuhanDetailRequirement::class, 'kebutuhan_id', 'kebutuhan_id');
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