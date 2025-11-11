<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JabatanPic extends Model
{
    use HasFactory, SoftDeletes; // Menggunakan SoftDeletes karena ada whereNull('deleted_at')

    protected $table = 'm_jabatan_pic';
    
    // Sesuaikan field di bawah ini dengan struktur kolom tabel Anda
    // Biasanya hanya nama jabatan dan status aktif/kode yang diisi
    protected $fillable = [
        'nama_jabatan',
        'kode',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    // Kolom yang akan disembunyikan dari JSON output (opsional)
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    // Casts (Opsional, sesuaikan jika ada boolean atau tipe data lain)
    // protected $casts = [
    //     'is_active' => 'boolean',
    // ];

    // Accessor untuk formatting tanggal (opsional, jika diperlukan)
    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
}