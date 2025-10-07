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
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::user()->id;
            } else {
                $model->updated_by = null;
            }
        });
        static::deleting(function ($model) {
            if (Auth::check()) {
                $model->deleted_by = Auth::user()->id;
            } else {
                $model->deleted_by = null;
            }
            $model->save();
        });
    }

    public static function getAllActive()
    {
        return self::whereNull('deleted_at')->get();
    }
    public static function getById($id)
    {
        return self::find($id);
    }


    public static function createNew($data)
    {
        $data['created_at'] = Carbon::now()->toDateTimeString();
        return self::create($data);
    }

  
    public static function updateData($id, $data)
    {
        $data['updated_at'] = Carbon::now()->toDateTimeString();
        return self::where('id', $id)->update($data);
    }
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by', 'id');
    }
    public function getCreatedByAttribute($value)
    {
        if ($this->relationLoaded('creator') && $this->creator) {
            return $this->creator->is_active ? $this->creator->full_name : null;
        }
        return null;
    }


    public function getUpdatedByAttribute($value)
    {
        if ($this->relationLoaded('updater') && $this->updater) {
            return $this->updater->is_active ? $this->updater->full_name : null;
        }
        return null;
    }
    public function getDeletedByAttribute($value)
    {
        if ($this->relationLoaded('deleter') && $this->deleter) {
            return $this->deleter->is_active ? $this->deleter->full_name : null;
        }
        return null;
    }
       // Relasi ke leads
    public function leads()
    {
        return $this->hasMany(Leads::class, 'jenis_perusahaan_id', 'id');
    }
}
