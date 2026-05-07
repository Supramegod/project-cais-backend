<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Consultation extends Model
{
    use HasFactory, SoftDeletes;

    // Nama tabel jika berbeda dengan jamak nama model
    protected $table = 'consultations';

    // Kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'nama_lengkap',
        'perusahaan',
        'aplikasi',
        'no_whatsapp',
        'alamat_email',
        'jadwal_konsultasi'
    ];

    // Mengatur agar jadwal_konsultasi otomatis menjadi objek Carbon/Tanggal
    protected $casts = [
        'jadwal_konsultasi' => 'datetime',
        'created_at' => 'datetime',
    ];
}