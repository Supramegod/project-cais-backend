<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationTraining extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sl_quotation_training';

    protected $fillable = [
        'quotation_id',
        'training_id',
        'nama',
        'harga',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $dates = ['deleted_at'];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function training()
    {
        return $this->belongsTo(Training::class, 'training_id');
    }
}