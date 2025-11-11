<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AplikasiPendukung extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_aplikasi_pendukung';

    protected $fillable = [
        'nama',
        'deskripsi',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    // Relasi ke quotation aplikasi jika diperlukan
    public function quotationAplikasis()
    {
        return $this->hasMany(QuotationAplikasi::class, 'aplikasi_pendukung_id');
    }
    public static function getAllActive()
    {
        return self::whereNull('deleted_at')->get();
    }
}