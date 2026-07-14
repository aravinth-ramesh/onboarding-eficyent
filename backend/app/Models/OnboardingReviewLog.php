<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable entry in an application's review timeline:
 * submitted / resubmitted / approved / rejected / reopened.
 */
class OnboardingReviewLog extends Model
{
    protected $fillable = [
        'user_onboarding_id',
        'event',
        'admin_id',
        'comment',
    ];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(UserOnboarding::class, 'user_onboarding_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
