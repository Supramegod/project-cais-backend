<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Position extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_position';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'description',
        'company_id',
        'layanan_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'layanan_id' => 'integer',
        'company_id' => 'integer',
        'created_by' => 'integer', // Cast sebagai integer
        'updated_by' => 'integer', // Cast sebagai integer
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    // Relasi dengan company
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Relasi dengan kebutuhan
    public function kebutuhan()
    {
        return $this->belongsTo(Kebutuhan::class, 'layanan_id');
    }

    // Relasi dengan requirements
    public function requirements()
    {
        return $this->hasMany(RequirementPosisi::class, 'position_id');
    }

    // HAPUS relasi dengan users karena tidak ada kolom position_id di m_user
    // public function users()
    // {
    //     return $this->hasMany(User::class, 'position_id');
    // }

    // Scope untuk position aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    // Accessor untuk status text
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check() && !$model->created_by) {
                $model->created_by = auth()->id(); // Gunakan ID, bukan name
            } else if (!$model->created_by) {
                $model->created_by = 0; // Default value jika tidak ada user
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id(); // Gunakan ID, bukan name
            } else {
                $model->updated_by = 0; // Default value jika tidak ada user
            }
        });
    }
}