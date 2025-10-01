<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadsKebutuhan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_leads_kebutuhan';
    protected $fillable = [
        'leads_id',
        'kebutuhan_id',
        'deleted_at',
        'deleted_by',
    ];

    public $timestamps = false; // kalau tabel pivot nggak punya created_at, updated_at

}

