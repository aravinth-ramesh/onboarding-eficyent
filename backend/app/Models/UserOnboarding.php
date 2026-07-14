<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserOnboarding extends Model
{
    protected $fillable = [
        'user_id',
        'user_type_id',
        'user_type_subcategory_id',
        'country_code',
        'registration_details',
        'status',
        'current_step_id',
        'template_version',
        'started_at',
        'completed_at',
        'decided_at',
        'decided_by',
        'decision_comment',
        'assigned_to',
        'reopened_at',
    ];

    protected function casts(): array
    {
        return [
            'registration_details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'decided_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'decided_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function reviewLogs(): HasMany
    {
        return $this->hasMany(OnboardingReviewLog::class)->orderBy('created_at')->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OnboardingNote::class)->latest()->latest('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OnboardingMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Human-facing reference, e.g. ONB-2026-0042. Must stay in sync with
     * formatReference() in frontend/src/components/layout/AppLayout.js —
     * clients quote this number from both the portal and emails.
     */
    public function getReferenceAttribute(): string
    {
        $padded = str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
        $year = $this->started_at?->format('Y');

        return $year ? "ONB-{$year}-{$padded}" : "ONB-{$padded}";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(UserTypeSubcategory::class, 'user_type_subcategory_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(UserOnboardingStep::class)->orderBy('order');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(UserOnboardingStep::class, 'current_step_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }
}
