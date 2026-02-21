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
        'status',
        'current_step_id',
        'template_version',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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
