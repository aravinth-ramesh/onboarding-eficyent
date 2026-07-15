<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotification extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'type',
        'user_answer_id',
        'admin_question_id',
        'message',
        'status',
        'read_at',
        'resolved_at',
        'checked_at',
        'checked_by',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function userAnswer(): BelongsTo
    {
        return $this->belongsTo(UserAnswer::class);
    }

    public function adminQuestion(): BelongsTo
    {
        return $this->belongsTo(AdminQuestion::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /** Client answered, but no admin has confirmed they looked at it yet. */
    public function scopeAwaitingCheck(Builder $query): Builder
    {
        return $query->where('status', 'resolved')->whereNull('checked_at');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'checked_by');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
