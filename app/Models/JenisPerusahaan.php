<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\User;

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
                $model->created_by = Auth::user()->id;
            } else {
                $model->created_by = null;
            }
        });

        // Saat update
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::user()->id;
            } else {
                $model->updated_by = null;
            }
        });

        // Saat delete (soft delete)
        static::deleting(function ($model) {
            if (Auth::check()) {
                $model->deleted_by = Auth::user()->id;
            } else {
                $model->deleted_by = null;
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

    /**
     * Format created_at jadi dd-mm-YYYY
     */
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

    /**
     * Relasi ke user untuk created_by
     */
    // public function creator()
    // {
    //     return $this->belongsTo(User::class, 'created_by', 'id');
    // }

    // public function updater()
    // {
    //     return $this->belongsTo(User::class, 'updated_by', 'id');
    // }

    // public function deleter()
    // {
    //     return $this->belongsTo(User::class, 'deleted_by', 'id');
    // }

    /**
     * Ganti created_by id dengan full_name
     */
    public function getCreatedByAttribute($value)
    {
        return $this->creator ? $this->creator->full_name : null;
    }

    /**
     * Ganti updated_by id dengan full_name
     */
    public function getUpdatedByAttribute($value)
    {
        return $this->updater ? $this->updater->full_name : null;
    }

    /**
     * Ganti deleted_by id dengan full_name
     */
    public function getDeletedByAttribute($value)
    {
        return $this->deleter ? $this->deleter->full_name : null;
    }
}
