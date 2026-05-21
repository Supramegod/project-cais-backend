<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemAnnouncementFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'system_announcement_id',
        'nama_file',
        'url_file',
        'mime_type',
        'size',
        'created_by',
    ];

    public function announcement()
    {
        return $this->belongsTo(SystemAnnouncement::class, 'system_announcement_id');
    }
}
