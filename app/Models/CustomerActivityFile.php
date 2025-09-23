<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerActivityFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_customer_activity_file';
    protected $fillable = [
        'customer_activity_id', 'nama_file', 'url_file', 'created_by', 'updated_by', 'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function customerActivity()
    {
        return $this->belongsTo(CustomerActivity::class, 'customer_activity_id');
    }
}