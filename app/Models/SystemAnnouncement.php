<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemAnnouncement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category',
        'version',
        'release_date',
        'title',
        'description',
        'details',
        'is_active',
        'created_by'
    ];

    public function files()
    {
        return $this->hasMany(SystemAnnouncementFile::class, 'system_announcement_id');
    }
}
