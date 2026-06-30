<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadsPic extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_leads_pic';

    protected $fillable = [
        'leads_id',
        'nama',
        'jabatan_id',
        'no_telp',
        'email',
        'is_kuasa',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function leads()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }

    public function jabatan()
    {
        return $this->belongsTo(JabatanPic::class, 'jabatan_id');
    }
}
