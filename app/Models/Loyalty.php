<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loyalty extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_loyalty';
    protected $fillable = ['nama', 'created_by', 'updated_by'];
    protected $dates = ['deleted_at'];

    // Relasi ke Pks
    public function pks()
    {
        return $this->hasMany(Pks::class, 'loyalty_id');
    }
}