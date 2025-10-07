<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusQuotation extends Model
{
    use HasFactory;

    protected $table = 'm_status_quotation';
    protected $guarded = [];

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'status_quotation_id');
    }
}