<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class JenisPerusahaan extends Model
{
    use SoftDeletes;

    protected $table = 'm_jenis_perusahaan';

    protected $fillable = [
        'nama',
        'resiko',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const DELETED_AT = 'deleted_at';

    /**
     * Event hook supaya created_by, updated_by, deleted_by otomatis terisi
     */
    protected static function booted()
    {
        // Saat create
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::user()->full_name;
            } else {
                $model->created_by = 'system';
            }
        });

        // Saat update
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::user()->full_name;
            } else {
                $model->updated_by = 'system';
            }
        });

        // Saat delete (soft delete)
        static::deleting(function ($model) {
            if (Auth::check()) {
                $model->deleted_by = Auth::user()->full_name;
            } else {
                $model->deleted_by = 'system';
            }
            $model->save();
        });
    }

    /**
     * Get all active jenis perusahaan
     */
    public static function getAllActive()
    {
        return self::whereNull('deleted_at')->get();
    }

    /**
     * Get jenis perusahaan by ID
     */
    public static function getById($id)
    {
        return self::find($id);
    }

    /**
     * Create new jenis perusahaan
     */
    public static function createNew($data)
    {
        $data['created_at'] = Carbon::now()->toDateTimeString();
        return self::create($data);
    }

    /**
     * Update jenis perusahaan
     */
    public static function updateData($id, $data)
    {
        $data['updated_at'] = Carbon::now()->toDateTimeString();
        return self::where('id', $id)->update($data);
    }
}
