<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HrisSite extends Model
{
    use HasFactory;

    protected $table = 'm_site';


    protected $connection = 'mysqlhris';

    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'code',
        'proyek_id',
        'contract_number',
        'cais_quotation_id',
        'name',
        'address',
        'layanan_id',
        'client_id',
        'city_id',
        'branch_id',
        'company_id',
        'pic_id_1',
        'pic_id_2',
        'pic_id_3',
        'supervisor_id',
        'reliever',
        'canvaser',
        'contract_value',
        'contract_start',
        'contract_end',
        'contract_terminated',
        'note_terminated',
        'contract_status',
        'health_insurance_status',
        'labor_insurance_status',
        'vacation',
        'attendance_machine',
        'is_active',
        'created_by',
        'updated_by'
    ];

    /**
     * RELASI
     */

    public function company()
    {
        // Relasi ke model Company yang sudah Anda buat sebelumnya
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function quotation()
    {
        // Relasi ke model Quotation (cais_quotation_id)
        return $this->belongsTo(Quotation::class, 'cais_quotation_id');
    }

    public function client()
    {
        // client_id merujuk ke Leads/Client
        return $this->belongsTo(Client::class, 'client_id');
    }

    // ... relasi lainnya (pic, supervisor, dll)
}