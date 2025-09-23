<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BarangDefaultQty extends Model
{
    use SoftDeletes;

    protected $table = 'm_barang_default_qty';
    protected $fillable = ['barang_id', 'layanan_id', 'layanan', 'qty_default'];
}