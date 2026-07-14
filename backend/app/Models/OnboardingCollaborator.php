<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Membership of an invited team member on an application.
 */
class OnboardingCollaborator extends Model
{
    protected $fillable = [
        'user_onboarding_id',
        'user_id',
        'invited_by',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(UserOnboarding::class, 'user_onboarding_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
