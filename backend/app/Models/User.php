<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'position',
        'email',
        'is_admin',
        'status',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    public function onboarding(): HasOne
    {
        return $this->hasOne(UserOnboarding::class);
    }

    public function collaboration(): HasOne
    {
        return $this->hasOne(OnboardingCollaborator::class);
    }

    /**
     * The onboarding this user works on: their own, or — for invited team
     * members — the one they collaborate on. All client-facing endpoints
     * resolve through this.
     */
    public function activeOnboarding(): ?UserOnboarding
    {
        return $this->onboarding ?? $this->collaboration?->onboarding;
    }

    /** Owners may manage the team; collaborators may not. */
    public function ownsActiveOnboarding(): bool
    {
        return $this->onboarding !== null;
    }

    public function answers(): HasMany
    {
        return $this->hasMany(UserAnswer::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AnswerAuditLog::class);
    }

    public function adminNotifications(): HasMany
    {
        return $this->hasMany(AdminNotification::class);
    }

    public function adminQuestions(): HasMany
    {
        return $this->hasMany(AdminQuestion::class);
    }
}
