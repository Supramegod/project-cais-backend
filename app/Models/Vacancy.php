<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vacancy extends Model
{
    use HasFactory;

    protected $connection = 'mysqlhris';
    protected $table = 'm_vacancy';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'icon_id',
        'start_date',
        'end_date',
        'company_id',
        'site_id',
        'position_id',
        'province_id',
        'city_id',
        'title',
        'type',
        'content',
        'needs',
        'phone_number1',
        'phone_number2',
        'flyer',
        'is_active',
        'durasi_ketelitian',
        'created_by',
        'updated_by'
    ];

    // Relasi ke Site HRIS
    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    // Relasi ke m_site HRIS (site_id → m_site.id) — dipakai pemenuhan HC.
    public function hrisSite()
    {
        return $this->belongsTo(HrisSite::class, 'site_id');
    }

    // Relasi ke posisi HRIS
    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    // Pelamar untuk lowongan ini (t_applicant.vacancy_id)
    public function applicants()
    {
        return $this->hasMany(Applicant::class, 'vacancy_id');
    }

    // Relasi ke Company HRIS
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}