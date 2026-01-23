<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesActivityFile extends Model
{
    use SoftDeletes;

    protected $table = 'sl_activity_sales_file';
    protected $fillable = [
        'activity_sales_id',
        'nama_file',
        'url_file',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public function activity()
    {
        return $this->belongsTo(SalesActivity::class, 'activity_sales_id');
    }
}
