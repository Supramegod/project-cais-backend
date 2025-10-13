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

    // Relasi ke Company HRIS
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}