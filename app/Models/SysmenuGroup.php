<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SysmenuGroup extends Model
{
    use HasFactory;

    protected $table = 'sysmenu_group';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nama',
    ];

    /**
     * Relasi ke Sysmenu (One to Many)
     * Satu group memiliki banyak sysmenu
     */
    public function sysmenus()
    {
        return $this->hasMany(Sysmenu::class, 'group_id', 'id');
    }
}
