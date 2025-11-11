<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Issue extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_issue';
    protected $fillable = [
        'pks_id', 'judul', 'jenis_keluhan', 'kolaborator', 'deskripsi', 'url_lampiran', 'status',
        'created_by', 'updated_by', 'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function pks()
    {
        return $this->belongsTo(Pks::class, 'pks_id');
    }
      public function lead()
    {
        return $this->belongsTo(Leads::class, 'leads_id');
    }
}