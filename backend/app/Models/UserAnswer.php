<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserAnswer extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
        'user_onboarding_id',
        'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(UserOnboarding::class, 'user_onboarding_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AnswerAuditLog::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AnswerFile::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(AdminNotification::class);
    }
}
