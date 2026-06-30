<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PksWizardStatus extends Model
{
    public const INITIALIZED = 1;
    public const IN_PROGRESS = 2;
    public const READY_TO_FINALIZE = 3;
    public const FINALIZED = 4;
    public const CANCELLED = 5;

    protected $table = 'm_pks_wizard_status';

    protected $fillable = [
        'kode',
        'nama',
        'keterangan',
        'urutan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function pks(): HasMany
    {
        return $this->hasMany(Pks::class, 'wizard_status_id');
    }
}
