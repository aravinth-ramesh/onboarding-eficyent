<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal admin annotation on an application. Admin-panel only — this
 * model must never be serialized into a client-facing response or document.
 */
class OnboardingNote extends Model
{
    protected $fillable = [
        'user_onboarding_id',
        'admin_id',
        'note',
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
