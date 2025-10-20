<?php

namespace App\Models;

use Carbon\Carbon;
use DB;
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
    // Ganti scope WithPermissions untuk menggunakan LEFT JOIN
    public function scopeWithPermissions($query, $roleId)
    {
        return $query->leftJoin('sysmenu_role', function ($join) use ($roleId) {
            $join->on('sysmenu_role.sysmenu_id', '=', 'sysmenu.id')
                ->where('sysmenu_role.role_id', $roleId);
        });
    }
       public function scopeWithGroupInfo($query)
    {
        return $query->leftJoin('sysmenu_group', 'sysmenu_group.id', '=', 'sysmenu.group_id');
    }
    public function scopeOrdered($query)
    {
        return $query->orderBy('sysmenu_group.nama')
            ->orderBy('id');
    }

    // Tambahkan scope untuk filter view permission
    public function scopeWithViewPermission($query)
    {
        return $query->where('sysmenu_role.is_view', 1)
            ->orWhereNull('sysmenu_role.id'); // Include menus without permission records
    }

    // Update scope SelectMenuFields untuk handle null permissions
    public function scopeSelectMenuFields($query)
    {
        return $query->select(
            'sysmenu.id',
            'sysmenu.nama',
            'sysmenu.icon',
            'sysmenu.url',
            'sysmenu.parent_id',
            'sysmenu.group_id',
            'sysmenu_group.nama as group_name',
            DB::raw('COALESCE(sysmenu_role.is_view, 0) as is_view'),
            DB::raw('COALESCE(sysmenu_role.is_add, 0) as is_add'),
            DB::raw('COALESCE(sysmenu_role.is_edit, 0) as is_edit'),
            DB::raw('COALESCE(sysmenu_role.is_delete, 0) as is_delete')
        );
    }
}