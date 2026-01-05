<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogApproval extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'log_approval';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tabel',
        'doc_id',
        'tingkat',
        'is_approve',
        'user_id',
        'approval_date',
        'note',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_approve' => 'boolean',
        'approval_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the user who created the approval log.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who created the record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'full_name');
    }

    /**
     * Get the user who updated the record.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'full_name');
    }

    /**
     * Get the user who deleted the record.
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by', 'full_name');
    }

    /**
     * Scope a query to only include approvals for a specific table.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $table
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTable($query, $table)
    {
        return $query->where('tabel', $table);
    }

    /**
     * Scope a query to only include approvals for a specific document.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $docId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDocument($query, $docId)
    {
        return $query->where('doc_id', $docId);
    }

    /**
     * Scope a query to only include approvals by specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include approved/rejected records.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $isApproved
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query, $isApproved = true)
    {
        return $query->where('is_approve', $isApproved);
    }

    /**
     * Scope a query to only include specific level approvals.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $level
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('tingkat', $level);
    }
}