<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HRIS employee (m_employee) — READ-ONLY dari sisi CAIS.
 * Menyimpan status rekrutmen: status_approval & followup_status.
 */
class Employee extends Model
{
    protected $connection = 'mysqlhris';

    protected $table = 'm_employee';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    // Read-only dari CAIS — jangan kelola timestamp/menulis.
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'status_approval' => 'integer',
        'is_active' => 'integer',
    ];

    public function applicants()
    {
        return $this->hasMany(Applicant::class, 'employee_id');
    }
}
