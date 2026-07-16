<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bulk email scheduled to send at a future time.
 */
class ScheduledEmail extends Model
{
    protected $fillable = [
        'admin_id',
        'subject',
        'body',
        'onboarding_ids',
        'send_at',
        'status',
        'sent_count',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_ids' => 'array',
            'send_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** Pending emails whose time has come. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('send_at', '<=', now());
    }
}
