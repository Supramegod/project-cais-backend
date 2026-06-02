<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class BentukUsaha extends Model
{
    use SoftDeletes;

    protected $table = 'm_bentuk_usaha';

    protected $fillable = [
        'nama',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $dates = ['deleted_at'];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->created_by = Auth::check() ? Auth::user()->id : null;
        });
        static::updating(function ($model) {
            $model->updated_by = Auth::check() ? Auth::user()->id : null;
        });
        static::deleting(function ($model) {
            $model->deleted_by = Auth::check() ? Auth::user()->id : null;
            $model->save();
        });
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
}
