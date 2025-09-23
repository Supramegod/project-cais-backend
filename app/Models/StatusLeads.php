<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusLeads extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'm_status_leads';
    protected $fillable = ['nama', 'warna_background', 'warna_font'];
    protected $dates = ['deleted_at'];
}