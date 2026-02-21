<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboardingStep extends Model
{
    protected $fillable = [
        'user_onboarding_id',
        'onboarding_step_id',
        'name',
        'component_key',
        'order',
        'status',
        'config',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(UserOnboarding::class, 'user_onboarding_id');
    }

    public function masterStep(): BelongsTo
    {
        return $this->belongsTo(OnboardingStep::class, 'onboarding_step_id');
    }
}
