<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SysmenuRole extends Model
{
    protected $table = 'sysmenu_role';

    protected $fillable = [
        'sysmenu_id',
        'role_id',
        'is_view',
        'is_add',
        'is_edit',
        'is_delete',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_view' => 'boolean',
        'is_add' => 'boolean',
        'is_edit' => 'boolean',
        'is_delete' => 'boolean',
    ];

    // Relationship dengan menu
    public function menu()
    {
        return $this->belongsTo(Sysmenu::class, 'sysmenu_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
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
    // Tambahkan di dalam model SysmenuRole
    public function scopeForRole($query, $roleId)
    {
        return $query->where('role_id', $roleId);
    }

    public function scopeWithViewPermission($query)
    {
        return $query->where('is_view', true);
    }
}