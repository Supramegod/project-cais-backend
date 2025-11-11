<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusPks extends Model
{
    use SoftDeletes;

    protected $table = 'm_status_pks';
    protected $primaryKey = 'id';
    
    protected $fillable = ['nama', 'created_by', 'updated_by'];
    protected $dates = ['deleted_at'];

    public function pks(): HasMany
    {
        return $this->hasMany(Pks::class, 'status_pks_id');
    }
}