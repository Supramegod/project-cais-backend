<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sysmenu extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sysmenu';

    protected $fillable = [
        'nama',
        'kode',
        'parent_id',
        'url',
        'icon',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    // Relationship dengan parent menu
    public function parent()
    {
        return $this->belongsTo(Sysmenu::class, 'parent_id');
    }

    // Relationship dengan child menus
    public function children()
    {
        return $this->hasMany(Sysmenu::class, 'parent_id');
    }

    // Relationship dengan roles
    public function roles()
    {
        return $this->hasMany(SysmenuRole::class, 'sysmenu_id');
    }

    // Scope untuk menu yang tidak terhapus
    public function scopeActive($query)
    {
        return $query->whereNull($this->getTable() . '.deleted_at');
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