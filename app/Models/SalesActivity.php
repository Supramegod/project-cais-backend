<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesActivity extends Model
{
    use HasFactory;

    // Nama tabel sesuai dengan SQL Query kita tadi
    protected $table = 'sl_activity_sales';

    // Kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'leads_id',
        'leads_kebutuhan_id',
        'quotation_id',
        'spk_id',
        'pks_id',
        'tgl_activity',
        'start',
        'end',
        'durasi',
        'penerima',
        'jenis_visit',
        'jenis_activity',
        'notulen',
        'created_by',
        'created_by_user_id'
    ];

    /**
     * Relasi ke Model Leads
     * Satu aktivitas dimiliki oleh satu Lead
     */
    public function lead()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    /**
     * Relasi ke Model LeadsKebutuhan
     * Aktivitas ini merujuk ke kebutuhan spesifik apa
     */
    public function leadsKebutuhan()
    {
        return $this->belongsTo(LeadsKebutuhan::class, 'leads_kebutuhan_id');
    }
    // app/Models/SalesActivity.php

    public function files()
    {
        return $this->hasMany(SalesActivityFile::class, 'activity_sales_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function spk()
    {
        return $this->belongsTo(Spk::class, 'spk_id');
    }

    public function pks()
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }

    /**
     * Jika created_by menyimpan ID User, kamu bisa tambahkan relasi ke User
     */
    public function creator()
    {
        // Sesuaikan dengan model User kamu, biasanya App\Models\User
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }
    public function getCreatedAtAttribute($value)
    {
        // Menggunakan 'd-m-Y H:i:s' untuk menyertakan jam (24-jam), menit, dan detik.
        return Carbon::parse($value)->format('d-m-Y H:i:s');
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('d-m-Y');
    }
}