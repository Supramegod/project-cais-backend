<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Position extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang digunakan oleh model ini
     */
    protected $table = 'm_position';

    /**
     * Koneksi database yang digunakan
     * Sesuai dengan controller asli yang menggunakan connection('mysqlhris')
     */
    protected $connection = 'mysqlhris';

    /**
     * Primary key untuk tabel
     */
    protected $primaryKey = 'id';

    /**
     * Menentukan apakah primary key auto increment
     */
    public $incrementing = true;

    /**
     * Tipe data primary key
     */
    protected $keyType = 'int';

    /**
     * Menentukan apakah model menggunakan timestamps
     */
    public $timestamps = true;

    /**
     * Field yang dapat diisi secara mass assignment
     */
    protected $fillable = [
        'name',
        'description',
        'company_id',
        'layanan_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * Casting atribut ke tipe data tertentu
     */
    protected $casts = [
        'is_active' => 'boolean',
        'layanan_id' => 'integer',
        'company_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope untuk mengambil position yang aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope untuk mengambil position berdasarkan department
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope untuk mengambil position berdasarkan level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Relasi dengan department (jika ada)
     * Uncomment jika Anda memiliki model Department
     */
    // public function department()
    // {
    //     return $this->belongsTo(Department::class, 'department_id');
    // }

    /**
     * Relasi dengan kebutuhan detail tunjangan
     */
    public function kebutuhanDetailTunjangan()
    {
        return $this->hasMany(KebutuhanDetailTunjangan::class, 'position_id');
    }

    /**
     * Relasi dengan kebutuhan detail requirement
     */
    public function kebutuhanDetailRequirement()
    {
        return $this->hasMany(KebutuhanDetailRequirement::class, 'position_id');
    }

    /**
     * Accessor untuk mendapatkan status aktif dalam bentuk text
     */
    public function getStatusTextAttribute()
    {
        return $this->is_active ? 'Aktif' : 'Tidak Aktif';
    }

    /**
     * Mutator untuk mengset created_by dan updated_by saat data dibuat/diperbarui
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check() && !$model->created_by) {
                $model->created_by = auth()->user()->full_name ?? auth()->user()->name ?? 'system';
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->user()->full_name ?? auth()->user()->name ?? 'system';
            }
        });

        // Hapus callback deleting karena tidak lagi menggunakan SoftDeletes
        // static::deleting(function ($model) {
        //     if (auth()->check()) {
        //         $model->deleted_by = auth()->user()->full_name ?? auth()->user()->name ?? 'system';
        //         $model->save();
        //     }
        // });
    }
}