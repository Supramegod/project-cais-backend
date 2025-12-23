<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogNotification extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'log_notification';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tabel',
        'doc_id',
        'transaksi',
        'pesan',
        'is_read',
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
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_read' => false
    ];

    /**
     * Get the user associated with the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who created the notification.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'full_name');
    }

    /**
     * Get the user who updated the notification.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'full_name');
    }

    /**
     * Get the user who deleted the notification.
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by', 'full_name');
    }

    /**
     * Scope a query to only include notifications for a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include unread notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $unread
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnread($query, $unread = true)
    {
        return $query->where('is_read', !$unread);
    }

    /**
     * Scope a query to only include notifications for a specific table.
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
     * Scope a query to only include notifications for a specific document.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $table
     * @param  int  $docId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDocument($query, $table, $docId)
    {
        return $query->where('tabel', $table)->where('doc_id', $docId);
    }

    /**
     * Scope a query to only include notifications for a specific transaction type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $transaction
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTransaction($query, $transaction)
    {
        return $query->where('transaksi', $transaction);
    }

    /**
     * Scope a query to order by creation date (newest first).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to include notifications within a date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $startDate
     * @param  string  $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Mark notification as read.
     *
     * @param  string  $updaterName
     * @return bool
     */
    public function markAsRead($updaterName = null)
    {
        return $this->update([
            'is_read' => true,
            'updated_at' => now(),
            'updated_by' => $updaterName ?? auth()->user()->full_name ?? 'System'
        ]);
    }

    /**
     * Mark notification as unread.
     *
     * @param  string  $updaterName
     * @return bool
     */
    public function markAsUnread($updaterName = null)
    {
        return $this->update([
            'is_read' => false,
            'updated_at' => now(),
            'updated_by' => $updaterName ?? auth()->user()->full_name ?? 'System'
        ]);
    }

    /**
     * Mark multiple notifications as read.
     *
     * @param  array  $notificationIds
     * @param  string  $updaterName
     * @return int
     */
    public static function markMultipleAsRead(array $notificationIds, $updaterName = null)
    {
        return self::whereIn('id', $notificationIds)
            ->update([
                'is_read' => true,
                'updated_at' => now(),
                'updated_by' => $updaterName ?? auth()->user()->full_name ?? 'System'
            ]);
    }

    /**
     * Get unread notifications count for a user.
     *
     * @param  int  $userId
     * @return int
     */
    public static function getUnreadCount($userId)
    {
        return self::forUser($userId)
            ->unread(true)
            ->count();
    }

    /**
     * Create a new notification.
     *
     * @param  array  $data
     * @return LogNotification
     */
    public static function createNotification(array $data)
    {
        $defaults = [
            'is_read' => false,
            'created_at' => now(),
            'created_by' => auth()->user()->full_name ?? 'System'
        ];
        
        return self::create(array_merge($defaults, $data));
    }

    /**
     * Create a quotation approval notification.
     *
     * @param  int  $userId
     * @param  int  $quotationId
     * @param  string  $quotationNumber
     * @param  string  $approverName
     * @param  bool  $isApproved
     * @param  string|null  $reason
     * @return LogNotification
     */
    public static function createQuotationApprovalNotification(
        $userId, 
        $quotationId, 
        $quotationNumber, 
        $approverName, 
        $isApproved = true, 
        $reason = null
    ) {
        $message = $isApproved 
            ? "Quotation dengan nomor: {$quotationNumber} di approve oleh {$approverName}"
            : "Quotation dengan nomor: {$quotationNumber} di reject oleh {$approverName}" . 
              ($reason ? " dengan alasan: {$reason}" : "");
        
        return self::createNotification([
            'user_id' => $userId,
            'tabel' => 'sl_quotation',
            'doc_id' => $quotationId,
            'transaksi' => 'Quotation',
            'pesan' => $message,
            'created_by' => $approverName
        ]);
    }

    /**
     * Get notifications for quotation approval.
     *
     * @param  int  $quotationId
     * @param  int|null  $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getQuotationNotifications($quotationId, $userId = null)
    {
        $query = self::forDocument('sl_quotation', $quotationId);
        
        if ($userId) {
            $query->forUser($userId);
        }
        
        return $query->latestFirst()->get();
    }
}
