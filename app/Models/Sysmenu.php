<?php

namespace App\Models;

use App\Services\MenuPermissionService;
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
        'status',
        'created_by',
        'created_by_user_id',
        'updated_by',
        'deleted_by',
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
        return $query->whereNull($this->getTable().'.deleted_at');
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
    public function scopeWithPermissions($query, $roleId, $userId = null)
    {
        $query->leftJoin('sysmenu_role', function ($join) use ($roleId) {
            $join->on('sysmenu_role.sysmenu_id', '=', 'sysmenu.id')
                ->where('sysmenu_role.role_id', $roleId)
                ->whereNull('sysmenu_role.user_id');
        });

        if ($userId !== null) {
            $query->leftJoin('sysmenu_role as sysmenu_role_user', function ($join) use ($roleId, $userId) {
                $join->on('sysmenu_role_user.sysmenu_id', '=', 'sysmenu.id')
                    ->where('sysmenu_role_user.role_id', $roleId)
                    ->where('sysmenu_role_user.user_id', $userId);
            });
        }

        return $query;
    }

    public function scopeWithGroupInfo($query)
    {
        return $query->leftJoin('sysmenu_group', 'sysmenu_group.id', '=', 'sysmenu.group_id');
    }

    // Di dalam Sysmenu.php
    public function scopeOrdered($query)
    {
        return $query->orderBy('sysmenu_group.sort_order')
            ->orderBy('sysmenu.id');
    }

    // Tambahkan scope untuk filter view permission
    public function scopeWithViewPermission($query)
    {
        return $query->where('sysmenu_role.is_view', 1)
            ->orWhereNull('sysmenu_role.id'); // Include menus without permission records
    }

    // Update scope SelectMenuFields untuk handle null permissions
    public function scopeSelectMenuFields($query, bool $withUserOverride = false)
    {
        $columns = [
            'sysmenu.id',
            'sysmenu.nama',
            'sysmenu.icon',
            'sysmenu.status',
            'sysmenu.url',
            'sysmenu.parent_id',
            'sysmenu.group_id',
            'sysmenu_group.nama as group_name',
        ];

        if ($withUserOverride) {
            $columns[] = DB::raw('CASE WHEN sysmenu_role_user.id IS NOT NULL THEN 1 ELSE 0 END as has_override');
        }

        foreach (MenuPermissionService::FIELDS as $field) {
            if ($withUserOverride) {
                $columns[] = DB::raw(
                    'CASE WHEN sysmenu_role_user.id IS NOT NULL'
                    .' THEN COALESCE(sysmenu_role_user.'.$field.', 0)'
                    .' ELSE COALESCE(sysmenu_role.'.$field.', 0) END as '.$field
                );
                $columns[] = DB::raw('COALESCE(sysmenu_role_user.'.$field.', 0) as override_'.$field);

                continue;
            }

            $columns[] = DB::raw('COALESCE(sysmenu_role.'.$field.', 0) as '.$field);
        }

        return $query->select($columns);
    }
}
