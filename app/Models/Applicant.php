<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * HRIS applicant (t_applicant) — READ-ONLY dari sisi CAIS.
 * Jembatan antara lowongan (vacancy) dan kandidat (employee).
 * Catatan: tabel ini tidak punya kolom `id` (PK), hanya index employee_id,
 * vacancy_id, is_active — dipakai untuk join.
 */
class Applicant extends Model
{
    protected $connection = 'mysqlhris';

    protected $table = 't_applicant';

    // Tidak ada primary key auto-increment di t_applicant.
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'employee_id' => 'integer',
        'vacancy_id' => 'integer',
        'is_active' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class, 'vacancy_id');
    }
}
